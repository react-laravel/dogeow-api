<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost 指导原则

Laravel Boost 指导原则由 Laravel 维护者根据本应用专门定制，应严格遵循以确保获得最佳的 Laravel 开发体验。

## 基础上下文

本应用是一个 Laravel 应用，其主要 Laravel 生态包和版本如下。你是所有相关包的专家，必须遵守这些特定包和版本。

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- laravel/scout (SCOUT) - v10
- laravel/socialite (SOCIALITE) - v5
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- laravel-echo (ECHO) - v2
- tailwindcss (TAILWINDCSS) - v4

## 技能激活

本项目有领域特定的技能可用。每当你在某个领域工作时，必须激活相关技能——不要等到遇到困难才用。

- `tailwindcss-development` — 当用户消息中包含任何形式的 'tailwind' 时始终调用。也适用于以下场景：构建响应式网格布局（多列卡片网格、产品网格）、flex/grid 页面结构（带侧边栏的仪表板、固定顶栏、移动端切换导航）、样式化 UI 组件（卡片、表格、导航栏、定价区域、表单、输入框、徽章）、添加深色模式变体、修复间距或排版问题，以及 Tailwind v3/v4 相关工作。核心用例：在 HTML 模板（Blade、JSX、Vue）中编写或修复 Tailwind 工具类。以下情况跳过：后端 PHP 逻辑、数据库查询、API 路由、不含 HTML/CSS 组件的 JavaScript、CSS 文件审计、构建工具配置、原生 CSS。

## 约定

- 必须遵循本应用使用的所有现有代码约定。创建或编辑文件时，查看同级文件以获取正确的结构、方法和命名。
- 使用描述性的变量和方法名。例如，使用 `isRegisteredForDiscounts`，而不是 `discount()`。
- 在编写新组件之前，先检查是否有可复用的现有组件。

## 验证脚本

- 当测试已覆盖某项功能并证明其正常工作时，不要创建验证脚本或 tinker。单元测试和功能测试更为重要。

## 应用结构与架构

- 遵循现有目录结构；未经批准不要创建新的基础文件夹。
- 未经批准不要更改应用的依赖项。

## 前端打包

- 如果用户在 UI 中看不到前端变更，可能需要运行 `npm run build`、`npm run dev` 或 `composer run dev`。请询问用户。

## 文档文件

- 只有在用户明确要求时，才能创建文档文件。

## 回复

- 保持解释简洁 — 专注于重要内容而非解释显而易见的细节。

=== boost rules ===

# Laravel Boost

- Laravel Boost 是一个 MCP 服务器，配备了专为本应用设计的强大工具。请使用它们。

## Artisan 命令

- 直接通过命令行运行 Artisan 命令（例如 `php artisan route:list`、`php artisan tinker --execute "..."`）。
- 使用 `php artisan list` 发现可用命令，使用 `php artisan [command] --help` 检查参数。

## URL

- 与用户分享项目 URL 时，应使用 `get-absolute-url` 工具以确保使用正确的 scheme、域名/IP 和端口。

## 调试

- 当只需要从数据库读取时，使用 `database-query` 工具。
- 在编写迁移或模型之前，使用 `database-schema` 工具检查表结构。
- 要执行 PHP 代码进行调试，直接运行 `php artisan tinker --execute "your code here"`。
- 要读取配置值，直接读取配置文件或运行 `php artisan config:show [key]`。
- 要检查路由，直接运行 `php artisan route:list`。
- 要检查环境变量，直接读取 `.env` 文件。

## 使用 `browser-logs` 工具读取浏览器日志

- 可以使用 Boost 的 `browser-logs` 工具读取浏览器日志、错误和异常。
- 只有最近的浏览器日志才有用 — 忽略旧日志。

## 搜索文档（非常重要）

