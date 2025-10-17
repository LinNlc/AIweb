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

### 拆分总体进度速览
- **后端接口**：`schedule.php`、`versions.php`、`auth.php`、`org_config.php` 已独立；`progress.php` 待按需补充。
- **核心算法层**：`Scheduler.php`、`Rules.php`、`DTO.php`、`Utils.php` 完成抽离并在 API 层复用。
- **前端静态资源**：`main.js` 仅负责状态编排；`ui.js` 已承载通用组件、排班表格、批量排班面板以及历史/设置分区；`api.js`、`state.js` 分别负责请求与规则算法。
- **配置与存储**：`config/app.php`、`storage/` 结构稳定，等待后续细化日志与导出模块。
