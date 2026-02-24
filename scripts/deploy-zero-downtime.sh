#!/bin/bash
# Laravel 零停机部署：自动创建 releases/shared、构建、切换 current、supervisorctl restart。
# 用法：APP_ROOT=/path/to/app SUPERVISOR_GROUP=组名 ./scripts/deploy-zero-downtime.sh
# 首次运行会自动建目录和首版发布；需事先在 APP_ROOT 放好 .env，Supervisor 的 directory 指向 $APP_ROOT/current。
# 若 current 下 bootstrap/cache 或 storage 报错：cd $APP_ROOT/current && rm -rf bootstrap/cache storage; ln -sfn ../../../shared/bootstrap/cache bootstrap/cache && ln -sfn ../../shared/storage storage
set -euo pipefail

if [ -z "${APP_ROOT:-}" ]; then
  echo "错误：请设置环境变量 APP_ROOT（Laravel 在服务器上的根目录），例: APP_ROOT=/var/www/example $0"
  exit 1
fi
if [ -z "${SUPERVISOR_GROUP:-}" ]; then
  echo "错误：请设置环境变量 SUPERVISOR_GROUP（Supervisor 的 group 名），例: 在 GitHub Secrets 中添加 SUPERVISOR_GROUP $0"
  exit 1
fi
RELEASES_DIR="${APP_ROOT}/releases"
SHARED_DIR="${APP_ROOT}/shared"
CURRENT_LINK="${APP_ROOT}/current"

cd "$APP_ROOT"

_install_release() {
  local release_dir="$1"
  cd "$release_dir"
  mkdir -p logs
  rm -rf storage bootstrap/cache 2>/dev/null || true
  ln -sfn ../../shared/storage storage
  ln -sfn ../../../shared/bootstrap/cache bootstrap/cache
  [ -L .env ] || [ -f .env ] || ln -sfn ../../.env .env
  composer install --no-dev --optimize-autoloader --no-interaction
  php artisan migrate --force
  php artisan optimize
  # 通知 queue:work 处理完当前任务后退出，便于后续 supervisor 重启时少丢任务
  php artisan queue:restart 2>/dev/null || true
  cd - >/dev/null
}

# ---------- 模式一：已有 current，新发布 + 原子切换 ----------
if [ -L "$CURRENT_LINK" ] || [ -d "$CURRENT_LINK" ]; then
  echo "[deploy] 使用发布目录模式（零停机）"
  [ -d "$RELEASES_DIR" ] || mkdir -p "$RELEASES_DIR"
  [ -d "$SHARED_DIR/storage" ] || mkdir -p "$SHARED_DIR/storage/app" "$SHARED_DIR/storage/framework/cache/data" "$SHARED_DIR/storage/framework/sessions" "$SHARED_DIR/storage/framework/views" "$SHARED_DIR/storage/logs"
  [ -d "$SHARED_DIR/bootstrap/cache" ] || mkdir -p "$SHARED_DIR/bootstrap/cache"
  chmod -R 775 "$SHARED_DIR" 2>/dev/null || true

  NEW_RELEASE="${RELEASES_DIR}/$(date +%Y%m%d%H%M%S)"
  CURRENT_RELEASE=""
  if [ -L "$CURRENT_LINK" ]; then
    CURRENT_RELEASE="$(readlink "$CURRENT_LINK")"
    [ "${CURRENT_RELEASE#/}" = "$CURRENT_RELEASE" ] && CURRENT_RELEASE="$APP_ROOT/$CURRENT_RELEASE"
  elif [ -d "$CURRENT_LINK" ]; then
    CURRENT_RELEASE="$CURRENT_LINK"
  fi

  echo "[deploy] 创建新发布目录: $NEW_RELEASE"
  mkdir -p "$NEW_RELEASE"
  rsync -a --exclude='vendor' --exclude='storage' --exclude='bootstrap/cache' --exclude='.env' \
    --exclude='releases' --exclude='current' --exclude='shared' --exclude='.git' \
    "${CURRENT_RELEASE:-.}/" "$NEW_RELEASE/" 2>/dev/null || rsync -a --exclude='vendor' --exclude='storage' --exclude='bootstrap/cache' --exclude='.env' --exclude='releases' --exclude='current' --exclude='shared' --exclude='.git' ./ "$NEW_RELEASE/"

  cd "$NEW_RELEASE"
  git pull
  cd - >/dev/null
  _install_release "$NEW_RELEASE"

  ln -sfn "$NEW_RELEASE" "$CURRENT_LINK"
  echo "[deploy] 已切换 current -> $NEW_RELEASE"

  KEEP=5
  (cd "$RELEASES_DIR" 2>/dev/null && ls -1t | tail -n +$((KEEP + 1)) | while read -r d; do [ -n "$d" ] && rm -rf "$RELEASES_DIR/$d"; done) || true

  echo "[deploy] 重启 Supervisor 组: $SUPERVISOR_GROUP"
  sudo supervisorctl restart "$SUPERVISOR_GROUP"
  sudo supervisorctl status "$SUPERVISOR_GROUP"
  echo "[deploy] 完成（零停机）"
  exit 0
fi

# ---------- 模式二：无 current，首次部署 ----------
echo "[deploy] 首次部署：创建 releases + current"
mkdir -p "$RELEASES_DIR" "$SHARED_DIR/storage/app" "$SHARED_DIR/storage/framework/cache/data" "$SHARED_DIR/storage/framework/sessions" "$SHARED_DIR/storage/framework/views" "$SHARED_DIR/storage/logs" "$SHARED_DIR/bootstrap/cache"
chmod -R 775 "$SHARED_DIR" 2>/dev/null || true
NEW_RELEASE="${RELEASES_DIR}/$(date +%Y%m%d%H%M%S)"
mkdir -p "$NEW_RELEASE"
rsync -a --exclude='vendor' --exclude='storage' --exclude='bootstrap/cache' --exclude='.env' \
  --exclude='releases' --exclude='current' --exclude='shared' --exclude='.git' \
  ./ "$NEW_RELEASE/"

cd "$NEW_RELEASE"
git pull
cd - >/dev/null
_install_release "$NEW_RELEASE"

ln -sfn "$NEW_RELEASE" "$CURRENT_LINK"
echo "[deploy] 已设置 current -> $NEW_RELEASE"
if sudo supervisorctl status "$SUPERVISOR_GROUP" >/dev/null 2>&1; then
  sudo supervisorctl restart "$SUPERVISOR_GROUP"
  sudo supervisorctl status "$SUPERVISOR_GROUP"
else
  echo "[deploy] 请配置 Supervisor（directory=$CURRENT_LINK）后执行: sudo supervisorctl start $SUPERVISOR_GROUP"
fi
echo "[deploy] 完成（首次）"
