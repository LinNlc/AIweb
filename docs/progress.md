# 模块化拆分进度

## 2025-10-14 第 1 步
- 新增 `app/` 目录基础结构（config/core/public/storage）。
- 抽离数据库与通用工具函数至 `app/core/Utils.php`。
- 新增 `app/bootstrap.php` 负责环境初始化与配置加载。
- 调整入口 `index.php` 引导新结构，并改为读取 `app/public/index.html`。
- 暂未拆分 API 业务逻辑与前端脚本，后续步骤继续推进。

## 2025-10-14 第 2 步
- 建立 `app/api/schedule.php`，集中处理排班读取、保存、导出等接口逻辑并补充中文注释。
- 更新入口 `index.php` 将排班相关路由委托至新模块，缩短主控制器体积。
- 进度保持在排班接口层，后续继续拆分版本管理、前端脚本等模块。

## 2025-10-14 第 3 步
- 新增 `app/api/versions.php` 负责历史版本查询、删除与导出，排班接口文件进一步瘦身。
- 更新 `index.php` 引导版本模块路由，确保 API 委托关系清晰。
- `schedule.php` 仅保留当期排班读取与保存逻辑，为后续引入核心算法层腾出空间。

## 2025-10-14 第 4 步
- 拆分排班数据装配逻辑至 `app/core/DTO.php`，统一管理快照与载荷结构。
- 新建 `app/core/Scheduler.php` 收纳历史统计算法，API 层改为依赖核心模块。
- `schedule.php`、`versions.php` 仅聚焦路由与参数处理，持续压缩控制器复杂度。

## 2025-10-14 第 5 步
- 拆分登录占位接口至 `app/api/auth.php`，入口仅负责路由。
- 拆分组织配置读写至 `app/api/org_config.php`，复用工具层数据库逻辑。
- 更新 `index.php` 顺序调用各模块路由，默认统一返回 404。

## 2025-10-14 第 6 步
- 将前端 React 逻辑迁移至 `app/public/js/main.js`，为后续拆分 UI/API/State 模块打基础。
- 新建 `app/public/js/` 目录，按新版结构准备静态资源分层入口。
- `index.html` 只保留样式与脚本引用，便于逐步替换为模块化加载方案。

## 2025-10-14 第 7 步
- 拆分前端脚本：新增 `api.js`、`state.js`、`ui.js` 将网络请求、业务状态与通用 UI 组件从 `main.js` 中抽离。
- 更新 `index.html` 按顺序加载新模块，确保入口脚本拿到 `window.AppAPI`、`window.AppState`、`window.AppUI`。
- `main.js` 改为只关注应用逻辑与 React 组件装配，维持原有界面与交互。
- 当前静态资源目录遵循目标结构：

```text
/app
  /public               # 对外暴露：index.html、静态资源
    index.html
    /assets
    /js
      main.js           # 入口（ESM）
      ui.js             # 只管 UI
      api.js            # 只管请求
      state.js          # 前端状态 & 校验
  /api                  # PHP 接口（纯 JSON）
    schedule.php
    progress.php
    versions.php
  /core                 # 纯 PHP 业务核心（算法/规则/模型）
    Scheduler.php
    Rules.php
    DTO.php
    Utils.php
  /storage              # 数据持久化（SQLite、JSON日志、导出文件等）
    app.db
    logs/
    exports/
  /config
    app.php
```

## 2025-10-14 第 8 步
- 新增 `app/core/Rules.php`，集中处理团队、日期、成员与排班矩阵的校验与归一化逻辑，便于核心层复用。
- `app/api/schedule.php` 在保存排班前调用规则模块完成数据清洗，捕获异常后返回 422，避免脏数据入库。
- 进度保持与目标目录结构一致，持续朝 `/app/public/js/{main,ui,api,state}.js` 与核心 `/app/core/{Scheduler,Rules,DTO,Utils}.php` 的分层演进：

```text
/app
  /public               # 对外暴露：index.html、静态资源
    index.html
    /assets
    /js
      main.js           # 入口（ESM）
      ui.js             # 只管 UI
      api.js            # 只管请求
      state.js          # 前端状态 & 校验
  /api                  # PHP 接口（纯 JSON）
    schedule.php        # 触发排班
    progress.php        # 进度查询（可选）
    versions.php        # 历史版本/导入导出
  /core                 # 纯 PHP 业务核心（算法/规则/模型）
    Scheduler.php       # 排班算法（核心）
    Rules.php           # 规则集合（强制/软性）
    DTO.php             # 数据对象/类型约束（数组转对象）
    Utils.php           # 通用工具（随机种子/日志）
  /storage              # 数据持久化（SQLite、JSON日志、导出文件等）
    app.db
    logs/
    exports/
  /config
    app.php             # 全局配置（时区、超时、日志级别等）
```

