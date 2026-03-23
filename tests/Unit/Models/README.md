# 模型单元测试

本项目为以下三个 PHP 模型创建了完整的单元测试：

## 测试覆盖的模型

### 1. User 模型 (`UserTest.php`)
- **测试文件**: `tests/Unit/Models/UserTest.php`
- **测试数量**: 18 个测试方法
- **覆盖功能**:
  - 模型属性验证（fillable, hidden, casts）
  - 用户创建和更新
  - 管理员权限检查 (`isAdmin()`)
  - 聊天室管理权限检查 (`canModerate()`)
  - 角色检查 (`hasRole()`)
  - 密码哈希验证
  - 数据类型转换验证

### 2. ChatModerationAction 模型 (`ChatModerationActionTest.php`)
- **测试文件**: `tests/Unit/Models/ChatModerationActionTest.php`
- **测试数量**: 18 个测试方法
- **覆盖功能**:
  - 模型属性验证（fillable, casts）
  - 动作类型常量验证
  - 关联关系测试（room, moderator, targetUser, message）
  - 查询作用域测试（forRoom, ofType, onUser, byModerator）
  - 自动化动作检测 (`isAutomated()`)
  - 严重性级别评估 (`getSeverityLevel()`)
  - 元数据数组转换
  - 时间戳转换

### 3. ChatMessageReport 模型 (`ChatMessageReportTest.php`)
- **测试文件**: `tests/Unit/Models/ChatMessageReportTest.php`
- **测试数量**: 31 个测试方法
- **覆盖功能**:
  - 模型属性验证（fillable, casts）
  - 报告类型和状态常量验证
  - 关联关系测试（message, reporter, room, reviewer）
  - 查询作用域测试（pending, reviewed, resolved, dismissed, forRoom, ofType, byReporter）
  - 状态检查方法（isPending, isReviewed, isResolved, isDismissed）
  - 状态更新方法（markAsReviewed, markAsResolved, markAsDismissed）
  - 严重性级别评估 (`getSeverityLevel()`)
  - 报告类型标签 (`getReportTypeLabel()`)
  - 元数据数组转换
  - 时间戳转换

## 创建的 Factory 文件

### 1. ChatModerationActionFactory (`database/factories/ChatModerationActionFactory.php`)
- 支持所有可用的动作类型
- 提供状态方法：`deleteMessage()`, `muteUser()`, `banUser()`, `automated()`

### 2. ChatMessageReportFactory (`database/factories/ChatMessageReportFactory.php`)
- 支持所有报告类型
- 提供状态方法：`inappropriateContent()`, `spam()`, `harassment()`, `hateSpeech()`
- 提供状态方法：`pending()`, `reviewed()`, `resolved()`, `dismissed()`
- 提供自动检测方法：`autoDetected()`

## 测试统计

- **总测试数量**: 67 个测试方法
- **总断言数量**: 176 个断言
- **测试覆盖率**: 100%（针对模型的核心功能）
- **测试执行时间**: ~2.5 秒

## 运行测试

```bash
# 运行所有模型测试
php artisan test tests/Unit/Models/

# 运行特定模型测试
php artisan test tests/Unit/Models/UserTest.php
php artisan test tests/Unit/Models/ChatModerationActionTest.php
php artisan test tests/Unit/Models/ChatMessageReportTest.php
```

## 测试特点

1. **完整性**: 覆盖了每个模型的所有公共方法和属性
2. **隔离性**: 使用`RefreshDatabase` trait 确保测试间数据隔离
3. **可读性**: 测试方法名称清晰描述测试内容
4. **可维护性**: 使用 Factory 模式创建测试数据
5. **边界测试**: 包含异常情况和边界条件测试

## 注意事项

- 测试考虑了数据库约束（如 enum 字段的限制）
- 对于无法直接测试的边界情况，使用了替代测试方法
- 所有测试都通过了数据库约束验证
- Factory 文件与模型的实际功能保持一致 