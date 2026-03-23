#!/bin/bash
# Tests for deploy-zero-downtime.sh (mocked, non-destructive)
# Run: bash ./scripts/test_deploy-zero-downtime.sh

set -euo pipefail

SCRIPT="./scripts/deploy-zero-downtime.sh"
TEST_APP_ROOT="/tmp/test_deploy_zero_downtime"
SUPERVISOR_GROUP="testgroup"

# Clean up before/after
cleanup() {
    rm -rf "$TEST_APP_ROOT"
}
trap cleanup EXIT

latest_release_path() {
    ls -1dt "$TEST_APP_ROOT"/releases/* 2>/dev/null | head -n1
}

# Mock supervisorctl
mock_supervisorctl() {
    echo "#!/bin/bash"
    echo "echo \"[mock supervisorctl] \$@\""
}
mock_sudo() {
    echo "#!/bin/bash"
    echo 'exec "$@"'
}
mkdir -p "$TEST_APP_ROOT"
mkdir -p "$TEST_APP_ROOT/scripts"
mock_supervisorctl > "$TEST_APP_ROOT/scripts/supervisorctl"
mock_sudo > "$TEST_APP_ROOT/scripts/sudo"
chmod +x "$TEST_APP_ROOT/scripts/supervisorctl"
chmod +x "$TEST_APP_ROOT/scripts/sudo"
export PATH="$TEST_APP_ROOT/scripts:$PATH"

# Create dummy Laravel structure
mkdir -p "$TEST_APP_ROOT/storage/app"
mkdir -p "$TEST_APP_ROOT/bootstrap/cache"
touch "$TEST_APP_ROOT/.env"
touch "$TEST_APP_ROOT/composer.json"
touch "$TEST_APP_ROOT/artisan"
echo "dummy" > "$TEST_APP_ROOT/composer.json"

# Mock composer and php artisan
echo "#!/bin/bash" > "$TEST_APP_ROOT/composer"
echo "echo '[mock composer] \$@'" >> "$TEST_APP_ROOT/composer"
chmod +x "$TEST_APP_ROOT/composer"
echo "#!/bin/bash" > "$TEST_APP_ROOT/php"
echo "echo '[mock php] \$@'" >> "$TEST_APP_ROOT/php"
chmod +x "$TEST_APP_ROOT/php"
export PATH="$TEST_APP_ROOT:$PATH"

echo "v1" > "$TEST_APP_ROOT/version.txt"
echo "legacy" > "$TEST_APP_ROOT/removed-on-second-deploy.txt"

# Copy script to test location
cp "$SCRIPT" "$TEST_APP_ROOT/deploy-zero-downtime.sh"

# Test 1: Missing APP_ROOT
echo "Test 1: Missing APP_ROOT"
if APP_ROOT="" SUPERVISOR_GROUP="$SUPERVISOR_GROUP" bash "$TEST_APP_ROOT/deploy-zero-downtime.sh" 2>&1 | grep -q "请设置环境变量 APP_ROOT"; then
    echo "PASS"
else
    echo "FAIL"
fi

# Test 2: Missing SUPERVISOR_GROUP
echo "Test 2: Missing SUPERVISOR_GROUP"
if APP_ROOT="$TEST_APP_ROOT" SUPERVISOR_GROUP="" bash "$TEST_APP_ROOT/deploy-zero-downtime.sh" 2>&1 | grep -q "请设置环境变量 SUPERVISOR_GROUP"; then
    echo "PASS"
else
    echo "FAIL"
fi

# Test 3: First deploy (no current)
echo "Test 3: First deploy"
APP_ROOT="$TEST_APP_ROOT" SUPERVISOR_GROUP="$SUPERVISOR_GROUP" bash "$TEST_APP_ROOT/deploy-zero-downtime.sh" | grep -q "首次部署" && echo "PASS" || echo "FAIL"
FIRST_RELEASE="$(latest_release_path)"
if [ -f "$FIRST_RELEASE/version.txt" ] && grep -q '^v1$' "$FIRST_RELEASE/version.txt"; then
    echo "first release version copied: PASS"
else
    echo "first release version copied: FAIL"
fi
if [ -f "$FIRST_RELEASE/removed-on-second-deploy.txt" ]; then
    echo "first release legacy file exists: PASS"
else
    echo "first release legacy file exists: FAIL"
fi

# Test 4: Zero downtime deploy (current exists)
echo "Test 4: Zero downtime deploy"
echo "v2" > "$TEST_APP_ROOT/version.txt"
rm -f "$TEST_APP_ROOT/removed-on-second-deploy.txt"
APP_ROOT="$TEST_APP_ROOT" SUPERVISOR_GROUP="$SUPERVISOR_GROUP" bash "$TEST_APP_ROOT/deploy-zero-downtime.sh" | grep -q "零停机" && echo "PASS" || echo "FAIL"
SECOND_RELEASE="$(latest_release_path)"
if [ -f "$SECOND_RELEASE/version.txt" ] && grep -q '^v2$' "$SECOND_RELEASE/version.txt"; then
    echo "second release refreshed from app root: PASS"
else
    echo "second release refreshed from app root: FAIL"
fi
if [ ! -e "$SECOND_RELEASE/removed-on-second-deploy.txt" ]; then
    echo "second release does not keep removed files: PASS"
else
    echo "second release does not keep removed files: FAIL"
fi

# Test 5: Shared dirs created
echo "Test 5: Shared dirs created"
for d in storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
    [ -d "$TEST_APP_ROOT/shared/$d" ] && echo "$d: PASS" || echo "$d: FAIL"
done

echo "All tests complete."