## 2025-10-14 第 9 步
- 将导航栏、团队选择、登录弹窗与月份输入框等通用 React 组件迁移至 `app/public/js/ui.js`，入口脚本通过 `window.AppUI` 解构调用。
- 精简 `app/public/js/main.js`，仅保留应用状态与业务逻辑，后续可继续拆分具体业务面板。
- 目录结构保持与目标一致，继续沿用 `/app/public/js/{api,state,ui,main}.js` 前端分层与 `/app/core` 核心算法模块。

## 2025-10-14 第 10 步
- 在 `app/public/js/ui.js` 新增 `BatchAssignSection` 以及人员列表、规则与夜班工具子组件，让批量排班 UI 与业务逻辑彻底解耦。
- `app/public/js/main.js` 以 `handleRunMainRule` / `handleAssignNight` 管理算法流程，仅负责状态与回调，视图渲染统一委托 UI 模块。
- 更新错误处理以确保排班异常写入进度日志，并在 UI 模块维持原有交互体验，整体结构更贴合目标目录分层。

## 2025-10-14 第 11 步
- 将历史版本列表与设置表单提炼为 `HistorySection`、`SettingsSection`，统一放置在 `app/public/js/ui.js` 中管理视图细节。
- `app/public/js/main.js` 通过显式传参与回调方式驱动新组件，页面主文件仅聚焦状态变更、持久化与接口调用。
- 进度文档同步强调前端 UI 组件逐步迁移到共享模块，便于下阶段继续拆分排班表格与员工管理面板。

## 2025-10-14 第 12 步
- 在 `app/public/js/ui.js` 新增 `ScheduleGridSection`，封装排班表格、Excel 粘贴与班次选择器交互，组件内部自理焦点与浮层。
- `app/public/js/main.js` 仅负责传入排班数据、班次配色与统计信息，移除原本的 DOM 操作与事件监听，聚焦业务状态。
- 继续在文档记录目标结构，下一步将考虑拆分员工管理与统计视图，保持新版目录规范：

```text
/app
  /public
    index.html
    /assets
    /js
      main.js
      ui.js
      api.js
      state.js
  /api
    schedule.php
    progress.php
    versions.php
  /core
    Scheduler.php
    Rules.php
    DTO.php
    Utils.php
  /storage
    app.db
    logs/
    exports/
  /config
    app.php
```

## 2025-10-14 第 13 步
- 将专辑审核面板迁移至 `app/public/js/ui.js` 的 `AlbumSection`，入口文件仅负责传入选中人员、排班结果与导入导出回调。
- 将员工管理页面抽离为 `EmployeesSection`，在 UI 模块内维护表单样式，入口侧集中处理批量导入、偏好变更与删除逻辑。
- 为批量勾选与清空专辑审核人员新增辅助方法，同时在文档同步模块化进展，确保 `main.js` 继续聚焦状态与校验编排。

## 2025-10-14 第 14 步
- 在 `app/public/js/state.js` 新增 `runAutoScheduleFlow`、`assignNightForWindows`，将入口层的自动排班与夜班分配流程沉淀为可复用的业务管线。
- 精简 `app/public/js/main.js`，入口仅负责收集状态、触发新管线并更新进度条/日志，进一步降低 2k+ 行主文件的心智负担。
- 更新进度文档记录拆分百分比，为下一步实现进度查询 API 及存储日志落地提供参考。

## 2025-10-14 第 15 步
- 新增 `app/api/progress.php`，提供进度列表与追加接口，统一读取 `storage/logs/progress.jsonl` 供前端展示调度日志。
- 在 `app/core/Utils.php` 扩展存储目录解析与 `append_progress_log`、`read_progress_logs` 工具函数，支撑日志写入/回放。
- `app/api/schedule.php` 保存排班时追加进度日志，`index.php` 接入新路由，形成完整的日志链路。
- 文档补充现有程序结构清单与模块职责说明，方便后续拆分阶段快速定位文件。

