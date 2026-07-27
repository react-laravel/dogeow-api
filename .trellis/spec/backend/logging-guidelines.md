# 日志规范

## 基础约定

- 使用 Laravel `Log` facade 和 `config/logging.php` 的 channel，不直接写日志文件。
- 默认 channel 是 `stack`；常规日志落到 `storage/logs/laravel.log`。图片上传有
  `image_upload` daily channel，需要隔离该领域日志时显式选用。
- 未捕获异常由 Laravel/Sentry 处理；业务层日志用于补充可操作上下文，不重复记录同一异常。

## 级别

- `info`：成功完成且确有运维价值的外部交互或后台流程，例如索引构建已触发、Web Push 成功。
- `warning`：可降级、可跳过或单项失败但主流程仍能继续，例如外部定位提供商失败、配置缺失、
  Web Push 发送失败。
- `error`：请求/Job 无法完成、数据或文件状态异常、需要排查的异常。
- `debug`：只用于短期本地诊断；不要把高频循环或完整 payload 长期写入生产日志。

## 结构化上下文

消息描述稳定的动作，动态值放在 context 数组：

```php
Log::error('添加物品关联失败', [
    'item_id' => $item->id,
    'related_item_id' => $relatedItemId,
    'user_id' => Auth::id(),
    'exception' => $e,
]);
```

- 优先记录定位所需的资源 ID、用户 ID、外部 URL/状态码、队列任务输入路径和异常对象。
- context key 使用 snake_case，并在同一业务内保持一致。
- Service 可复用 `BaseService::logError()` / `logInfo()` 的 `service` 与时间上下文；不要为了使用
  该 helper 强迫所有简单服务继承基类。
- Job 的最终失败在 `failed()` 中记录关键输入和异常消息，参考
  `ProcessUploadedImageJob`、`GenerateThumbnailForItemImageJob`。

## 敏感信息边界

不得记录：

- Sanctum token、SSO ticket、客户端 secret、密码、Authorization/Cookie header。
- 完整请求 body、上传文件内容或 Redis 中保存的身份 payload。
- 不必要的邮箱、第三方 API 响应正文和完整堆栈。

外部响应正文仅在确认不含凭据/个人数据且诊断确有需要时记录，并考虑截断。Web Push endpoint
属于可识别订阅信息，不要在新日志中扩散或与用户隐私数据组合。

## 避免噪音

- 不为普通 CRUD 成功逐条记日志，除非它是审计或异步流程要求。
- 不在控制器、服务和全局异常层重复记录同一个 Throwable。
- 不使用日志替代客户端错误响应、指标或测试断言。
