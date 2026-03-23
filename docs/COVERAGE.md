# PHP 代码覆盖率检查

本项目要求 **100% 的代码覆盖率**，确保所有代码都经过测试。

## 确保已安装 Xdebug 扩展

```bash
# 检查 Xdebug 是否已安装
php -m | grep xdebug
```

## 快速开始

### 1. 运行测试并生成覆盖率报告

```bash
# 运行所有测试并生成覆盖率报告
composer run test:coverage
```

### 2. 检查覆盖率是否达到 100%

```bash
# 检查覆盖率要求
composer run test:coverage-check
```

### 3. 一键运行测试和检查

```bash
# 运行测试并检查覆盖率
composer run test:coverage-full
```

## 覆盖率报告

运行测试后，覆盖率报告会生成在以下位置：

- **HTML报告**：`coverage/html/` - 可在浏览器中查看详细的覆盖率信息
- **Clover XML**：`coverage/clover.xml` - 用于CI/CD和代码质量工具
- **文本报告**：`coverage/coverage.txt` - 简单的文本格式报告

## 查看覆盖率报告

```bash
# 在浏览器中打开 HTML 报告
open coverage/html/index.html
```

## CI/CD 集成

项目已配置 GitHub Actions 来自动检查覆盖率：

- 每次推送到 `main` 或 `develop` 分支时会自动运行
- 每次创建 Pull Request 时会自动运行
- 如果覆盖率低于 100%，CI 会失败

## 覆盖率检查脚本

`scripts/check-coverage.php` 脚本会：

1. 检查覆盖率文件是否存在
2. 解析覆盖率数据
3. 计算总体覆盖率
4. 显示详细的覆盖率信息
5. 列出未完全覆盖的文件
6. 如果覆盖率低于 100%，脚本会失败

## 排除的文件

以下目录和文件被排除在覆盖率检查之外：

- `app/Console/` - 控制台命令
- `app/Exceptions/` - 异常处理
- `app/Http/Middleware/` - 中间件
- `app/Providers/` - 服务提供者

## 提高覆盖率

如果覆盖率未达到 100%：

1. 查看覆盖率报告，找出未覆盖的代码
2. 为未覆盖的代码编写测试
3. 重新运行测试和检查

## 常用命令

```bash
# 运行所有测试
composer run test

# 只运行单元测试
vendor/bin/phpunit --testsuite=Unit

# 只运行功能测试
vendor/bin/phpunit --testsuite=Feature
```

## 覆盖率改进历史

### 2026 年 3 月 - Rounds 16-20

系统性地提升了测试覆盖率，重点关注 Controllers 和 Services 层。

#### Round 16: InventoryController
- **文件**：`app/Http/Controllers/Api/Game/InventoryController.php`
- **改进**：96.1% (73/76) → **100%** (76/76)
- **新增测试**：3个测试方法
  - `test_get_quality_name_returns_magic_for_magic`
  - `test_get_quality_name_returns_legendary_for_legendary`
  - `test_get_quality_name_returns_mythic_for_mythic`
- **覆盖内容**：`getQualityName()` 私有方法中的品质翻译 match 语句
- **测试文件**：`tests/Unit/Controllers/Game/InventoryControllerUnitTest.php`

#### Round 17: ImageUploadService
- **文件**：`app/Services/File/ImageUploadService.php`
- **改进**：96.1% (73/76) → **98.7%** (75/76)
- **新增测试**：2个测试方法
  - `test_process_uploaded_images_creates_directory_if_not_exists`
  - `test_process_image_paths_creates_directory_if_not_exists`
- **覆盖内容**：目录不存在时的 mkdir() 调用场景
- **测试文件**：`tests/Unit/Services/ImageUploadServiceTest.php`
- **未覆盖**：1行（Line 117: rename() 失败时的 Log::error）

#### Round 18: GameInventoryService
- **文件**：`app/Services/Game/GameInventoryService.php`
- **改进**：96.5% (303/314) → **96.8%** (304/314)
- **新增测试**：1个测试方法
  - `test_equip_item_creates_equipment_slot_if_not_exists`
- **覆盖内容**：首次创建装备槽位的场景（`getOrCreateEquipmentSlot`）
- **测试文件**：`tests/Unit/Services/Game/GameInventoryServiceTest.php`
- **未覆盖**：10行（类型转换、防御性检查、数据库异常）

#### Round 19: ItemSearchService
- **文件**：`app/Services/Thing/ItemSearchService.php`
- **改进**：94.8% (55/58) → **98.3%** (57/58)
- **新增测试**：1个测试方法
  - `test_record_search_history_logs_error_on_database_failure`
- **覆盖内容**：数据库插入失败时的异常处理和日志记录
- **测试文件**：`tests/Unit/Services/Thing/ItemSearchServiceTest.php`
- **未覆盖**：1行（Line 77: 低频分支条件）

#### Round 20: 覆盖率分析总结
- **总测试数**：3,201 passing
- **总断言数**：8,768 assertions
- **100% 覆盖文件**：多个 Controllers（AuthController, ClientInfoController, GithubController, LogController）

### 剩余低覆盖率文件分析

以下文件覆盖率 <97%，主要包含难以测试的代码：

1. **FileStorageService** (93.8%)
   - 6 行未覆盖：异常处理中的 catch 块
   - 原因：需要实际文件系统故障才能触发

2. **ChatPaginationService** (94.7%)
   - 7 行未覆盖：数据库异常 + 低频分支
   - 原因：需要特殊数据库状态

3. **ContentFilterService** (94.9%)
   - 14 行未覆盖：复杂的违规检测逻辑
   - 部分改进已完成（Round 15）

4. **CombatRoundProcessor** (95.2%)
   - 19 行未覆盖：复杂的 AOE 计算和技能选择算法
   - 原因：需要极端的游戏状态组合

5. **GameCombatLootService** (96.2%)
   - 8 行未覆盖：装备品质系统的边界情况

### 测试最佳实践

基于这些改进工作的经验总结：

1. **使用 Reflection 测试私有方法**（Round 16）
   ```php
   $reflection = new \ReflectionClass($this->controller);
   $method = $reflection->getMethod('privateMethod');
   $method->setAccessible(true);
   $result = $method->invoke($this->controller, $arg);
   ```

2. **测试目录创建场景**（Round 17）
   - 在测试前确保目录不存在
   - 使用递归删除辅助方法清理
   - 设置正确的文件权限（chmod）

3. **Mock 数据库异常**（Round 19）
   ```php
   DB::shouldReceive('table')->andThrow(new \Exception('Database error'));
   Log::shouldReceive('warning')->once();
   ```

4. **覆盖率验证工作流**
   - 添加测试 → 运行测试 → 生成覆盖率 → 分析未覆盖行 → 迭代改进

### 覆盖率工具命令

```bash
# 生成特定测试的覆盖率
XDEBUG_MODE=coverage php artisan test tests/Unit/Services/MyServiceTest.php --coverage-clover=/tmp/my_coverage.xml

# 使用 Python 分析覆盖率 XML
python3 scripts/analyze_coverage.py /tmp/my_coverage.xml

# 运行完整覆盖率分析
XDEBUG_MODE=coverage php artisan test --coverage-clover=/tmp/full_coverage.xml
```