# 平台设计交付说明（Delivery）

## 1. 交付范围
- `background`：平台后台管理、API、配置发布/回滚、监控告警、增长分析能力。
- `client/ui`：当前主客户端界面与业务联调入口。
- `client/desktop`：Electron 桌面壳层。
- `install/SystemaInstaller`：独立安装器工程。
- `specs/平台设计`：规格文档、任务清单、验收清单（均已完成状态对齐）。

## 2. 部署与初始化顺序
1. 后台环境准备：
   - 启动数据库与 PHP 运行环境。
   - 使用 `background/application/admin/command/Install/fastadmin.sql` 初始化基础表。
   - 使用 `background/application/admin/command/Install/platform.sql` 初始化平台业务表与菜单权限。
2. 后台服务启动：
   - 启动 `background` 项目。
   - 确认后台可访问、API 根地址可访问（默认示例 `http://game.test/api`）。
3. 主客户端验证：
   - 构建 `client/ui`。
   - 打开 `client/ui/dist/index.html`。
4. 桌面壳层验证（可选）：
   - 进入 `client/desktop`，执行 `npm install`。
   - 执行 `npm start` 启动 Electron 壳层。

## 3. 核心联调路径
### 3.1 配置驱动路径
- 后台配置页面/推荐位/专题并发布。
- `client/ui` 通过 `/platform/bootstrap` 拉取配置。
- 发布异常时通过发布历史执行回滚，验证终端配置恢复。

### 3.2 交付闭环路径
- 在 `client/ui` 执行浏览、详情、下载、安装、修复、定价、订单、资产等主流程。
- 在 `install/SystemaInstaller` 执行安装器相关流程验证（如有需要）。

### 3.3 运维治理路径
- 查看概览、节点健康、运维告警、回滚任务、发布审计。
- 人工触发失败上报，验证告警创建、异常聚合、自动守护回滚链路。

## 4. 账号与权限模板
- 已在 `platform.sql` 提供最小权限角色模板：
  - `Platform Ops Readonly`
  - `Platform Risk Handler`
  - `Platform Publisher`
- 建议在上线前按组织结构分配后台账号到上述模板，并复核菜单粒度。

## 5. 验收脚本（建议顺序）
1. 基线验收：检查 `checklist.md` 与 `tasks.md` 全部为完成状态。
2. 功能验收：按“配置驱动 → 交付闭环 → 运维治理”三条路径走通。
3. 安全验收：验证支付验签、幂等、关键接口限流、异常告警创建。
4. 性能验收：验证核心读接口缓存命中（bootstrap/games/game）。
5. 回归验收：确认已有后台 CRUD、发布、回滚、统计能力不受影响。

## 6. 交接注意事项
- 当前主客户端以 `client/ui` 为准，桌面壳层负责承载该构建产物。
- 独立安装器能力以 `install/SystemaInstaller` 工程为准。
- 异常识别规则为轻量策略，生产建议接入更完整风控规则与告警路由。
- 文档与代码按当前仓库状态保持一致，后续变更建议先更新 `specs` 再改实现。
