# 数据库规范

## 运行环境与基本原则

- 生产使用 PostgreSQL 18；`.env.example` 的本地示例使用 SQLite；`phpunit.xml` 当前测试库使用
  MySQL。迁移和查询不得无意绑定到单一驱动。
- 本项目不创建数据库外键。关联列使用 `unsignedBigInteger()` 等普通字段并建立必要索引，
  关系完整性、归属验证和删除清理由应用层维护。
- 普通数据访问优先 Eloquent：`Model::query()`、模型关系、scope 和预加载。不要为简单查询
  引入 `DB::table()` 或原始 SQL。
- `DB` facade 适用于事务、确实复杂的查询和驱动专用维护逻辑，例如
  `PostgresSequenceSynchronizer`；驱动专用代码必须先判断 `DB::getDriverName()`。

## 模型

- 模型按业务放在 `app/Models/<Module>/`，显式声明非默认表名、`$fillable`、casts 和关系。
- 新代码为关系方法声明 `BelongsTo`、`HasMany`、`BelongsToMany` 等返回类型。参考
  `app/Models/Thing/Item.php` 和 `app/Models/Todo/TodoList.php`。
- Laravel 13 新模型优先使用 `protected function casts(): array`；相邻旧模型仍有 `$casts`
  属性，修改旧模型时避免无关的整文件重写。
- 可复用筛选写成 scope 并返回 Builder，例如 `Item::scopeSearch()`；租户范围必须包含
  `user_id`。
- 序列化访问器可能触发查询。列表必须预加载访问器依赖的关系；参考
  `Item::getThumbnailUrlAttribute()` 和其“预加载后零额外查询”测试。

## 查询与一致性

- 所有用户数据查询都要从服务端认证用户推导 `user_id`，不接受客户端指定所属用户。
- 关联 ID 的验证使用带租户/父资源条件的 `Rule::exists()`，再在写入时使用关系方法
  `sync()`、`attach()`、`detach()`。
- 列表使用 `with()` 预加载并设置排序、分页或明确上限。不要在 collection 循环内逐条查询。
- 多模型写入使用 `DB::transaction()`，参考 `ItemController::store/update/destroy`。
- 大批量导入或固定基础数据放在 Seeder；测试数据使用 Factory。新增模型时同步补充有用的
  Factory，必要时再补 Seeder。

## 迁移与命名

- 使用匿名 Migration 类，`up()` / `down()` 显式返回 `void`，通过 `Schema` 修改结构。
- 表和列使用 snake_case。业务表常带模块前缀，如 `thing_items`、`word_books`；pivot 表遵循
  现有命名并显式配置关系表名。
- 关联列虽无外键，也要为高频过滤建立索引。复合索引按实际查询前缀设计，并使用稳定名称，
  例如 `thing_items_user_status_idx`、`user_words_user_book_status_idx`。
- 业务唯一性使用数据库 unique 索引兜底，例如用户标签名、学习记录和关联 pivot。
- 修改已有列时保留原列的 nullable、default、comment 等全部属性；迁移需能在项目支持的
  PostgreSQL、SQLite、MySQL 环境运行。

## 禁止与注意

- 不使用 `foreignId()->constrained()`、`foreign()` 或级联外键。
- 不用未限定 `user_id` 的 ID 查询处理租户资源。
- 不把 `env()` 写在配置文件之外；业务代码读取 `config()`。
- 不以删除测试数据或跳过索引来规避迁移/并发问题。
- 原始 SQL 必须使用绑定参数或安全的标识符处理，并配套驱动判断和测试。
