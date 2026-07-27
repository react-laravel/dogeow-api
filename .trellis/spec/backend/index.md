# 后端开发规范

本目录描述 `dogeow-api` 当前使用的 Laravel 13 / PHP 8.4 开发约定。规范来自
`AGENTS.md`、`CLAUDE.md`、实际源码和测试；新增代码应先查看同业务目录中的相邻实现，
不要把其他 Laravel 项目的惯例直接套入本仓库。

## 规范索引

| 文档 | 内容 |
| --- | --- |
| [目录与分层](./directory-structure.md) | 路由、控制器、请求、服务、任务及业务模块的放置方式 |
| [API 规范](./api-guidelines.md) | 路由、输入边界、认证授权、响应和分页契约 |
| [数据库规范](./database-guidelines.md) | Eloquent、关系、迁移、索引、事务及多数据库约束 |
| [错误处理](./error-handling.md) | 异常传播、客户端错误、后台任务失败和安全响应 |
| [日志规范](./logging-guidelines.md) | Laravel 日志级别、结构化上下文和敏感信息边界 |
| [质量规范](./quality-guidelines.md) | PHP 风格、测试、格式化及审查清单 |

## 项目边界

- 本仓库是 REST API 后端，不包含 Next.js 前端源码。
- 生产数据库是 PostgreSQL 18；本地示例可使用 SQLite，测试配置当前使用 MySQL。
- 认证使用 Sanctum，实时通信使用 Reverb，异常监控接入 Sentry。
- 关联由 Eloquent 和应用层维护，数据库迁移不创建外键约束。

涉及通用推理方法时再读取 `../guides/`；具体实现以本目录规范和相邻代码为准。
