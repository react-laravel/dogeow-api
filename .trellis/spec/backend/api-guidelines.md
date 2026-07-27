# API 规范

## 路由与认证

- API 总入口是 `routes/api.php`。公开接口必须在 `auth:sanctum` 组外定义，通常放在
  `routes/api/public.php`；认证接口在模块路由中定义并由总入口包含。
- 写操作默认要求 `auth:sanctum`。管理员接口额外使用 `admin` 中间件，重复提交风险高的接口
  可使用 `idempotency`，外部或高成本接口使用明确的 `throttle`。
- 同一路径下，固定路径必须放在资源参数之前，避免被 `{id}` 捕获。参考
  `routes/api/note.php` 中 `notes/graph` 位于 `Route::apiResource('notes', ...)` 之前。
- 路由模型绑定适用于普通资源；需要隐藏资源存在性或特殊可见性时，在授权查询中返回 404。

## 输入边界

- 所有业务输入使用独立 Form Request，并在控制器中只读取 `$request->validated()`。
- `authorize()` 与 Policy 各司其职：通用模型能力使用 Policy；字段级和租户级存在性约束放在
  Request。参考 `ItemRequest` 使用带 `user_id` 条件的 `Rule::exists()`。
- 多租户资源 ID、上传路径、标签和图片 ID 必须验证属于当前用户或当前父资源，不能只验证
  “记录存在”。`ItemRequest` 的 `image_paths`、`primary_image_id`、`tag_ids` 是参考实现。
- PATCH/局部更新使用 `sometimes` 规则；创建与更新语义差异大时拆分 Request，参考
  `NoteRequest` 与 `UpdateNoteRequest`。
- 用户可见错误消息可在 `messages()` / `attributes()` 中提供中文文本。

## 授权

- 认证使用 Sanctum；测试使用 `Sanctum::actingAs()`。
- 模型读写权限通过 `$this->authorize()` 和 `app/Policies/` 实现。所有者比较时将 ID 转成一致
  类型，参考 `ThingItemPolicy::ownsItem()`。
- 列表查询也必须显式应用可见性条件，不能依赖详情 Policy 自动过滤。参考
  `ItemController::applyVisibilityFilter()`。
- 管理员判断使用 `User::isAdmin()` / `hasRole('admin')`，不要信任客户端传入的角色字段。

## 响应契约

- 新增普通端点优先使用 `App\Http\Resources\ApiResponse` 或 Eloquent `JsonResource`：

```json
{"success": true, "message": "物品创建成功", "data": {}}
```

错误响应使用 `success=false`、`message`，字段错误放在 `errors`。创建返回 201，认证失败 401，
禁止访问 403，不存在 404，验证失败 422，限流 429，未捕获服务端错误 500；删除可返回 204。

- 分页列表可直接返回 `spatie/laravel-json-api-paginate` 的 `data` / `links` 契约，参考
  `ItemController::index()`。
- 仓库存在少量历史接口返回裸模型或自定义结构。修改这些接口前先查看对应 Feature 测试和
  前端契约；不要仅为“统一格式”破坏已有响应。新增接口不要复制历史不一致。
- 输出字段需要稳定变换或条件关联时创建 JsonResource，参考 `BookResource` 的
  `whenLoaded()`；不要在控制器重复手写大型映射。

## 查询与副作用

- 列表预加载客户端需要的关系并限制字段/数量，避免 N+1 和无界响应。
- 可筛选列表使用 `spatie/laravel-query-builder` 的 allowlist，不接受任意字段或排序参数。
- 涉及多条记录的一致性写入使用 `DB::transaction()`；事务内只保留必须同步完成的操作。
- 缓存失效、索引构建、图片处理等后续行为通过 Service/Job 编排。Job 调度位置必须与数据
  提交时序一致，并由测试覆盖。