- Boost 配备了一个强大的 `search-docs` 工具，在处理 Laravel 或 Laravel 生态包时应在尝试其他方法之前使用。该工具会自动将已安装包及其版本列表传递给远程 Boost API，因此只返回与用户环境相关的版本特定文档。如果你知道需要特定包的文档，应传递一个包数组进行过滤。
- 在修改代码之前搜索文档，以确保我们采取正确的方法。
- 一次使用多个宽泛、简单的基于主题的查询。例如：`['rate limiting', 'routing rate limiting', 'routing']`。最相关的结果会优先返回。
- 不要将包名添加到查询中；包信息已经共享。例如，使用 `test resource table`，而不是 `filament 4 test resource table`。

### 可用搜索语法

1. 带自动词干提取的简单单词搜索 - query=authentication - 找到 'authenticate' 和 'auth'。
2. 多个单词（AND 逻辑）- query=rate limit - 找到包含 "rate" 和 "limit" 的知识。
3. 带引号的短语（精确位置）- query="infinite scroll" - 单词必须相邻且顺序一致。
4. 混合查询 - query=middleware "rate limit" - "middleware" 和精确短语 "rate limit"。
5. 多个查询 - queries=["authentication", "middleware"] - 以上任意一个。

=== php rules ===

# PHP

- 控制结构始终使用花括号，即使是单行主体。

## 构造器

- 在 `__construct()` 中使用 PHP 8 构造器属性提升。
    - `public function __construct(public GitHub $github) { }`
- 不允许零参数的空 `__construct()` 方法，除非构造器是私有的。

## 类型声明

- 方法和函数始终使用显式返回类型声明。
- 对方法参数使用适当的 PHP 类型提示。

