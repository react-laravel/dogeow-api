#!/usr/bin/env sh
# Husky/Git GUI 启动时 PATH 很精简，Homebrew PHP 常常不在其中。

if command -v php >/dev/null 2>&1; then
  return 0 2>/dev/null || exit 0
fi

for dir in \
  /opt/homebrew/opt/php@8.4/bin \
  /opt/homebrew/opt/php/bin \
  /usr/local/opt/php@8.4/bin \
  /usr/local/opt/php/bin \
  /opt/homebrew/bin \
  /usr/local/bin
do
  if [ -x "$dir/php" ]; then
    PATH="$dir:$PATH"
    export PATH
    return 0 2>/dev/null || exit 0
  fi
done

echo "husky: 找不到 php。请安装 PHP（如 brew install php）或在 shell 配置里加入 PATH。"
return 1 2>/dev/null || exit 1
