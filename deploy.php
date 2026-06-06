<?php

/**
 * Deployer 部署配置（GitHub Actions self-hosted runner 使用）
 *
 * 设计要点：
 * - self-hosted runner 就在目标服务器上，host 用 localhost() 走本地 shell，无需 SSH。
 * - 代码来源直接使用当前 Actions checkout 工作区，避免在部署阶段再次 clone 私有仓库。
 * - deploy_path / supervisor_group 通过环境变量注入，避免把机器路径写死进仓库。
 * - Laravel recipe 已覆盖 shared/writable/symlink/migrate/optimize，本文件只补 Supervisor 重启。
 *
 * 本地使用：
 *   DEPLOY_PATH=/example/dogeow-api SUPERVISOR_GROUP=laravel-horizon \
 *     scripts/ensure-deployer.sh deploy production
 *
 * 回滚：
 *   scripts/ensure-deployer.sh rollback production
 */

namespace Deployer;

require 'recipe/laravel.php';

// =====================
// 基本配置
// =====================
set('application', 'dogeow-api');
set('keep_releases', 2);
set('git_tty', false); // CI 环境没有 TTY
set('workspace_root', __DIR__);
// ECS 镜像默认没有 ACL 工具，改用 chmod 处理 writable 目录。
set('writable_mode', 'chmod');
set('writable_recursive', true);
set('writable_chmod_mode', '0775');

// 跨版本共享文件 / 目录（升级不会丢）
add('shared_files', ['.env']);
add('shared_dirs', ['storage']);

// 仅在每次部署时处理 release 内部目录的 writable 权限。
// shared/storage 是跨版本共享目录，权限应在服务器初始化时一次性配置，
// 避免 deploy:writable 递归 chmod 软链目标时因属主不一致而失败。
set('writable_dirs', [
    'bootstrap/cache',
]);

// =====================
// Hosts
// =====================
localhost('production')
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/example/dogeow-api')
    ->set('supervisor_group', getenv('SUPERVISOR_GROUP') ?: 'laravel-horizon')
    ->set('supervisorctl_bin', getenv('SUPERVISORCTL_BIN') ?: 'supervisorctl')
    ->set('remote_user', getenv('DEPLOY_REMOTE_USER') ?: (getenv('USER') ?: (getenv('LOGNAME') ?: 'localhost')));

// =====================
// 自定义任务
// =====================
desc('重启 Supervisor 下的 queue worker / Horizon');
task('supervisor:restart', function () {
    $supervisorctlBin = escapeshellarg((string) get('supervisorctl_bin'));

    run(<<<BASH
SUPERVISORCTL_BIN=$supervisorctlBin

if [ -x "\$SUPERVISORCTL_BIN" ]; then
    SUPERVISORCTL_PATH="\$SUPERVISORCTL_BIN"
else
    SUPERVISORCTL_PATH="\$(command -v "\$SUPERVISORCTL_BIN" || true)"
fi

if [ -z "\$SUPERVISORCTL_PATH" ]; then
    echo "未找到 supervisorctl，请安装 Supervisor 或设置 SUPERVISORCTL_BIN" >&2
    exit 1
fi

sudo -n "\$SUPERVISORCTL_PATH" restart {{supervisor_group}}:*
sudo -n "\$SUPERVISORCTL_PATH" status {{supervisor_group}}:*
BASH
    );
});

desc('从当前工作区同步代码到 release 目录');
task('deploy:update_code', function () {
    $workspaceRoot = rtrim(get('workspace_root'), '/');
    $releasePath = '{{release_path}}';

    run("mkdir -p $releasePath");
    run(
        'rsync -a '
        . "--exclude='.git' "
        . "--exclude='vendor' "
        . "--exclude='storage' "
        . "--exclude='bootstrap/cache' "
        . "--exclude='.env' "
        . "--exclude='releases' "
        . "--exclude='current' "
        . "--exclude='shared' "
        . "$workspaceRoot/ $releasePath/"
    );
});

desc('修复 shared storage 权限，避免 PHP-FPM(www-data) 与部署用户(nginx)轮流写日志/上传文件时 500');
task('deploy:storage_permissions', function () {
    run(<<<'BASH'
bash -lc '
set -euo pipefail

storage_dir="{{deploy_path}}/shared/storage"
bootstrap_cache="{{release_path}}/bootstrap/cache"

[ -d "$storage_dir" ] || exit 0

find "$storage_dir" -type d -exec chmod 2777 {} +
find "$storage_dir" -type f -exec chmod 0666 {} +

if [ -d "$bootstrap_cache" ]; then
  find "$bootstrap_cache" -type d -exec chmod 2775 {} +
  find "$bootstrap_cache" -type f -exec chmod 0664 {} +
fi
'
BASH);
});

// =====================
// Hooks
// =====================
// 部署失败自动解锁，避免下次运行报 "Deploy is locked"
after('deploy:failed', 'deploy:unlock');

// shared/storage 由 PHP-FPM(www-data) 和部署用户(nginx)共同写入，部署后统一修正权限。
after('deploy:shared', 'deploy:storage_permissions');

// current 切换之后再重启 worker：
//   queue:restart 让跑中的 job 做完就退出，Supervisor 拉起新进程时加载新代码。
after('deploy:symlink', 'artisan:queue:restart');
after('artisan:queue:restart', 'supervisor:restart');
