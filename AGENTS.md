# AGENTS.md

本文档为 AI 编码助手提供项目上下文，便于快速理解项目结构和开发规范。

## 项目概述

- **类型**：Laravel 13 API 后端项目
- **用途**：为 dogeow (Next.js) 前端提供 RESTful API 服务
- **语言**：中文回答与注释

## 技术栈

| 分类 | 技术 |
| ------ | ------ |
| 核心 | Laravel 13、PHP 8.4+、PostgreSQL 18（生产）/ SQLite（本地示例）、Redis 7 |
| 认证 | Laravel Sanctum |
| 搜索 | Eloquent LIKE / 自定义 scope（物品等） |
| 图片 | intervention/image |
| 实时 | Laravel Reverb |
| 查询 | spatie/laravel-query-builder、spatie/laravel-json-api-paginate |
| 推送 | laravel-notification-channels/webpush |
| 社交登录 | Laravel Socialite |
| Redis 客户端 | 默认 phpredis；CI/测试可用 predis（`REDIS_CLIENT`） |
| 工具 | Laravel Octane、Pint、Sentry |

## 项目结构

```plain
app/
├── Http/
│   ├── Controllers/Api/     # 按业务拆分：Note、Word、Thing、Cloud、Nav、Book 等
│   ├── Requests/            # Form Request 按功能分类
│   ├── Middleware/
│   └── Resources/           # API Resource
├── Models/                  # Eloquent 模型
├── Services/                # 业务逻辑服务层
├── Jobs/                    # 队列任务
└── Policies/                # 授权策略

routes/api/
├── public.php               # 公开 API
├── broadcast.php            # 广播认证
├── book.php, cloud.php, dashboard.php, item.php, knowledge.php
├── location.php, nav.php, note.php, notification.php, profile.php
├── todo.php, tools.php, websocket.php, word.php
├── cache.php, logs.php
```

## 开发规范

### 技术选择

- 优先使用 Laravel 官方包
- 认证用 Sanctum
- **不使用数据库外键**，由应用层维护关联

### 代码组织

- 单文件不超过 600 行，超出需拆分
- Policy、Form Request 等单独文件
- 按业务功能组织代码

### 代码质量

- 可改进注释，但不删除现有注释
- 使用 PHP 8 构造器属性提升、显式返回类型
- 修改后运行 `vendor/bin/pint --dirty` 保持格式

### API 设计

- RESTful
- 统一响应格式（`ApiResponse`）
- 适当的 HTTP 状态码和错误处理

### 测试

- 新建/修改后运行相关测试
- 使用工厂创建测试数据

## 常用命令

| 用途 | 命令 |
| ------ | ------ |
| 开发 | `composer run dev`（serve + queue + reverb） |
| 测试 | `php artisan test` |
| 单测 | `php artisan test --filter=testName` |
| 代码格式 | `vendor/bin/pint --dirty` |
| 迁移 | `php artisan migrate` |
| 队列 | `php artisan queue:work` |

## 主要业务模块

- **系统本身（App）**：Web Push、通知、Dashboard
- **笔记 (Note)**：分类、标签、Wiki
- **地点/物品 (Thing)**：Location、Item、Category、Tag
- **云盘 (Cloud)**：文件与目录树
- **导航 (Nav)**：全站导航（写操作需管理员）
- **单词 (Word)**：书本、学习、艾宾浩斯复习、打卡
- **待办 (Todo)**：任务管理
- **书籍书签 (Book)**、知识图谱 (Knowledge)

## 注意事项

1. **数据库**：无外键，关系在模型与 Service 中维护
2. **认证**：所有需登录接口使用 `auth:sanctum` 中间件
3. **响应**：使用 `ApiResponse` 或 Eloquent Resource 统一格式
4. **验证**：使用 Form Request 类，不在控制器内做校验
<!-- TRELLIS:START -->
# Trellis Instructions

These instructions are for AI assistants working in this project.

This project is managed by Trellis. The working knowledge you need lives under `.trellis/`:

- `.trellis/workflow.md` — development phases, when to create tasks, skill routing
- `.trellis/spec/` — package- and layer-scoped coding guidelines (read before writing code in a given layer)
- `.trellis/workspace/` — per-developer journals and session traces
- `.trellis/tasks/` — active and archived tasks (PRDs, research, jsonl context)

If a Trellis command is available on your platform (e.g. `/trellis:finish-work`, `/trellis:continue`), prefer it over manual steps. Not every platform exposes every command.

If you're using Codex or another agent-capable tool, additional project-scoped helpers may live in:
- `.agents/skills/` — reusable Trellis skills
- `.codex/agents/` — optional custom subagents

Managed by Trellis. Edits outside this block are preserved; edits inside may be overwritten by a future `trellis update`.

<!-- TRELLIS:END -->
