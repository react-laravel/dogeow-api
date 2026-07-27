# 目录与分层

## 核心结构

```text
app/
├── Http/
│   ├── Controllers/Api/   # HTTP 编排，按 Note、Thing、Word 等业务分组
│   ├── Requests/          # Form Request，按业务分组
│   ├── Resources/         # ApiResponse 与 Eloquent JsonResource
│   └── Middleware/        # 认证补充、管理员、幂等等横切边界
├── Models/                # Eloquent 模型，复杂业务按命名空间分组
├── Policies/              # 模型授权
├── Services/              # 可复用业务逻辑及外部系统适配
├── Jobs/                  # 耗时或可重试副作用
├── Events/、Listeners/    # 广播和通知事件处理
├── Support/、Utils/       # 无业务状态的底层支持与小型工具
└── Console/Commands/      # Artisan 命令
routes/api/                # 按业务拆分的 API 路由
database/                  # migrations、factories、seeders
tests/Feature、tests/Unit  # PHPUnit 功能测试和单元/组件测试
```

Laravel 13 的中间件、路由入口和异常集成集中在 `bootstrap/app.php`，不要创建旧式
`app/Http/Kernel.php` 或 `app/Console/Kernel.php`。

## 分层职责

- `routes/api.php` 只聚合公开路由、广播认证路由和 `auth:sanctum` 模块；业务路由写入
  `routes/api/<module>.php`。参考 `routes/api/public.php`、`routes/api/item.php`。
- 控制器负责请求编排、授权、事务边界和响应，不承载可复用的复杂算法。参考
  `app/Http/Controllers/Api/Thing/ItemController.php` 调用
  `app/Services/Thing/ItemService.php`。
- 输入规则放在 `app/Http/Requests/<Module>/`；不要在控制器中调用 `$request->validate()`。
- 复杂的文件操作、缓存、搜索或领域算法放入 `app/Services/<Module>/`。服务通过构造器注入，
  不从容器全局解析自身依赖。
- 耗时、可重试或不应阻塞 HTTP 的行为使用 `ShouldQueue` Job。参考
  `ProcessUploadedImageJob` 和 `TriggerKnowledgeIndexBuildJob`。
- 数据访问和关系定义留在 Eloquent 模型；跨多个模型、文件或外部系统的流程放在服务层。
- 模型资源输出使用 `app/Http/Resources/`；通用响应封装使用 `ApiResponse`。

## 业务组织与命名

- 类、目录和命名空间使用 StudlyCase；方法和变量使用 camelCase；表和列使用 snake_case。
- 同一业务的 Controller、Request、Model、Policy、Service 和测试保持相同模块命名，例如
  `Thing/Item`、`Note/Note`、`Word/Book`。
- REST 控制器优先使用 `index/store/show/update/destroy`；非资源动作使用清楚的动词名称，
  例如 `searchSuggestions`、`batchAddRelations`。
- 新增基础目录或依赖前需要明确批准。单文件超过 600 行时按职责拆分，但保留原公共入口。

## 放置判断

- 只与 HTTP 输入/状态码有关：Controller、Request 或 Resource。
- 可被控制器、Job、Command 重用的业务行为：Service。
- 后台执行、重试和失败回调：Job。
- 针对模型实例的访问判断：Policy。
- 跨业务但无状态的小工具：`Support/` 或 `Utils/`；先确认不存在可复用实现。

不要把新的业务类堆在 `app/` 根目录，也不要为了一个简单动作创建全新的架构层。
