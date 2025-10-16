// UI 组件模块：集中存放可复用的 React 视图组件

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

// 导出给入口脚本使用
window.AppUI = { Icon, Spinner, PillBtn, Section };
