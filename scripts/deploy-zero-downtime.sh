#!/bin/bash
# Laravel 零停机部署脚本：自动创建 releases/shared、构建、切换 current、supervisorctl restart。
# 用法：APP_ROOT=/path/to/app SUPERVISOR_GROUP=组名 ./scripts/deploy-zero-downtime.sh
set -euo pipefail

# ----------- 常量与校验 -----------
if [ -z "${APP_ROOT:-}" ]; then
  echo "错误：请设置环境变量 APP_ROOT（Laravel 根目录），例: APP_ROOT=/var/www/example $0"
  exit 1
fi
if [ -z "${SUPERVISOR_GROUP:-}" ]; then
  echo "错误：请设置环境变量 SUPERVISOR_GROUP（Supervisor group 名），例: SUPERVISOR_GROUP=xxx $0"
  exit 1
fi

RELEASES_DIR="${APP_ROOT}/releases"
SHARED_DIR="${APP_ROOT}/shared"
CURRENT_LINK="${APP_ROOT}/current"
KEEP_RELEASES=5

cd "$APP_ROOT"

# ----------- 工具函数 -----------
create_shared_dirs() {
  mkdir -p \
    "$SHARED_DIR/storage/app" \
    "$SHARED_DIR/storage/framework/cache/data" \
    "$SHARED_DIR/storage/framework/sessions" \
    "$SHARED_DIR/storage/framework/views" \
    "$SHARED_DIR/storage/logs" \
    "$SHARED_DIR/bootstrap/cache"
  chmod -R 775 "$SHARED_DIR" 2>/dev/null || true
}

copy_source_to_release() {
  local src="$1"
  local dest="$2"
  rsync -a --exclude='vendor' --exclude='storage' --exclude='bootstrap/cache' --exclude='.env' \
    --exclude='releases' --exclude='current' --exclude='shared' --exclude='.git' \
    "$src/" "$dest/"
}

install_release() {
  local release_dir="$1"
  cd "$release_dir"
  mkdir -p logs
  rm -rf storage bootstrap/cache public/storage 2>/dev/null || true
  ln -sfn ../../shared/storage storage
  ln -sfn ../../../shared/bootstrap/cache bootstrap/cache
  ln -sfn ../../../shared/storage/app/public public/storage
  [ -L .env ] || [ -f .env ] || ln -sfn ../../.env .env
  composer install --no-dev --optimize-autoloader --no-interaction
  php artisan migrate --force
  php artisan optimize
  php artisan queue:restart 2>/dev/null || true
  cd - >/dev/null
}

cleanup_old_releases() {
  (cd "$RELEASES_DIR" && ls -1t | tail -n +$((KEEP_RELEASES + 1)) | while read -r d; do
    [ -n "$d" ] && rm -rf "$RELEASES_DIR/$d"
  done) || true
}

restart_supervisor() {
  local group="$1"
  echo "[deploy] 重启 Supervisor 组: $group"
  sudo supervisorctl restart "${group}:*"
  sudo supervisorctl status "${group}:*"
}

# ----------- 主流程 -----------
if [ -L "$CURRENT_LINK" ] || [ -d "$CURRENT_LINK" ]; then
  echo "[deploy] 使用发布目录模式（零停机）"
  [ -d "$RELEASES_DIR" ] || mkdir -p "$RELEASES_DIR"
  create_shared_dirs

  NEW_RELEASE="${RELEASES_DIR}/$(date +%Y%m%d%H%M%S)"
  echo "[deploy] 创建新发布目录: $NEW_RELEASE"
  mkdir -p "$NEW_RELEASE"
  # APP_ROOT 工作树由外部部署流程先更新到最新提交；release 必须始终从这里复制，
  # 不能从上一个 release 递推，否则被删除或修改的文件会一直残留。
  copy_source_to_release "." "$NEW_RELEASE"
  install_release "$NEW_RELEASE"

  ln -sfn "$NEW_RELEASE" "$CURRENT_LINK"
  echo "[deploy] 已切换 current -> $NEW_RELEASE"

  cleanup_old_releases
  restart_supervisor "$SUPERVISOR_GROUP"
  echo "[deploy] 完成（零停机）"
  exit 0
fi

# ----------- 首次部署 -----------
echo "[deploy] 首次部署：创建 releases + current"
mkdir -p "$RELEASES_DIR"
create_shared_dirs

NEW_RELEASE="${RELEASES_DIR}/$(date +%Y%m%d%H%M%S)"
mkdir -p "$NEW_RELEASE"
copy_source_to_release "." "$NEW_RELEASE"
install_release "$NEW_RELEASE"

ln -sfn "$NEW_RELEASE" "$CURRENT_LINK"
echo "[deploy] 已设置 current -> $NEW_RELEASE"

if sudo supervisorctl status "${SUPERVISOR_GROUP}:*" >/dev/null 2>&1; then
  restart_supervisor "$SUPERVISOR_GROUP"
else
  echo "[deploy] 请配置 Supervisor（directory=$CURRENT_LINK）后执行: sudo supervisorctl start ${SUPERVISOR_GROUP}:*"
fi
echo "[deploy] 完成（首次）"
