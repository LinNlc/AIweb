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
