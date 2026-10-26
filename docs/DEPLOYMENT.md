# DogeOW 主API发布到新服务器C

当前发布目标为C（120.26.21.64），仅新增主API所需运行依赖，不安装Codex或模型；Octane按1个worker、1个task worker减少内存。使用现有A自托管GitHub runner构建，再通过专用受限SSH账号上传运行包。旧Deployer localhost入口已退出；不要再从A执行旧的deploy或rollback命令。

首次启用由管理员完成以下受限权限配置与验收；源码发布只使用本仓库专用密钥及明确列出的三项服务重启权限。

## CI行为

push main自动发布；workflow_dispatch默认validate_only=true，只验证C当前运行的API，不上传或启用工作区的新源码。需要发布新版本时显式选false。

构建固定使用PHP8.5、现有composer.lock与composer install --no-dev --no-scripts --prefer-dist。不会composer update、编译前端、运行schema migration、清空Redis/session或调用notify:deploy。运行包只含app/bootstrap/config/database/lang/public/resources/routes/vendor与必要根文件，排除.env、storage、bootstrap/cache、调试资产和认证文件。C不构建、不下载依赖。

GitHub加密Secrets：
- C_API_DEPLOY_SSH_KEY：本API专用SSH私钥，不复用其他站点密钥。
- C_API_DEPLOY_KNOWN_HOSTS：经管理员核验的C主机公钥。

构建归档及SSH临时文件在任务结束时清理。运行.env、数据库口令、APP_KEY、VAPID和SSO凭据只保留在C的受保护文件中，不进入仓库或运行包。

## C权限与目录边界

SSH账号dogeow-api-deploy的公钥限定来源A的公网115.29.235.44及私网172.16.65.147，并强制执行root拥有的固定网关。仅接受三个形式：
- receive加40位SHA：stdin接收压缩包。
- publish加40位SHA：校验和发布该包。
- validate加40位SHA：验证当前或初始155版本。

没有通用SSH shell。单包上限200MiB，展开后上限600MiB；只保留一个待处理上传。收包预留最大上传空间及512MiB余量，解包按真实展开量再次检查至少512MiB剩余，空间不足在切换前拒绝。解包拒绝路径穿越、符号链接、硬链接、设备、重复成员、未知路径和错误hash。

目录设置：
- /var/www/dogeow-api：root:root 0755。
- /var/www/dogeow-api/current：root预建固定链接，目标releases/current。
- /var/www/dogeow-api/releases：root:dogeow-api-deploy 2775；publisher只更换其内部current链接。
- /var/www/dogeow-api/shared：root:root 0755；不允许CI替换其目录。
- shared/.env：root:dogeow-api 0640，是受保护普通文件。
- shared/storage：只由运行用户管理；部署账号不加入app组，不通过程序包替换用户数据。
- 每个release的bootstrap/cache为空、归部署用户、0755，不存放秘密。私有缓存固定在/var/lib/dogeow-api/cache，仅运行用户访问（0700）；部署用户不加入app组。
- /var/lib/dogeow-api-deploy：root:dogeow-api-deploy 0750；incoming子目录归deploy用户0700。
- publish.lock：管理员预创建为dogeow-api-deploy:dogeow-api-deploy 0600，避免在root保护的家目录中要求宽写权限。
- .ssh目录及authorized_keys由root拥有，部署用户不能改写密钥限制。

运行用户dogeow-api不能写程序release；程序目录归dogeow-api-deploy。初始化155的程序目录和普通文件也应如此。155已有的秘密缓存由运行用户迁到私有缓存目录；仅空的代码cache目录交给部署用户。CI不创建.env链接，不读取或更改秘密缓存、受保护.env及用户storage。服务侧负责加载私有环境。

唯一sudo权限为：
    /usr/bin/systemctl restart dogeow-api-octane.service
    /usr/bin/systemctl restart dogeow-api-queue.service
    /usr/bin/systemctl restart dogeow-api-reverb.service

Nginx、systemd unit、sudoers及网关/publisher均由root安装，只读给部署账号。CI不能编辑这些文件，也不能重启其他服务或运行其他root命令。应用代码仍以主API本身的权限访问本业务数据库、文件和必要凭据。

