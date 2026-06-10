# 部署指南（Deployer + GitHub Actions Self-hosted Runner）

本文描述如何用 [Deployer](https://deployer.org) 对本项目做零停机部署，触发方式是推送 `main` 自动跑。

---

## 1. 为什么用 Deployer

- 自带 Laravel recipe，`releases/`、`shared/`、`current` 软链等样板都不用自己写。
- 内置 `rollback`、`deploy:unlock`、`deploy:info` 等运维命令。
- 失败时会自动恢复 `current` 指向上一版本，且能回滚迁移前的代码。
- 社区大，遇到问题容易搜到。

原本的 `scripts/deploy-zero-downtime.sh` 已经被替换，逻辑挪到 `deploy.php`。

---

## 2. 目录约定

服务器上部署根目录（默认 `/example/dogeow-api`，可通过 `DEPLOY_PATH` 改）结构：

```plaintext
/example/dogeow-api/
├── .dep/                 Deployer 内部状态（锁、历史）
├── current/              -> releases/<timestamp>   对外入口
├── releases/
│   ├── 20260419183000/
│   └── 20260420090000/
└── shared/
    ├── .env              真实配置（可提前手动放好，或由首发脚本自动复制）
    └── storage/          日志、session、上传的文件
```

- Nginx `root` 指向 `/example/dogeow-api/current/public`。
- Supervisor / Horizon 的 `directory` 也指向 `current`。
- 每个 release 里的 `.env`、`storage` 都是指向 `shared/` 的软链，升级不丢。

---

## 3. 前置条件

### 3.1 服务器软件

- PHP 8.4、Composer、Git
- MySQL 8、Redis 7
- Nginx、Supervisor
- 已安装并注册为该仓库的 GitHub self-hosted runner

### 3.2 Git 访问

当前 Deployer 配置不会在 `deploy:update_code` 阶段重新 `git clone` 仓库。

实际取码方式是：

1. GitHub Actions 先用 `actions/checkout` 拉取当前提交
2. Deployer 再把当前工作区内容同步到新的 `release` 目录

这样和旧版 `deploy-zero-downtime.sh` 的思路一致，避免部署阶段因为 runner 用户缺少 GitHub SSH key 而失败。

注意：

- 自动部署仍然依赖 `actions/checkout` 能正常拉仓库
- 手动执行 `scripts/ensure-deployer.sh deploy production` 时，需要先确保当前目录就是已经更新到目标提交的项目工作树
- 如果后续要改回 “Deployer 自己 clone 仓库”，再单独为 runner 用户配置 GitHub Deploy Key

### 3.3 sudo 免密

Deployer 在部署最后会重启 Supervisor，runner 用户需要无交互执行 `supervisorctl`：

```bash
# sudo visudo -f /etc/sudoers.d/deploy-runner
<runner-user> ALL=(root) NOPASSWD: /usr/bin/supervisorctl
```

验证：`sudo -n supervisorctl status`，应立即返回且不提示密码。

补充说明：

- 如果你的 `supervisorctl` 实际安装在 `/usr/local/bin/supervisorctl` 等非默认位置，sudoers 里要放行真实绝对路径。
- 当前 `deploy.php` 会先解析 `supervisorctl` 的绝对路径，再用 `sudo -n` 调用，避免 `sudo` 的 `secure_path` 找不到命令。
- 如果机器上不在常规 `PATH`，可在 GitHub Actions Secret 或手动部署命令里额外传 `SUPERVISORCTL_BIN=/实际/路径/supervisorctl`。

### 3.3.1 writable 权限模式

当前 `deploy.php` 显式使用 `chmod` 处理 writable 目录，不依赖 `setfacl` / ACL 工具。

原因：

- 常见的 ECS / 精简 Linux 镜像默认没有安装 `acl`
- Deployer 默认优先尝试 ACL，缺少 `setfacl` 时会在 `deploy:writable` 失败
- 当前部署显式覆盖了 Laravel recipe 的默认 `writable_dirs`，仅对 release 内部的 `bootstrap/cache` 做 writable 处理，不再递归修改 `shared/storage`

如果后续你想改回 ACL 模式，再额外安装系统包并调整 `deploy.php`。

### 3.4 首次部署准备

在目标服务器上一次性完成：

```bash
sudo mkdir -p /example/dogeow-api/shared
sudo mkdir -p /example/dogeow-api/shared/storage \
    /example/dogeow-api/shared/storage/app/public \
    /example/dogeow-api/shared/storage/framework/cache \
    /example/dogeow-api/shared/storage/framework/sessions \
    /example/dogeow-api/shared/storage/framework/views \
    /example/dogeow-api/shared/storage/logs
sudo chown -R <runner-user>:<runner-user> /example/dogeow-api
cp /path/to/your/.env /example/dogeow-api/shared/.env
chmod 640 /example/dogeow-api/shared/.env
```

如果你已经在 runner 机器上的工作树里，可以直接运行首发脚本完成这一步初始化并触发第一次发布：

```bash
SUPERVISOR_GROUP=laravel-horizon \
scripts/first-deploy.sh
```

默认情况下，`scripts/first-deploy.sh` 会把当前工作树根目录当作 `DEPLOY_PATH`。
如果你的部署根目录不是当前仓库目录，再显式传入：

```bash
DEPLOY_PATH=/example/dogeow-api \
SUPERVISOR_GROUP=laravel-horizon \
scripts/first-deploy.sh
```

如果要从其他位置复制配置文件，再额外传入：

```bash
DEPLOY_PATH=/example/dogeow-api \
SUPERVISOR_GROUP=laravel-horizon \
LOCAL_ENV_FILE=/path/to/.env \
scripts/first-deploy.sh
```

脚本会：

- 创建 `shared/storage` 所需目录树
- 在 `shared/.env` 不存在时，默认把工作树根目录的 `.env` 复制过去
- 如果传了 `LOCAL_ENV_FILE`，则改为复制那个文件
- 自动下载或复用 Deployer，再调用现有 `deploy.php` 执行第一次 `deploy production`

约束：

- 只适用于还没有 `current` 且还没有时间戳 release 的部署根目录
- 不传 `DEPLOY_PATH` 时，默认使用当前工作树根目录
- 如果 `shared/.env` 已提前放好，且你不想覆盖它，可以不传 `LOCAL_ENV_FILE`
- 脚本不会执行 `sudo chown` 或重写既有 `shared/storage` 权限；如果传了 `LOCAL_ENV_FILE`，会刷新 `shared/.env`
- 部署根目录仍需先由运维保证 runner 用户可写

说明：

- `shared/storage` 建议在首次部署前就由运维一次性设置好 owner / group / mode
- 如果 PHP-FPM 不是以 `<runner-user>` 运行，至少要保证 runner 用户与 PHP-FPM 用户共享同一个可写 group
- 后续部署不会再尝试递归 `chmod shared/storage`，避免历史文件属主不一致时发布失败

Nginx 配置示例（省略 SSL）：

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /example/dogeow-api/current/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

注意 `$realpath_root` —— 切换 `current` 软链时 PHP-FPM 的 OPcache 会感知到新文件；用 `$document_root` 反而可能命中旧缓存。

---

## 4. GitHub Secrets

仓库 Settings → Secrets and variables → Actions 配置：

| Secret 名 | 值示例 | 说明 |
| --- | --- | --- |
| `DEPLOY_PATH` | `/example/dogeow-api` | 部署根目录（可选，不设用默认） |
| `SUPERVISOR_GROUP` | `laravel-horizon` | Supervisor 里 queue/horizon 所在 group |
| `SUPERVISORCTL_BIN` | `/usr/bin/supervisorctl` | 可选，Supervisor 控制命令的绝对路径 |

说明：

- 当前工作流不需要额外配置仓库地址 Secret
- 如果 `composer install` 还要读取其他私有 GitHub 仓库，再单独配置 `COMPOSER_AUTH` 或细粒度 PAT

---

## 5. 自动部署流程

`.github/workflows/deploy-self-hosted.yml` 已经配好：

1. 推送 `main` 或手动点 "Run workflow" 触发
2. Runner checkout 仓库（拿 `deploy.php` 和工作流定义）
3. 执行 `scripts/ensure-deployer.sh deploy production -v`，自动下载或复用 Deployer
4. Deployer 依次：同步当前工作区到 release → composer install → shared link → migrate → optimize → 切换 `current` → `queue:restart` → `supervisorctl restart` → `notify:deploy`（需配置 `DEPLOY_NOTIFY_USER_IDS`）

全程 `current` 直到最后一刻才切换，因此 HTTP 请求不中断。

---

## 6. 手动命令

Runner 机器上或任何能 SSH 到它的地方都能执行（需要 `deploy.php` 在当前目录）：

```bash
# 常用命令
scripts/ensure-deployer.sh --version           # 自动下载 / 复用 Deployer
scripts/ensure-deployer.sh deploy production   # 部署
scripts/ensure-deployer.sh rollback production # 回滚到上一个 release
scripts/ensure-deployer.sh deploy:unlock production  # 解锁（上一次非正常中断时）
scripts/ensure-deployer.sh releases production       # 列出历史 release
scripts/ensure-deployer.sh config:current production # 查看 current 指向
```

---

## 7. 回滚

```bash
scripts/ensure-deployer.sh rollback production
```

这会：

- 把 `current` 软链切回上一个 release（瞬时生效）
- 重启 queue worker

数据库迁移不会自动回滚。如果新版本跑了破坏性 migration，需要先手动 `php artisan migrate:rollback` 或准备好向下兼容的迁移后再 rollback。

---

## 8. 故障排查

| 现象 | 排查 |
| --- | --- |
| `Deploy is locked` | 上次跑挂了，执行 `scripts/ensure-deployer.sh deploy:unlock production` |
| `Permission denied` on shared | `shared/` 目录所有者不是 runner 用户；`chown -R` 修好 |
| `sudo: supervisorctl: command not found` | 多见于 sudo 的 `secure_path` 不含真实安装目录；设置 `SUPERVISORCTL_BIN` 为绝对路径，并确保 sudoers 放行同一路径 |
| Queue worker 加载旧代码 | 确认 Supervisor 的 `directory=current`；`sudo -n /实际路径/supervisorctl restart <group>:*` |
| Nginx 返回 404 | `current` 软链失效或指向错，`ls -l /example/dogeow-api/current` 确认 |
| 发布目录代码不是最新 | 确认 `actions/checkout` 是否拉到了目标提交；手动部署时确认当前工作树已先 `git pull` |
| 503 / 500 | `tail -f shared/storage/logs/laravel.log` |

查看本次部署做了什么：

```bash
scripts/ensure-deployer.sh deploy production -vvv
```

---

## 9. 调整 Deployer 行为

改 `deploy.php`：

- `set('keep_releases', N)` — 保留的历史版本数
- `add('shared_files', [...])` — 跨版本持久化文件
- 新增 `task('xxx', fn () => run('...'))` 然后 `after('deploy:symlink', 'xxx')` 注册 hook

Laravel recipe 完整任务清单见 <https://deployer.org/docs/7.x/recipe/laravel>。
