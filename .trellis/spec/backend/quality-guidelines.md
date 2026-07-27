# 质量规范

## PHP 与 Laravel 风格

- 目标版本为 PHP 8.4、Laravel 13。所有方法和函数声明显式返回类型，参数使用准确类型。
- 依赖注入使用 PHP 8 构造器属性提升和 `readonly`；无参数空构造器不保留。
- 控制结构始终使用花括号。数组形状不明显时补充 PHPDoc，注释解释“为什么”或业务约束，
  不复述代码。
- 新模型关系声明具体 Relation 返回类型；新 Request 的 `rules()`、`messages()` 等声明数组
  泛型 PHPDoc。
- 单文件不超过 600 行。超出时按服务职责拆分，保持已有路由、控制器动作和公共服务入口兼容。
- 可以改进注释，但不删除已有注释；不得恢复或覆盖用户已有的无关改动。

## 测试

- 使用 PHPUnit 13 类测试，不引入 Pest。新测试优先通过
  `php artisan make:test --phpunit --no-interaction` 创建。
- API 行为放在 `tests/Feature/`，覆盖认证、授权、验证、状态码、响应结构和数据库副作用。
- Service、Model、Request、Policy、Job 的独立行为放在 `tests/Unit/`。
- 数据使用 Factory；数据库测试使用 `RefreshDatabase` 或项目 `Tests\TestCase` 提供的
  `LazilyRefreshDatabase`。认证 API 使用 `Sanctum::actingAs()`。
- 文件、队列、HTTP、事件、缓存和存储使用 Laravel fake；服务依赖可使用 Mockery。不要访问
  真实第三方服务。
- 至少覆盖 happy path、失败路径和重要边界，尤其是跨用户资源、目录穿越、重复提交、
  N+1 和任务重试。

参考：

- `tests/Feature/Controllers/Thing/ItemControllerTest.php`：租户归属和上传路径边界。
- `tests/Feature/Controllers/SsoControllerTest.php`：Sanctum、Redis mock、PKCE 与一次性票据。
- `tests/Unit/Jobs/TriggerKnowledgeIndexBuildJobTest.php`：HTTP/Event/Log fake。
- `tests/Unit/Models/Thing/ItemTest.php`：预加载后不产生额外查询。

## 验证命令

PHP 变更完成后按影响范围运行：

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Controllers/Thing/ItemControllerTest.php
php artisan test --compact --filter=test_name
```

优先运行最小相关测试；高风险或跨模块变更再运行 `php artisan test --compact`。CI 还会对变更的
PHP 文件运行 Pint，并执行覆盖率检查；`scripts/check-coverage.php` 默认阈值为 80%。

仅修改 Markdown/Trellis 规范时，不需要运行 PHP 测试，但必须检查链接、占位符和 Git diff。

## 禁止模式

- 在控制器内联验证、信任客户端 `user_id`、绕过 Policy/租户过滤。
- 普通查询使用原始 SQL/`DB::table()`，或循环中触发 N+1。
- 在配置文件之外调用 `env()`。
- 新增数据库外键。
- 吞掉异常、向生产响应泄漏 trace/secret，或记录认证凭据。
- 未经批准更换依赖、创建新的基础架构目录、删除测试或改变已有 API 契约。
- 为已有测试覆盖的行为额外创建 tinker/一次性验证脚本。

## 审查清单

- 输入是否通过 Form Request，关联 ID 是否按当前用户/父资源限定。
- 公开/认证/管理员路由边界是否正确，固定路由是否在参数路由之前。
- 列表是否预加载、分页/限量，写入是否需要事务或 Job。
- 响应结构和状态码是否保持兼容。
- 是否补齐相关 Factory/测试，测试是否真的覆盖失败和越权路径。
- PHP 文件是否通过 Pint，注释和类型是否与相邻文件一致。