## 稳定路径、队列与第一版清理

三个服务及其runtime准备器必须设置：
    LARAVEL_STORAGE_PATH=/var/www/dogeow-api/shared/storage

Laravel13会在读取storage_path时优先使用该环境值，避免新图片任务再次序列化临时release路径。服务同时把APP_CONFIG_CACHE、APP_ROUTES_CACHE、APP_EVENTS_CACHE、APP_PACKAGES_CACHE、APP_SERVICES_CACHE固定到app独占私有缓存目录，避免秘密写回程序目录。Redis队列retry_after要求至少900秒，高于worker的700秒超时；C需设置REDIS_QUEUE_RETRY_AFTER=900。服务停止超时必须足以让当前job正常退出。

初迁保留releases/155，以兼容迁移窗口以前已保存的绝对图片任务。管理员在最终冻结阶段确认main ready/reserved/delayed队列均为空，才创建：
    /var/lib/dogeow-api/legacy-release-retired

该root拥有、不可被部署用户写的普通文件内容为155。首次SHA发布发现没有此标记会中止，不删除155。

成功发布流程为：候选包hash校验 → 切内部current → 精确重启三个服务 → runtime启动前准备器检查schema并以app用户生成私有缓存 → HTTPS、CORS、SQL公开只读接口、WebSocket验证 → 删除旧程序release。publisher不执行PHP/Artisan。失败时在未清旧前恢复旧current并重启三个服务，Octane准备器为原版重新生成私有缓存。数据库从未自动迁移，shared数据与.env保持原位，成功只留一个程序版本。

## 数据库schema由管理员维护

本版CI没有run_migrations输入，也不会执行migrate。Octane启动前的root安装准备器以runtime app用户读取migrations表与当前源码的migration文件名；发现pending拒绝服务启动，publisher恢复原程序并重启。publisher本身不能读取数据库凭据或调用数据库CLI。不能把“迁移服务器”当作顺便升级业务schema的授权。

有新schema时，管理员应先审核具体migration和数据影响，在维护窗口独立执行：
1. 阻止业务写入并等待主API队列当前job完成；停止三个服务，保留数据库恢复点。
2. 在C准备已审核SHA的维护代码目录，使用相同受保护.env与稳定shared/storage，完成平台要求检查。
3. 以dogeow-api运行用户执行该维护目录的PHP8.5 artisan migrate --force --no-interaction；不要用root执行未审查业务代码。
4. 核对schema与数据，再重试已审核SHA的CI发布，恢复并验证写入。

程序回滚不自动回滚SQL schema；不兼容或不可逆migration必须由管理员给出专门恢复步骤。CI发布权限不包括通用root数据库管理。

## 验证与日常检查

runtime服务准备器负责SQL/Redis连通、所有源码migration均已应用、queue retry_after不少于900与稳定storage路径。它以app身份执行，不自动迁schema。

publisher只做以下黑盒只读验证：
- 本机经有效TLS验证的next-api /up为200。
- /api/user匿名401 JSON及next.dogeow.com CORS。
- /api/nav/categories只读SQL接口为200 JSON数组。
- ws.dogeow.com升级为101，并收到Reverb connection_established事件。

publisher只从root拥有的/etc/dogeow-api/publish-health.json读取公开前端ws_app_key，拒绝文件中任何其他字段；此文件不含APP_KEY、Reverb secret或其他凭据。秘密cache由runtime准备器生成到app私有目录，CI不能读、写或管理它，不清空共享Redis或会话。验证不会使用真实用户账号执行业务写操作、发送站内通知或WebPush。

在C读取状态：
    systemctl status dogeow-api-octane dogeow-api-queue dogeow-api-reverb
    journalctl -u dogeow-api-octane -u dogeow-api-queue -u dogeow-api-reverb -n 80 --no-pager

首次启用前需验证网关拒绝通用命令、主机公钥和来源白名单正确、上传包不含秘密、健康失败能够回退，以及三个精确重启命令的sudoers语法。当前本地测试是文件/网关边界与受控失败模拟；真实C集成和CI仍需在授权后验收。

A runner是当前过渡构建点。迁走runner时必须同时调整专用公钥来源限制，并验证B/C管理和构建不再依赖A。
