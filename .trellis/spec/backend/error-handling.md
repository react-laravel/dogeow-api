# 错误处理

## HTTP 边界

- 输入错误由 Form Request 产生 422 响应；不要在控制器手工拼接字段验证。
- 未认证使用 401，已认证但无权限使用 403，资源不可见或不存在使用 404。需要隐藏私有资源
  是否存在时返回 404，而不是泄漏为 403。
- 业务错误通过 `ApiResponse::error()` 及其 `notFound()`、`forbidden()`、
  `validationError()`、`serverError()` 等方法返回稳定结构。
- 成功删除可使用 `response()->noContent()`；不要返回带 JSON body 的 204。

## 异常传播

- 让 Laravel 的 `ValidationException`、`ModelNotFoundException`、授权异常和 HTTP 异常在
  能表达正确语义时自然传播，避免先捕获再转换成模糊的 500。
- 只捕获当前层能够恢复、降级或补充业务上下文的异常。例如批量关联允许记录单项失败并继续；
  外部信息提供商失败可返回降级结果。
- 捕获未知 `Throwable` 时，记录操作名和定位 ID，对客户端返回安全的通用消息。不要把 trace、
  SQL、绝对路径、访问令牌或外部响应密钥暴露给客户端。
- Job 遇到需要重试的失败必须抛出异常；永久失败日志放在 `failed(Throwable $exception)`。
  `ProcessUploadedImageJob` 是参考实现。
- 文件与数据库同时变更时要明确补偿行为；数据库事务不能自动回滚磁盘或外部 API 副作用。

## 当前全局处理

- `bootstrap/app.php` 通过 `Sentry\Laravel\Integration::handles()` 上报全局异常。
- `app/Exceptions/ApiExceptionHandler.php` 定义并测试了 API JSON 映射，但当前
  `bootstrap/app.php` 没有注册它。除非同时完成注册和集成测试，不要假设该类会处理线上异常。
- 生产错误不得携带 debug trace；本地诊断可以从 Laravel 日志、Sentry 或相关测试获取。

## 参考模式

- `ItemController::addRelation()`：识别可预期的重复关联，其他异常记录上下文并返回通用 500。
- `NoteController::findUserNote()`：不可见资源转换为 `ModelNotFoundException`，避免泄露。
- `TriggerKnowledgeIndexBuildJob::handle()`：未配置时 warning 并跳过，非成功响应 warning，
  连接异常 error。
- `ItemRequest`：在到达控制器前拒绝跨用户标签、位置和上传路径。

## 常见错误

- `catch (\Exception)` 后吞掉错误并仍返回成功。
- 将 `$exception->getMessage()` 原样返回给生产客户端。
- 对授权失败一律返回 200 或 422。
- 捕获异常但既不记录上下文、也不重新抛出。
- 用 try/catch 替代数据库唯一索引、Form Request 或 Policy 应承担的约束。