```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## 枚举

- 枚举中的键通常应使用 TitleCase。例如：`FavoritePerson`、`BestLake`、`Monthly`。

## 注释

- 优先使用 PHPDoc 块而非行内注释。除非逻辑异常复杂，否则不要在代码内部使用注释。

## PHPDoc 块

- 在适当时添加有用的数组形状类型定义。

=== tests rules ===

# 测试执行

- 每次变更都必须进行程序化测试。编写新测试或更新现有测试，然后运行受影响的测试以确保它们通过。
- 运行确保代码质量和速度所需的最少测试。使用 `php artisan test --compact` 并指定特定文件名或过滤器。

=== laravel/core rules ===

# 按 Laravel 的方式做事

- 使用 `php artisan make:` 命令创建新文件（即迁移、控制器、模型等）。可以使用 `php artisan list` 列出可用 Artisan 命令，使用 `php artisan [command] --help` 检查参数。
- 如果正在创建通用的 PHP 类，使用 `php artisan make:class`。
- 向所有 Artisan 命令传递 `--no-interaction` 以确保它们无需用户输入即可工作。还应传递正确的 `--options` 以确保正确行为。

## 数据库

- 始终使用带返回类型提示的正确 Eloquent 关联方法。优先使用关联方法而非原生查询或手动连接。
- 在建议原生数据库查询之前，先使用 Eloquent 模型和关联。
- 避免使用 `DB::`；优先使用 `Model::query()`。生成利用 Laravel ORM 功能的代码，而非绕过它。
- 通过使用预加载来防止 N+1 查询问题的代码生成。
- 对非常复杂的数据库操作使用 Laravel 查询构建器。

### 模型创建

- 创建新模型时，同时创建有用的工厂和播种器。询问用户是否需要其他东西，使用 `php artisan make:model --help` 检查可用选项。

### API 和 Eloquent Resources

- 对于 API，默认使用 Eloquent API Resources 和 API 版本控制，除非现有 API 路由不使用，否则应遵循现有应用约定。

## 控制器和校验

- 始终创建 Form Request 类进行校验，而不是在控制器内联校验。同时包含校验规则和自定义错误消息。
- 查看同级 Form Request 以了解应用使用数组还是字符串形式的校验规则。

## 认证和授权

- 使用 Laravel 内置的认证和授权功能（gates、policies、Sanctum 等）。

## URL 生成

- 生成到其他页面的链接时，优先使用命名路由和 `route()` 函数。

## 队列

- 对耗时的操作使用带 `ShouldQueue` 接口的排队任务。

## 配置

- 环境变量只能在配置文件中使用 — 不要在配置文件之外直接使用 `env()` 函数。始终使用 `config('app.name')`，而不是 `env('APP_NAME')`。

## 测试

- 为测试创建模型时，使用模型的工厂。在手动设置模型之前，检查工厂是否有可用的自定义状态。
- Faker：使用方法如 `$this->faker->word()` 或 `fake()->randomDigit()`。遵循现有约定使用 `$this->faker` 还是 `fake()`。
- 创建测试时，使用 `php artisan make:test [options] {name}` 创建功能测试，并传递 `--unit` 创建单元测试。大多数测试应该是功能测试。

## Vite 错误

- 如果收到 "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" 错误，可以运行 `npm run build` 或要求用户运行 `npm run dev` 或 `composer run dev`。

=== laravel/v13 rules ===

# Laravel 13 是 Laravel 的最新版本，本项目当前使用 Laravel 13。在处理本项目时务必检查版本特定的文档，并使用 `search-docs` 工具确保引用正确的版本。

- 关键：始终使用 `search-docs` 工具获取版本特定的 Laravel 文档和更新后的代码示例。
- 自 Laravel 11 起，Laravel 采用了新的精简文件结构，本项目使用该结构。

## Laravel 13 结构

- 在 Laravel 13 中，中间件不再在 `app/Http/Kernel.php` 中注册。
- 中间件在 `bootstrap/app.php` 中使用 `Application::configure()->withMiddleware()` 进行声明式配置。
- `bootstrap/app.php` 是注册中间件、异常和路由文件的文件。
- `bootstrap/providers.php` 包含应用特定的服务提供者。
- `app/Console/Kernel.php` 文件不再存在；使用 `bootstrap/app.php` 或 `routes/console.php` 进行控制台配置。
- `app/Console/Commands/` 中的控制台命令自动可用，无需手动注册。

## 数据库

- 修改列时，迁移必须包含该列之前定义的所有属性。否则，它们将被删除并丢失。
- Laravel 13 允许原生限制预加载的记录，无需外部包：`$query->latest()->limit(10);`。

### 模型

- 类型转换可以在模型的 `casts()` 方法中设置，而不是在 `$casts` 属性中设置。遵循其他模型的现有约定。

=== pint/core rules ===

# Laravel Pint 代码格式化程序

- 如果修改了任何 PHP 文件，必须在最终确定更改之前运行 `vendor/bin/pint --dirty --format agent`，以确保代码匹配项目的预期样式。
- 不要运行 `vendor/bin/pint --test --format agent`，只需运行 `vendor/bin/pint --format agent` 来修复任何格式问题。

=== phpunit/core rules ===

# PHPUnit

- 本应用使用 PHPUnit 进行测试。所有测试必须编写为 PHPUnit 类。使用 `php artisan make:test --phpunit {name}` 创建新测试。
- 如果看到使用 "Pest" 的测试，转换为 PHPUnit。
- 每次更新测试时，运行该单个测试。
- 当相关功能的测试通过时，询问用户是否愿意运行整个测试套件以确保一切仍然正常。
- 测试应涵盖所有 happy paths、failure paths 和 edge cases。
- 未经批准，不得从 tests 目录中删除任何测试或测试文件。这些不是临时或辅助文件；它们是应用的核心。

## 运行测试

- 在最终确定之前，使用适当的过滤器运行最少数量的测试。
- 运行所有测试：`php artisan test --compact`。
- 运行文件中的所有测试：`php artisan test --compact tests/Feature/ExampleTest.php`。
- 过滤特定测试名称：`php artisan test --compact --filter=testName`（修改相关文件后推荐使用）。

</laravel-boost-guidelines>
