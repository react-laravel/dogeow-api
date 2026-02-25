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

# Mock supervisorctl
mock_supervisorctl() {
    echo "#!/bin/bash"
    echo "echo \"[mock supervisorctl] \$@\""
}
mkdir -p "$TEST_APP_ROOT"
mkdir -p "$TEST_APP_ROOT/scripts"
mock_supervisorctl > "$TEST_APP_ROOT/scripts/supervisorctl"
chmod +x "$TEST_APP_ROOT/scripts/supervisorctl"
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

# Mock git
echo "#!/bin/bash" > "$TEST_APP_ROOT/git"
echo "echo '[mock git] \$@'" >> "$TEST_APP_ROOT/git"
chmod +x "$TEST_APP_ROOT/git"

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

# Test 4: Zero downtime deploy (current exists)
echo "Test 4: Zero downtime deploy"
ln -sfn "$TEST_APP_ROOT/releases/$(ls "$TEST_APP_ROOT/releases" | head -n1)" "$TEST_APP_ROOT/current"
APP_ROOT="$TEST_APP_ROOT" SUPERVISOR_GROUP="$SUPERVISOR_GROUP" bash "$TEST_APP_ROOT/deploy-zero-downtime.sh" | grep -q "零停机" && echo "PASS" || echo "FAIL"

# Test 5: Shared dirs created
echo "Test 5: Shared dirs created"
for d in storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
    [ -d "$TEST_APP_ROOT/shared/$d" ] && echo "$d: PASS" || echo "$d: FAIL"
done

echo "All tests complete."