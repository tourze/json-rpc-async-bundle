# Entity Design: AsyncResult

## Entity: AsyncResult

| 字段名        | 类型                | 说明           |
| ------------- | ------------------- | -------------- |
| id            | bigint (Snowflake)  | 主键ID         |
| taskId        | string(100), unique | 任务ID         |
| result        | json, nullable      | 响应内容       |
| createTime    | datetime, nullable  | 创建时间       |

### 设计说明

- `id` 采用 Snowflake 算法生成，确保分布式唯一性。
- `taskId` 用于异步任务的唯一标识，所有结果查询均基于该字段。
- `result` 保存 JSON-RPC 执行后的响应内容，支持结构化数据。
- `createTime` 由 `CreateTimeColumn` 自动填充。
- 实体表配置有自动清理（见 `AsScheduleClean` 注解），支持定期清理过期数据。

### 相关代码

- 实体定义：`src/Entity/AsyncResult.php`
- 仓库类：`src/Repository/AsyncResultRepository.php`