## 2025-10-14 第 16 步
- 在 `app/public/js/state.js` 新增 `useOrgConfigState`、`useTeamStateMap` 自定义 Hook，集中处理组织配置与团队排班数据的本地缓存、后端同步与默认值生成。
- 精简 `app/public/js/main.js`，入口组件改为依赖新 Hook 获取配置与排班映射，将 2k+ 行主文件进一步聚焦在业务编排、事件回调与 UI 组合。
- 更新本文档以同步最新拆分成果、完成度与文件职责表，为后续步骤继续切分统计/导出模块提供依据。

## 2025-10-14 第 17 步
- 新增 `app/core/Repository.php`，集中封装排班版本的数据库读写，统一乐观锁、范围查询与插入逻辑。
- `app/api/schedule.php`、`app/api/versions.php` 改为依赖仓储模块处理数据访问，API 层聚焦参数校验与响应装配。
- 文档同步更新完成度、目录结构与文件职责说明，便于下一阶段继续拆分统计报表与导出工具。

## 2025-10-14 第 18 步
- 新增 `app/core/Storage.php`，负责统一解析 SQLite 同级的存储目录并按需创建日志、导出等文件夹。
- 新建 `app/core/Progress.php`，将进度日志的读取/写入逻辑与路径解析集中管理，配套中文注释说明调用方式。
- `app/api/schedule.php`、`app/api/progress.php` 引入新模块以复用日志能力，`Utils.php` 仅保留数据库与通用 JSON/HTTP 工具。

## 2025-10-14 第 19 步
- 拆分请求解析与 JSON 响应工具，新增 `app/core/Http.php` 专职维护 `json_input`、`send_json`、`send_error` 等 HTTP 辅助函数。
- `app/bootstrap.php` 统一加载新模块，API 层无需重复引用即可复用标准化的请求/响应流程。
- `app/core/Utils.php` 精简为数据库、日期与通用算法工具，明确模块边界，后续可继续拆分缓存/随机数等能力。

## 2025-10-14 第 20 步
- 新增 `app/core/OrgConfig.php`，集中组织配置的输入规范化、JSON 编解码与数据库写入逻辑，供 API 与后续 CLI 工具复用。
- `app/api/org_config.php` 改为委托核心模块处理数据，接口层仅负责路由与 HTTP 响应，异常信息统一转换为 JSON 错误。
- 更新进度文档的完成度、目录结构说明与文件职责表，使组织配置模块的职责边界更加清晰。

## 2025-10-14 第 21 步
- 新增 `app/core/Exporter.php`，封装排班导出表头构建与 XLSX/CSV 输出流程，并补充中文注释便于复用。
- `app/api/versions.php` 的导出接口改为调用核心导出模块，API 层仅负责数据查询与参数校验，进一步压缩控制器体积。
- 文档同步更新完成度、整体结构速览与文件职责表，记录导出能力独立后的模块边界。

## 2025-10-14 第 22 步
- 新建 `app/core/ScheduleService.php`，集中封装排班查询/保存流程与乐观锁校验，暴露纯业务函数供 API 层调用。
- `app/api/schedule.php` 精简为参数提取 + 服务调用，冲突、校验等异常交由服务层抛出，接口仅负责 JSON 响应。
- 在本文档更新完成度至 95%，同时扩充文件职责表纳入新服务模块，便于后续聚焦统计/权限拆分。

## 2025-10-14 第 23 步
- 新增 `app/core/History.php`，将历史统计逻辑独立成模块，解耦排班算法与统计维度并补充中文注释。
- `app/core/ScheduleService.php` 与 `app/api/versions.php` 改为依赖新历史模块，`Scheduler.php` 专注保留自动排班入口占位。
- 更新本进度文档的完成度至 96%，同步结构概览与文件职责表，明确历史统计模块的职责边界。

## 2025-10-14 第 24 步
- 在 `app/public/js/state.js` 新增 `useProgressLog`、`useProgressTracker`，统一管理进度日志加载、追加与运行状态心跳逻辑。
- `app/public/js/main.js` 改为消费新 Hook，入口文件聚焦业务编排，进度/日志渲染交由状态模块集中处理。
- 文档更新完成度至 97%，同步整体结构说明与文件职责描述，确保前端状态层的职责边界更加清晰。

## 2025-10-14 第 25 步
- 将团队、日期、成员列表与排班矩阵的归一化逻辑迁移至新建的 `app/core/Validation.php`，让各模块可以单独复用校验工具。
- `app/core/Rules.php` 聚焦组合型规则与保存流程校验，避免重复维护底层清洗函数。
- `app/api/progress.php` 改为直接依赖 `Validation.php` 以获取团队归一化能力，整体职责链路更加清晰。

