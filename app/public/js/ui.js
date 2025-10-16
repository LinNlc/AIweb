// UI 组件模块：集中存放可复用的 React 视图组件
const { useState, useEffect, useMemo, useRef } = React;

// 基础图标集合，供业务面板引用
const Icon = ({ name, className = '' }) => {
  const common = 'w-5 h-5';
  switch (name) {
    case 'grid':
      return <svg className={`${common} ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9h8V3H3v6zm10 12h8v-6h-8v6zM3 21h8v-6H3v6zm10-12h8V3h-8v6z" strokeWidth="1.5"/></svg>;
    case 'users':
      return <svg className={`${common} ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zM8 13c-2.67 0-8 1.34-8 4v3h10v-3c0-1.1.9-2 2-2h4c.74 0 1.4.4 1.74 1" strokeWidth="1.5"/></svg>;
    case 'magic':
      return <svg className={`${common} ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 21l9-9M14 7l3-3M11 10l3-3" strokeWidth="1.5"/></svg>;
    case 'chart':
      return <svg className={`${common} ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 3v18h18M7 13v5M12 9v9M17 6v12" strokeWidth="1.5"/></svg>;
    case 'history':
      return <svg className={`${common} ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 8v5l3 3M3 12a9 9 0 1 0 9-9 9 9 0 0 0-9 9zm0 0h3" strokeWidth="1.5"/></svg>;
    case 'cog':
      return <svg className={`${common} ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V22a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1 1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a2 2 0 0 1-1.82.33 1.65 1.65 0 0 0-1.51 1H2a2 2 0 0 1 0-4h.09c-.49.2-.95.52-1.15 1z" strokeWidth="1"/></svg>;
    case 'lock':
      return <svg className={`${common} ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>;
    default:
      return null;
  }
};

// 旋转加载图标
const Spinner = ({ className = 'w-4 h-4 text-white' }) => (
  <svg className={`animate-spin ${className}`} viewBox="0 0 24 24" fill="none">
    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
    <path className="opacity-75" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" fill="currentColor"></path>
  </svg>
);

// 彩色按钮，保持与旧版一致的动画与禁用态
const colorClasses = {
  indigo: 'bg-indigo-600 hover:bg-indigo-500',
  emerald: 'bg-emerald-600 hover:bg-emerald-500',
  sky: 'bg-sky-600 hover:bg-sky-500',
  gray: 'bg-gray-800 hover:bg-gray-700'
};

const PillBtn = ({ children, onClick, color = 'indigo', type = 'button', disabled = false, title = '' }) => (
  <button
    type={type}
    disabled={disabled}
    onClick={onClick}
    title={title}
    className={`group relative overflow-hidden inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-white ${disabled ? 'bg-gray-300 cursor-not-allowed' : (colorClasses[color] || colorClasses.indigo)} transition-transform duration-200 ${disabled ? '' : 'hover:-translate-y-0.5 active:translate-y-0'} shadow-md`}
  >
    <span className="relative z-10">{children}</span>
    {!disabled && <span className="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></span>}
  </button>
);

// 模块化的分区容器
const Section = ({ title, children, right }) => (
  <div className="bg-white rounded-2xl shadow p-4 mb-4 animate-fade-in">
    <div className="flex items-center justify-between mb-3">
      <h2 className="text-lg font-semibold">{title}</h2>
      {right}
    </div>
    {children}
  </div>
);

// 侧边导航按钮，支持高亮与图标展示
const MenuButton = ({ active, icon, children, onClick }) => (
  <button
    type="button"
    onClick={onClick}
    className={`w-full flex items-center gap-3 rounded-xl px-3 py-2 mb-1 transition ${
      active ? 'bg-indigo-100 text-indigo-700 shadow-inner' : 'hover:bg-gray-100 text-gray-600'
    }`}
  >
    <Icon name={icon} className="w-4 h-4" />
    <span className="text-sm flex-1 text-left">{children}</span>
  </button>
);

// 左侧功能导航栏，悬停展开，点击切换标签页
const SideDock = ({ tab, setTab, menuPerms }) => {
  const [open, setOpen] = useState(false);
  const items = [
    { key: 'grid', icon: 'grid', label: '排班表格' },
    { key: 'batch', icon: 'magic', label: '自动分配班次' },
    { key: 'album', icon: 'magic', label: '专辑审核自动排班' },
    { key: 'users', icon: 'users', label: '员工管理' },
    { key: 'stats', icon: 'chart', label: '统计与对齐' },
    { key: 'history', icon: 'history', label: '历史版本' },
    { key: 'settings', icon: 'cog', label: '设置 / 导出' },
    { key: 'roles', icon: 'lock', label: '角色设置' }
  ];
  return (
    <div
      className="dock fixed left-0 top-16 bottom-6 w-11 z-40 flex items-center"
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
    >
      <div
        className={`dock-panel absolute left-2 top-0 bottom-0 w-60 transform transition-transform duration-200 ${
          open ? 'translate-x-0' : '-translate-x-[115%]'
        } bg-white rounded-xl shadow p-2 border`}
      >
        {items
          .filter((item) => menuPerms?.[item.key]?.visible)
          .map((item) => (
            <MenuButton
              key={item.key}
              active={tab === item.key}
              icon={item.icon}
              onClick={() => setTab(item.key)}
            >
              {item.label}
            </MenuButton>
          ))}
      </div>
      <div
        className="dock-tab absolute right-[-12px] top-1/2 -translate-y-1/2 w-6 h-18 rounded-r-lg flex items-center justify-center bg-white text-gray-700 cursor-pointer shadow"
        onClick={() => setOpen((v) => !v)}
      >
        <svg viewBox="0 0 20 20" className="w-4 h-4">
          <path d="M12 4l-6 6 6 6" fill="none" stroke="currentColor" strokeWidth="2" />
        </svg>
      </div>
    </div>
  );
};

// 团队切换器，下拉选择团队，支持跳转到角色管理
const TeamSwitcher = ({ teams, value, onChange, disabled, onManage, canManage }) => {
  const hasTeams = teams && teams.length > 0;
  return (
    <div className="flex items-center gap-2">
      <select
        className={`border rounded px-3 py-1.5 ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}`}
        value={hasTeams ? value : ''}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled || !hasTeams}
      >
        {hasTeams ? (
          teams.map((team) => (
            <option key={team.id} value={team.id}>
              {team.name}
            </option>
          ))
        ) : (
          <option value="">暂无团队</option>
        )}
      </select>
      {canManage && (
        <button
          type="button"
          onClick={onManage}
          className="p-1.5 rounded-full border text-gray-500 hover:text-indigo-600 hover:border-indigo-300"
          title="管理团队与权限"
        >
          <Icon name="cog" />
        </button>
      )}
    </div>
  );
};

// 登录弹窗，支持本地账号与后端校验
const LoginModal = ({ onSuccess, accounts }) => {
  const { apiPost } = window.AppAPI || {};
  const { verifyPassword } = window.AppState || {};
  const [username, setUsername] = useState('admin');
  const [password, setPassword] = useState('admin');
  const [err, setErr] = useState('');
  const [loading, setLoading] = useState(false);

  async function doLogin(e) {
    e.preventDefault();
    setErr('');
    const accountEntry = Array.isArray(accounts) ? accounts.find((acc) => acc.username === username) : null;
    if (accountEntry && username !== 'admin') {
      if (!verifyPassword || !verifyPassword(accountEntry.passwordHash, password)) {
        setErr('密码错误');
        return;
      }
      onSuccess({
        username: accountEntry.username,
        display_name: accountEntry.displayName || accountEntry.username,
        role: accountEntry.role,
        localAccount: true
      });
      return;
    }
    try {
      setLoading(true);
      if (!apiPost) {
        throw new Error('请求模块未初始化');
      }
      const res = await apiPost('/login', { username, password });
      onSuccess(res.user);
    } catch (ex) {
      setErr(ex.message || '登录失败');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="fixed inset-0 bg-black/20 backdrop-blur flex items-center justify-center z-50">
      <form onSubmit={doLogin} className="bg-white rounded-2xl shadow-xl p-6 w-[360px] animate-fade-in">
        <h2 className="text-lg font-semibold mb-4 text-center">管理员登录</h2>
        <div className="space-y-3">
          <div>
            <label className="block text-sm text-gray-600 mb-1">用户名</label>
            <input
              className="w-full border rounded px-3 py-2"
              value={username}
              disabled={loading}
              onCompositionStart={() => {}}
              onCompositionEnd={(e) => setUsername(e.target.value)}
              onChange={(e) => setUsername(e.target.value)}
            />
          </div>
          <div>
            <label className="block text-sm text-gray-600 mb-1">密码</label>
            <input
              type="password"
              className="w-full border rounded px-3 py-2"
              value={password}
              disabled={loading}
              onCompositionStart={() => {}}
              onCompositionEnd={(e) => setPassword(e.target.value)}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>
          {err && <div className="text-sm text-red-600">{err}</div>}
          <button
            type="submit"
            disabled={loading}
            className={`w-full rounded-full text-white py-2 transition flex items-center justify-center gap-2 ${
              loading ? 'bg-indigo-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-500'
            }`}
          >
            {loading && <Spinner className="w-4 h-4 text-white" />}
            <span>登录</span>
          </button>
        </div>
      </form>
    </div>
  );
};

// 人员批量选择列表，提供搜索与批量勾选功能
const EmployeeChecklist = ({ employees, batchChecked, setBatchChecked, editingLocked }) => {
  const [keyword, setKeyword] = useState('');
  const filtered = useMemo(() => {
    const kw = keyword.trim().toLowerCase();
    if (!kw) {
      return employees;
    }
    return employees.filter((name) => name.toLowerCase().includes(kw));
  }, [keyword, employees]);
  const listRef = useRef(null);

  const withScrollRestore = (cb) => {
    const node = listRef.current;
    const top = node ? node.scrollTop : 0;
    const left = node ? node.scrollLeft : 0;
    cb();
    requestAnimationFrame(() => {
      const current = listRef.current;
      if (current) {
        current.scrollTop = top;
        current.scrollLeft = left;
      }
    });
  };

  const selectedCount = filtered.filter((name) => !!batchChecked[name]).length;

  return (
    <div className="border rounded-xl p-3">
      <input
        className="border rounded px-2 py-1 text-sm w-full mb-2"
        placeholder="搜索姓名…"
        value={keyword}
        onChange={(e) => setKeyword(e.target.value)}
      />
      <div className="flex items-center justify-between text-xs text-gray-600 mb-2">
        <div>
          当前筛选：{filtered.length} 人；已选 {selectedCount} 人
        </div>
        <div className="space-x-2">
          <button
            className={`px-2 py-1 rounded border ${editingLocked ? 'opacity-50 cursor-not-allowed' : ''}`}
            disabled={editingLocked}
            onClick={() =>
              withScrollRestore(() => {
                const next = { ...batchChecked };
                filtered.forEach((name) => {
                  next[name] = true;
                });
                setBatchChecked(next);
              })
            }
          >
            全选
          </button>
          <button
            className={`px-2 py-1 rounded border ${editingLocked ? 'opacity-50 cursor-not-allowed' : ''}`}
            disabled={editingLocked}
            onClick={() =>
              withScrollRestore(() =>
                setBatchChecked((prev) => {
                  const next = { ...prev };
                  filtered.forEach((name) => {
                    next[name] = !prev[name];
                  });
                  return next;
                })
              )
            }
          >
            反选
          </button>
          <button
            className={`px-2 py-1 rounded border ${editingLocked ? 'opacity-50 cursor-not-allowed' : ''}`}
            disabled={editingLocked}
            onClick={() =>
              withScrollRestore(() =>
                setBatchChecked((prev) => {
                  const next = { ...prev };
                  filtered.forEach((name) => {
                    next[name] = false;
                  });
                  return next;
                })
              )
            }
          >
            清空
          </button>
        </div>
      </div>
      <div ref={listRef} className="max-h-64 overflow-auto divide-y">
        {filtered.map((name) => (
          <label key={name} className="flex items-center gap-2 py-1">
            <input
              type="checkbox"
              disabled={editingLocked}
              checked={!!batchChecked[name]}
              onChange={() =>
                withScrollRestore(() =>
                  setBatchChecked((prev) => ({ ...prev, [name]: !prev[name] }))
                )
              }
            />
            <span className="text-sm">{name}</span>
          </label>
        ))}
      </div>
    </div>
  );
};

// 主自动排班规则配置面板
const MainRulePanel = ({
  rMin,
  rMax,
  pMin,
  pMax,
  mixMax,
  setRMin,
  setRMax,
  setPMin,
  setPMax,
  setMixMax,
  progRunning,
  onRunMainRule,
}) => (
  <div className="bg-white rounded-xl border p-3 space-y-3">
    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
      <div className="space-y-2">
        <div className="font-medium text-gray-700">每日比例（中1:白）</div>
        <div className="flex items-center gap-2">
          <span>1 :</span>
          <input
            type="number"
            step="0.05"
            min="0"
            max="3"
            className="w-24 border rounded px-2 py-1"
            value={rMin}
            onChange={(e) => setRMin(Math.max(0, Math.min(3, parseFloat(e.target.value || '0'))))}
          />
          <span>~</span>
          <input
            type="number"
            step="0.05"
            min="0"
            max="3"
            className="w-24 border rounded px-2 py-1"
            value={rMax}
            onChange={(e) => setRMax(Math.max(0, Math.min(3, parseFloat(e.target.value || '0'))))}
          />
          <span className="text-gray-400">（示例：1:0.4~0.6）</span>
        </div>
      </div>
      <div className="space-y-2">
        <div className="font-medium text-gray-700">个人比例（中1:白）</div>
        <div className="flex items-center gap-2">
          <span>1 :</span>
          <input
            type="number"
            step="0.05"
            min="0"
            max="3"
            className="w-24 border rounded px-2 py-1"
            value={pMin}
            onChange={(e) => setPMin(Math.max(0, Math.min(3, parseFloat(e.target.value || '0'))))}
          />
          <span>~</span>
          <input
            type="number"
            step="0.05"
            min="0"
            max="3"
            className="w-24 border rounded px-2 py-1"
            value={pMax}
            onChange={(e) => setPMax(Math.max(0, Math.min(3, parseFloat(e.target.value || '0'))))}
          />
          <span className="text-gray-400">（示例：1:0.4~0.6）</span>
        </div>
      </div>
    </div>
    <div className="space-y-2">
      <div className="font-medium text-gray-700">混合周期上限（比例）</div>
      <div className="flex items-center gap-2 text-sm">
        <span>混合周期 ≤ 总周期 ×</span>
        <input
          type="number"
          step="0.05"
          min="0"
          max="1"
          className="w-24 border rounded px-2 py-1"
          value={mixMax}
          onChange={(e) => setMixMax(Math.max(0, Math.min(1, parseFloat(e.target.value || '0'))))}
        />
        <span className="text-gray-400">（例如：0.5 表示有白且有中的周期不超过一半）</span>
      </div>
    </div>

    <div className="flex flex-wrap items-center gap-3">
      <PillBtn color="indigo" onClick={onRunMainRule} disabled={progRunning}>
        开始自动排班
      </PillBtn>
    </div>

    <div className="text-xs text-gray-500">
      规则说明：① 全程禁止生成“中1→白”相邻；② 严格遵守最多 6 天连续上班；③ 混合周期（白+中1 同在一周期）不超过设定比例；④ 夜/中2 不参与本算法但保留；⑤ 多轮“按天→按人”收敛，优先确保你的目标约束而非速度。
    </div>
  </div>
);

// 夜班辅助工具面板
const NightPanel = ({
  nightWindows,
  setNightWindows,
  nightOverride,
  setNightOverride,
  nightRules,
  setNightRules,
  start,
  end,
  onAssignNight,
  editingLocked,
  hasSelection,
}) => (
  <div className="bg-white rounded-xl border p-3">
    <h4 className="font-medium mb-2">夜班窗口（每天尽量有 夜 + 中2）</h4>
    <div className="space-y-2">
      {nightWindows.map((win, idx) => (
        <div key={idx} className="flex flex-wrap items-end gap-2">
          <label className="text-sm">
            开始
            <input
              type="date"
              value={win.s}
              onChange={(e) =>
                setNightWindows((arr) => {
                  const next = [...arr];
                  next[idx] = { ...next[idx], s: e.target.value };
                  return next;
                })
              }
              className="border rounded px-2 py-1 ml-1"
            />
          </label>
          <label className="text-sm">
            结束
            <input
              type="date"
              value={win.e}
              onChange={(e) =>
                setNightWindows((arr) => {
                  const next = [...arr];
                  next[idx] = { ...next[idx], e: e.target.value };
                  return next;
                })
              }
              className="border rounded px-2 py-1 ml-1"
            />
          </label>
          <button className="px-2 py-1 border rounded" onClick={() => setNightWindows((arr) => arr.filter((_, i) => i !== idx))}>
            删除
          </button>
        </div>
      ))}
      <button className="px-3 py-1.5 border rounded" onClick={() => setNightWindows((arr) => [...arr, { s: start, e: end }])}>
        新增一段
      </button>
    </div>

    <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
      <label className="inline-flex items-center gap-2">
        <input type="checkbox" checked={nightOverride} onChange={(e) => setNightOverride(e.target.checked)} /> 应用夜/中2时允许覆盖现有班次
      </label>
      <label className="inline-flex items-center gap-2">
        <input
          type="checkbox"
          checked={nightRules.prioritizeInterval}
          onChange={(e) => setNightRules((prev) => ({ ...prev, prioritizeInterval: e.target.checked }))}
        />
        夜班按间隔优先分配
      </label>
      <label className="inline-flex items-center gap-2">
        <input
          type="checkbox"
          checked={nightRules.restAfterNight}
          onChange={(e) => setNightRules((prev) => ({ ...prev, restAfterNight: e.target.checked }))}
        />
        夜班后休息 2 天
      </label>
      <label className="inline-flex items-center gap-2">
        <input
          type="checkbox"
          checked={nightRules.enforceRestCap}
          onChange={(e) => setNightRules((prev) => ({ ...prev, enforceRestCap: e.target.checked }))}
        />
        夜后休息不超过 2 天
      </label>
      <label className="inline-flex items-center gap-2">
        <input
          type="checkbox"
          checked={nightRules.restAfterMid2}
          onChange={(e) => setNightRules((prev) => ({ ...prev, restAfterMid2: e.target.checked }))}
        />
        中2 后休息 2 天
      </label>
      <label className="inline-flex items-center gap-2">
        <input
          type="checkbox"
          checked={nightRules.allowDoubleMid2}
          onChange={(e) => setNightRules((prev) => ({ ...prev, allowDoubleMid2: e.target.checked }))}
        />
        允许连续 2 个中2
      </label>
      <label className="inline-flex items-center gap-2">
        <input
          type="checkbox"
          checked={nightRules.allowNightDay4}
          onChange={(e) => setNightRules((prev) => ({ ...prev, allowNightDay4: e.target.checked }))}
        />
        支持周期第 4 天排夜班
      </label>
    </div>

    <div className="flex flex-wrap items-center gap-3 mt-3 text-sm">
      <PillBtn
        color="emerald"
        onClick={onAssignNight}
        title={!hasSelection ? '请先勾选人员' : editingLocked ? '执行中：只读' : ''}
      >
        AI 一键分配 夜/中2（按窗口，多段）
      </PillBtn>
      <span className="text-xs text-gray-500">规则：夜后两天必须休；夜后两天禁止该员工中2；中2后一天强制休。</span>
    </div>
  </div>
);

// 自动排班组合面板，整合人员选择 + 规则 + 夜班工具
const BatchAssignSection = ({
  employees,
  batchChecked,
  setBatchChecked,
  editingLocked,
  progRunning,
  rMin,
  rMax,
  pMin,
  pMax,
  mixMax,
  setRMin,
  setRMax,
  setPMin,
  setPMax,
  setMixMax,
  nightWindows,
  setNightWindows,
  nightOverride,
  setNightOverride,
  nightRules,
  setNightRules,
  start,
  end,
  onRunMainRule,
  onAssignNight,
  hasSelection,
}) => (
  <Section
    title="自动分配班次"
    right={
      editingLocked ? (
        <span className="text-rose-600 text-sm inline-flex items-center gap-1">
          <Icon name="lock" />执行中：只读浏览
        </span>
      ) : null
    }
  >
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
      <div className="lg:col-span-1">
        <h3 className="font-medium mb-2">选择人员</h3>
        <EmployeeChecklist
          employees={employees}
          batchChecked={batchChecked}
          setBatchChecked={setBatchChecked}
          editingLocked={editingLocked}
        />
      </div>
      <div className="lg:col-span-2 space-y-4">
        <MainRulePanel
          rMin={rMin}
          rMax={rMax}
          pMin={pMin}
          pMax={pMax}
          mixMax={mixMax}
          setRMin={setRMin}
          setRMax={setRMax}
          setPMin={setPMin}
          setPMax={setPMax}
          setMixMax={setMixMax}
          progRunning={progRunning}
          onRunMainRule={onRunMainRule}
        />
        <NightPanel
          nightWindows={nightWindows}
          setNightWindows={setNightWindows}
          nightOverride={nightOverride}
          setNightOverride={setNightOverride}
          nightRules={nightRules}
          setNightRules={setNightRules}
          start={start}
          end={end}
          onAssignNight={onAssignNight}
          editingLocked={editingLocked}
          hasSelection={hasSelection}
        />
      </div>
    </div>
  </Section>
);

// 月份输入框：支持键盘输入自动格式化
const MonthInput = React.memo(function MonthInputComponent({
  value,
  onChange,
  disabled = false,
  className = '',
  placeholder = 'YYYY-MM'
}) {
  const [draft, setDraft] = useState(() => value || '');
  useEffect(() => {
    setDraft(value || '');
  }, [value]);

  const format = (raw) => {
    const digits = String(raw || '').replace(/[^0-9]/g, '');
    let next = digits.slice(0, 4);
    if (digits.length > 4) {
      next += '-' + digits.slice(4, 6);
    }
    return next;
  };

  const commit = (input) => {
    if (!onChange) return;
    if (!input) {
      onChange('');
      return;
    }
    const match = input.match(/^(\d{4})(?:-(\d{1,2}))?$/);
    if (!match) {
      onChange(value || '');
      return;
    }
    const year = match[1];
    const monthRaw = match[2] ?? '';
    const monthNum = monthRaw ? parseInt(monthRaw, 10) : NaN;
    const month = Number.isFinite(monthNum) ? Math.min(12, Math.max(1, monthNum)) : 1;
    const normalized = `${year}-${String(month).padStart(2, '0')}`;
    onChange(normalized);
    setDraft(normalized);
  };

  const handleChange = (e) => {
    if (disabled) return;
    const formatted = format(e.target.value);
    setDraft(formatted);
    if (formatted.length >= 7) {
      commit(formatted);
    } else if (formatted === '') {
      if (onChange) onChange('');
    }
  };

  const handleBlur = () => {
    if (disabled) return;
    if (!draft) {
      if (onChange) onChange('');
      return;
    }
    commit(draft);
  };

  return (
    <input
      type="text"
      inputMode="numeric"
      pattern="\d{4}-\d{2}"
      className={className}
      value={draft}
      placeholder={placeholder}
      disabled={disabled}
      onChange={handleChange}
      onBlur={handleBlur}
    />
  );
});

// 导出给入口脚本使用
window.AppUI = { Icon, Spinner, PillBtn, Section, SideDock, TeamSwitcher, LoginModal, MonthInput, BatchAssignSection };
