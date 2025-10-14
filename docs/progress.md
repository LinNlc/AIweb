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