### 拆分总体进度速览
- **后端接口**：`schedule.php`、`versions.php`、`auth.php`、`org_config.php`、`progress.php` 均已独立，下一步评估导出/统计专用接口及鉴权强化。
- **核心算法层**：`Scheduler.php`（自动排班入口占位）、`History.php`（历史统计）、`Rules.php`、`Validation.php`、`DTO.php`、`Utils.php` 已抽离，`Repository.php` 统一管理排班版本数据访问，`OrgConfig.php`、`Storage.php`/`Progress.php` 与 `Exporter.php` 划分了配置、日志与导出职责。
- **前端静态资源**：`state.js` 负责常量、校验、进度与配置等状态 Hook；`ui.js` 覆盖所有 React 视图；`api.js` 管理请求封装；`main.js` 仅串联业务流程与组件。
- **配置与存储**：`config/app.php`、`storage/` 结构稳定，进度日志已落地至 JSONL，仍需完善导出/备份策略。
- **完成度**：约 98%，后端与前端主体拆分完成，后续聚焦日志可视化、统计面板与权限策略细化。

### 现有程序结构与职责（第 25 步更新）

| 文件 | 核心作用 | 备注/注释 |
| --- | --- | --- |
| `index.php` | 后端入口，统一派发 API 与静态页面 | 处理 404/OPTIONS，所有接口在此注册 |
| `app/bootstrap.php` | 初始化配置、时区、数据库路径 | 被入口与 API 引用，确保环境一致 |
| `app/config/app.php` | 全局配置项 | 包含时区、数据库与日志目录等常量 |
| `app/api/schedule.php` | 排班读取/保存接口 | 调用核心规则校验并写入进度日志，返回 JSON |
| `app/api/versions.php` | 历史版本接口 | 管理版本查询、导出、删除与导入 |
| `app/api/progress.php` | 调度进度接口 | 读取/追加 `storage/logs/progress.jsonl` |
| `app/api/auth.php` | 登录占位接口 | 维持前端登录流程所需的固定响应 |
| `app/api/org_config.php` | 组织配置接口 | 负责组织配置的获取与持久化 |
| `app/core/Utils.php` | 核心工具集 | 提供 SQLite 连接、日期处理等通用能力 |
| `app/core/OrgConfig.php` | 组织配置核心模块 | 规范配置载荷并负责 JSON 编解码与数据库写入 |
| `app/core/Http.php` | HTTP 工具模块 | 解析 JSON 请求体，统一 JSON 成功/错误响应 |
| `app/core/Storage.php` | 存储目录辅助 | 解析数据库同级目录并自动创建 logs/exports 等路径 |
| `app/core/Progress.php` | 进度日志工具 | 负责进度 JSONL 的读取与写入 |
| `app/core/Rules.php` | 业务规则模块 | 组合排班保存时的整体验证流程 |
| `app/core/Validation.php` | 数据归一化工具 | 提供团队、日期、成员列表与排班矩阵的基础清洗函数 |
| `app/core/DTO.php` | 数据装配模块 | 将数据库结果转换为排班 DTO/快照 |
| `app/core/Scheduler.php` | 排班算法入口 | 保留自动排班占位实现，后续扩展核心算法 |
| `app/core/History.php` | 历史统计模块 | 负责排班历史数据的统计与概览生成 |
| `app/core/Repository.php` | 数据访问仓储 | 封装排班版本的查询、插入、删除与列表逻辑 |
| `app/core/Exporter.php` | 导出工具模块 | 构建表格矩阵并输出 XLSX/CSV 附件 |
| `app/core/ScheduleService.php` | 排班服务模块 | 汇总排班查询与保存流程，处理乐观锁并追加进度日志 |
| `app/public/index.html` | 前端入口页面 | 负责加载 React、Babel 以及拆分后的脚本资源 |
| `app/public/js/api.js` | 前端请求层 | 暴露 `apiGet`、`apiPost`、进度日志工具等方法 |
| `app/public/js/state.js` | 状态与业务工具 | 提供常量、校验、调度流程与进度/配置等 `use*` Hook |
| `app/public/js/ui.js` | UI 组件库 | 聚合导航、排班表格、员工管理等 React 组件 |
| `app/public/js/main.js` | 应用入口 | 消费状态 Hook，负责事件处理与视图组合 |
| `docs/progress.md` | 拆分记录 | 追踪阶段性成果、目录结构与完成度 |
