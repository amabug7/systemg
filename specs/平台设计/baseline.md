# 平台基线规范（Baseline）

## 1. 子系统目录与职责
- `background`：统一配置、数据服务、日志上报、运营后台。
- `client`：桌面端用户程序，消费 `bootstrap/games/game` 与上报接口。
- `install`：轻量安装引导程序，专注下载与安装上报。
- `webset`：门户展示程序，专注内容浏览与转化入口。

## 2. 统一配置命名规范
- 全局键：`global.*`
- 终端键：`terminal.{client|webset}.*`
- 页面键：`page.{page_key}.*`
- 产品键：`product.{game_id|slug}.*`
- 活动键：`campaign.{activity_key}.*`
- 版本键：`version.client.*`

## 3. 统一 API 响应规范
- 成功响应
  - `code: 1`
  - `msg: ''`
  - `time: unix timestamp`
  - `data: object|array`
- 失败响应
  - `code: 0 | 4xx/5xx语义码`
  - `msg: string`
  - `time: unix timestamp`
  - `data: null|object`

## 4. 统一错误码字典（首版）
- `0`：通用失败
- `1`：成功
- `40001`：参数不合法
- `40101`：未登录或令牌失效
- `40301`：无权限
- `40401`：资源不存在
- `40901`：幂等冲突
- `42201`：状态流转不允许
- `42901`：请求频率过高
- `50001`：内部服务异常
- `50301`：下游服务不可用

## 5. 状态枚举规范
- 通用状态：`started|success|failed`
- 可见状态：`normal|hidden`
- 终端类型：`common|client|webset`
- 修复类型：`common|game`

## 6. 日志字段规范
- 公共字段：`id,user_id,game_id,status,device_id,client_version,error_code,meta_json,createtime,updatetime`
- 下载扩展：`channel,resource_type,resource_name`
- 安装扩展：`install_path`
- 修复扩展：`repair_type,action_key`
- `meta_json` 限制为对象 JSON 字符串，键使用 snake_case。

## 7. 幂等与安全约束
- 写接口优先 `POST`。
- 客户端可匿名上报，但有 token 时必须自动绑定用户。
- 上报接口按 `device_id + game_id + status + minute_bucket` 做去重策略。
- 管理后台导出接口走权限节点控制。

## 8. 版本与发布最小规则
- 配置版本号使用整型递增。
- 发布必须保留最近 10 次可回滚快照。
- 灰度字段最小集合：`enabled,ratio,group_tag,min_client_version`。

