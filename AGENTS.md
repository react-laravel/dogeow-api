# AGENTS.md

本文档为 AI 编码助手提供项目上下文，便于快速理解项目结构和开发规范。

## 项目概述

- **类型**: Laravel 12 API 后端项目
- **用途**: 为 dogeow (Next.js) 前端提供 RESTful API 服务
- **语言**: 中文回答与注释

## 技术栈

| 分类 | 技术 |
|------|------|
| 核心 | Laravel 12、PHP 8.2+、MySQL 8、Redis 7 |
| 认证 | Laravel Sanctum（不用第三方 JWT） |
| 搜索 | Laravel Scout |
| 图片 | intervention/image |
| 实时 | Laravel Reverb |
| 权限 | spatie/laravel-permission |
| 查询 | spatie/laravel-query-builder |
| 日志 | spatie/laravel-activitylog |
| 备份 | spatie/laravel-backup |
| 媒体 | spatie/laravel-medialibrary |
| 推送 | laravel-notification-channels/webpush |
| 工具 | Laravel Octane、Horizon、Telescope、Pint |

## 项目结构

```
app/
├── Http/
│   ├── Controllers/Api/     # 按业务拆分：Game、Note、Word、Thing、Chat 等
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
├── chat.php, game.php, item.php, location.php
├── note.php, profile.php, todo.php, word.php
└── logs.php
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
- 不恢复已修改的逻辑
- 使用 PHP 8 构造器属性提升、显式返回类型
- 修改后运行 `vendor/bin/pint --dirty` 保持格式

### API 设计
- RESTful
- 统一响应格式（`ApiResponse`）
- 适当的 HTTP 状态码和错误处理

### 测试
- 使用 PHPUnit，不写 Pest
- 新建/修改后运行相关测试
- 使用工厂创建测试数据

## 常用命令

| 用途 | 命令 |
|------|------|
| 开发 | `composer run dev`（serve + queue + reverb） |
| 测试 | `php artisan test` |
| 单测 | `php artisan test --filter=testName` |
| 代码格式 | `vendor/bin/pint --dirty` |
| 迁移 | `php artisan migrate` |
| 队列 | `php artisan queue:work` |

## 主要业务模块

- **聊天 (Chat)**: 房间、消息、WebSocket、Web Push
- **游戏 (Game)**: 角色、战斗、背包、商店、技能
- **笔记 (Note)**: 分类、标签
- **地点/物品 (Thing)**: Location、Item、Category、Tag
- **单词 (Word)**: 书本、学习、艾宾浩斯复习、打卡
- **待办 (Todo)**: 任务管理

## 注意事项

1. **数据库**: 无外键，关系在模型与 Service 中维护
2. **认证**: 所有需登录接口使用 `auth:sanctum` 中间件
3. **响应**: 使用 `ApiResponse` 或 Eloquent Resource 统一格式
4. **验证**: 使用 Form Request 类，不在控制器内做校验
