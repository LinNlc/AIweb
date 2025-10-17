// 状态模块：承载前端用到的常量、校验与调度算法工具
const { useState, useEffect, useRef, useCallback } = React;
const WN = ['日', '一', '二', '三', '四', '五', '六'];
const today = new Date();
const SHIFT_TYPES = ['白', '中1', '中2', '夜', '休'];
const SHIFT_WORK_TYPES = ['白', '中1', '中2', '夜'];

function fmt(d) { return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); }
function monthRangeOf(dateObj) { const y = dateObj.getFullYear(); const m = dateObj.getMonth(); const start = new Date(y, m, 1); const end = new Date(y, m + 1, 0); return [fmt(start), fmt(end)]; }
function enumerateDates(start, end) { const out = []; let d = new Date(start); const e = new Date(end); while (d <= e) { out.push(fmt(d)); d.setDate(d.getDate() + 1); } return out; }
function dow(ymd) { return new Date(ymd).getDay(); }
function dateAdd(ymd, delta) { const d = new Date(ymd); d.setDate(d.getDate() + delta); return fmt(d); }
function monthListBetween(start, end) {
  const startDate = new Date(start);
  const endDate = new Date(end);
  const cur = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
  const out = [];
  while (cur <= endDate) {
    const key = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}`;
    const label = `${cur.getFullYear()}年${String(cur.getMonth() + 1).padStart(2, '0')}月`;
    const monthStart = fmt(cur);
    const monthEnd = fmt(new Date(cur.getFullYear(), cur.getMonth() + 1, 0));
    out.push({ key, label, start: monthStart, end: monthEnd });
    cur.setMonth(cur.getMonth() + 1);
  }
  return out;
}
function monthKeyToSpan(monthKey) {
  if (!monthKey || !/\d{4}-\d{2}/.test(monthKey)) return null;
  const [y, m] = monthKey.split('-').map(Number);
  if (Number.isNaN(y) || Number.isNaN(m)) return null;
  const start = fmt(new Date(y, m - 1, 1));
  const end = fmt(new Date(y, m, 0));
  return { start, end };
}
function weekStartKey(day) {
  const d = new Date(day);
  const mondayIdx = (d.getDay() + 6) % 7;
  d.setDate(d.getDate() - mondayIdx);
  return fmt(d);
}
const isWork = (v) => !!(v && v !== '休');
function getVal(data, day, emp) { return (data[day] || {})[emp] || ''; }
function setVal(map, day, emp, val) { const row = { ...(map[day] || {}) }; row[emp] = val; map[day] = row; }
function countByEmpInRange(employees, data, start, end) {
  const dates = enumerateDates(start, end);
  const counter = {};
  for (const e of employees) {
    counter[e] = { 总: 0 };
    for (const s of SHIFT_WORK_TYPES) { counter[e][s] = 0; }
  }
  for (const d of dates) {
    const row = data[d] || {};
    for (const e of employees) {
      const v = row[e] || '';
      if (!v || v === '休') continue;
      counter[e]['总']++;
      if (SHIFT_WORK_TYPES.includes(v)) counter[e][v]++;
    }
  }
  return counter;
}
function countRunLeft(data, d, e) { let c = 0; for (let i = -1; i >= -10; i--) { const ymd = dateAdd(d, i); const v = getVal(data, ymd, e); if (isWork(v)) c++; else break; } return c; }
function countRunRight(data, d, e) { let c = 0; for (let i = 1; i <= 10; i++) { const ymd = dateAdd(d, i); const v = getVal(data, ymd, e); if (isWork(v)) c++; else break; } return c; }
function wouldExceed6(data, e, d, newV) { if (!isWork(newV)) return false; const L = countRunLeft(data, d, e); const R = countRunRight(data, d, e); return (L + 1 + R) > 6; }
const REST_PAIRS = ['12', '23', '34', '56', '71'];
const MENU_KEYS = ['grid', 'batch', 'album', 'users', 'stats', 'history', 'settings', 'roles'];
const defaultShiftColors = { '白': '#eef2ff', '中1': '#e0f2fe', '中2': '#cffafe', '夜': '#fee2e2', '休': '#f3f4f6' };
const defaultStaffingAlerts = {
  total: { threshold: 0, lowColor: '#fee2e2', highColor: '#dcfce7' },
  white: { threshold: 0, lowColor: '#fef3c7', highColor: '#bfdbfe' },
  mid1: { threshold: 0, lowColor: '#fef3c7', highColor: '#bae6fd' }
};
const REST_PREF_STORAGE_KEY = 'scheduler_restprefs_store_v2';
const normalizeRestPair = (pair) => pair === '17' ? '71' : pair;
function sanitizeRestPairValue(val) {
  if (!val) return '';
  const digits = String(val).replace(/\D/g, '');
  if (digits.length !== 2) return '';
  const normalized = normalizeRestPair(digits);
  return REST_PAIRS.includes(normalized) ? normalized : digits;
}
function sanitizeRestPrefsMap(map) {
  const next = {};
  Object.entries(map || {}).forEach(([emp, raw]) => {
    const cleaned = sanitizeRestPairValue(raw);
    if (cleaned) next[emp] = cleaned;
  });
  return next;
}
function normalizeStaffingAlerts(raw) {
  const base = JSON.parse(JSON.stringify(defaultStaffingAlerts));
  if (!raw || typeof raw !== 'object') return base;
  ['total', 'white', 'mid1'].forEach(key => {
    const entry = raw[key];
    if (!entry || typeof entry !== 'object') return;
    const threshold = Number(entry.threshold);
    if (Number.isFinite(threshold)) base[key].threshold = threshold;
    if (typeof entry.lowColor === 'string' && entry.lowColor.trim()) base[key].lowColor = entry.lowColor;
    if (typeof entry.highColor === 'string' && entry.highColor.trim()) base[key].highColor = entry.highColor;
  });
  return base;
}
function contrastColor(hex) {
  if (typeof hex !== 'string') return '#111827';
  const norm = hex.replace('#', '');
  if (norm.length !== 6) return '#111827';
  const r = parseInt(norm.slice(0, 2), 16);
  const g = parseInt(norm.slice(2, 4), 16);
  const b = parseInt(norm.slice(4, 6), 16);
  if ([r, g, b].some(v => Number.isNaN(v))) return '#111827';
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return luminance > 0.6 ? '#111827' : '#f9fafb';
}
function styleForStaffing(count, rule) {
  if (!rule) return {};
  const { threshold, lowColor, highColor } = rule;
  const targetColor = (Number.isFinite(threshold) && count <= threshold) ? lowColor : highColor;
  if (!targetColor) return {};
  return {
    backgroundColor: targetColor,
    color: contrastColor(targetColor),
    borderRadius: '999px',
    padding: '0 0.35rem'
  };
}
function normalizeNightRules(raw) {
  const defaults = {
    prioritizeInterval: false,
    restAfterNight: true,
    enforceRestCap: true,
    restAfterMid2: true,
    allowDoubleMid2: false,
    allowNightDay4: false
  };
  if (!raw || typeof raw !== 'object') return { ...defaults };
  const next = { ...defaults };
  if (typeof raw.prioritizeInterval === 'boolean') next.prioritizeInterval = raw.prioritizeInterval;
  if (typeof raw.restAfterNight === 'boolean') next.restAfterNight = raw.restAfterNight;
  if (typeof raw.enforceRestCap === 'boolean') next.enforceRestCap = raw.enforceRestCap;
  if (typeof raw.restAfterMid2 === 'boolean') next.restAfterMid2 = raw.restAfterMid2;
  if (typeof raw.allowDoubleMid2 === 'boolean') next.allowDoubleMid2 = raw.allowDoubleMid2;
  if (typeof raw.allowNightDay4 === 'boolean') next.allowNightDay4 = raw.allowNightDay4;
  if (raw.rest2AfterNight && typeof raw.rest2AfterNight === 'object') {
    if (typeof raw.rest2AfterNight.enabled === 'boolean') next.restAfterNight = raw.rest2AfterNight.enabled;
    if (typeof raw.rest2AfterNight.mandatory === 'boolean') next.enforceRestCap = raw.rest2AfterNight.mandatory;
  }
  return next;
}
function hashPassword(raw) {
  if (typeof raw !== 'string') return '';
  try { return btoa(unescape(encodeURIComponent(raw))); } catch { return raw; }
}
function verifyPassword(hash, raw) {
  if (!hash) return raw === '';
  return hash === hashPassword(raw);
}
function defaultMenuPerms(overrides) {
  const base = {};
  MENU_KEYS.forEach(key => { base[key] = { visible: true, editable: true }; });
  base.roles.visible = false;
  if (overrides) {
    Object.entries(overrides).forEach(([key, conf]) => {
      if (!base[key]) return;
      base[key] = { ...base[key], ...conf };
    });
  }
  return base;
}
function normalizeMenuPerms(perms) {
  const base = defaultMenuPerms();
  if (!perms || typeof perms !== 'object') return base;
  MENU_KEYS.forEach(key => {
    const conf = perms[key];
    if (conf && typeof conf === 'object') {
      if (typeof conf.visible === 'boolean') base[key].visible = conf.visible;
      if (typeof conf.editable === 'boolean') base[key].editable = conf.editable;
    }
  });
  return base;
}
function ensureUniqueId(base, existing) {
  let id = base;
  let idx = 1;
  while (existing.has(id)) {
    id = `${base}-${idx++}`;
  }
  existing.add(id);
  return id;
}
function sanitizeIdFromName(name) {
  const base = (name || 'team').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  return base || 'team';
}
function normalizeTeamEntry(team, existing) {
  const inputId = (team && typeof team.id === 'string' && team.id.trim()) ? team.id.trim() : sanitizeIdFromName(team?.name || '');
  const id = ensureUniqueId(inputId, existing);
  const features = team?.features && typeof team.features === 'object' ? team.features : {};
  return {
    id,
    name: (team && typeof team.name === 'string' && team.name.trim()) ? team.name.trim() : `团队${existing.size}`,
    remark: (team && typeof team.remark === 'string') ? team.remark : '',
    features: { albumScheduler: features.albumScheduler !== false }
  };
}
function defaultOrgConfig() {
  return {
    teams: [
      { id: 'default', name: '默认团队', remark: '', features: { albumScheduler: true } }
    ],
    accounts: [
      {
        username: 'admin',
        displayName: '超级管理员',
        role: 'super',
        passwordHash: '',
        menus: defaultMenuPerms({ roles: { visible: true } }),
        teamAccess: ['*']
      }
    ]
  };
}
function normalizeOrgConfig(raw) {
  const defaults = defaultOrgConfig();
  const teamsInput = Array.isArray(raw?.teams) && raw.teams.length ? raw.teams : defaults.teams;
  const existing = new Set();
  const teams = teamsInput.map(team => normalizeTeamEntry(team, existing));
  if (!teams.length) {
    const fallback = normalizeTeamEntry(defaults.teams[0], existing);
    teams.push(fallback);
  }
  const teamIds = new Set(teams.map(t => t.id));
  const accounts = [];
  if (Array.isArray(raw?.accounts)) {
    raw.accounts.forEach(acc => {
      if (!acc || typeof acc.username !== 'string') return;
      const username = acc.username.trim();
      if (!username || username === 'admin') return;
      const menus = normalizeMenuPerms(acc.menus);
      const access = Array.isArray(acc.teamAccess) && acc.teamAccess.length
        ? Array.from(new Set(acc.teamAccess.filter(id => id === '*' || teamIds.has(id))))
        : ['*'];
      accounts.push({
        username,
        displayName: typeof acc.displayName === 'string' ? acc.displayName : '',
        role: acc.role === 'manager' ? 'manager' : 'guest',
        passwordHash: typeof acc.passwordHash === 'string' ? acc.passwordHash : '',
        menus,
        teamAccess: access
      });
    });
  }
  const adminMenus = defaultMenuPerms({ roles: { visible: true } });
  const adminAccount = {
    username: 'admin',
    displayName: '超级管理员',
    role: 'super',
    passwordHash: '',
    menus: adminMenus,
    teamAccess: ['*']
  };
  accounts.push(adminAccount);
  return { teams, accounts };
}
function createEmptyTeamState(rangeStart, rangeEnd) {
  return {
    start: rangeStart,
    end: rangeEnd,
    employees: [],
    data: {},
    versionId: null,
    shiftColors: { ...defaultShiftColors },
    staffingAlerts: normalizeStaffingAlerts(),
    adminDays: 0,
    restPrefs: {},
    nightWindows: [{ s: rangeStart, e: rangeEnd }],
    nightOverride: true,
    nightRules: normalizeNightRules(),
    rMin: 0.3,
    rMax: 0.7,
    pMin: 0.3,
    pMax: 0.7,
    mixMax: 1,
    note: '',
    albumWhiteHour: 0.22,
    albumMidHour: 0.06,
    albumRangeStartMonth: rangeStart.slice(0, 7),
    albumRangeEndMonth: rangeEnd.slice(0, 7),
    albumMaxDiff: 0.5,
    albumAssignments: {},
    albumAutoNote: '',
    albumSelected: {},
    batchChecked: {},
    albumHistory: [],
    historyProfile: null,
    yearlyOptimize: false
  };
}

const ORG_STORAGE_KEY = 'scheduler_org_config_v1';
const TEAM_STATE_STORAGE_KEY = 'scheduler_team_states_v1';
const pairToSet = (p) => {
  const cleaned = sanitizeRestPairValue(p);
  if (cleaned.length !== 2) return new Set();
  const m = { '1': 1, '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7 };
  const set = new Set();
  const first = m[cleaned[0]];
  const second = m[cleaned[1]];
  if (typeof first === 'number') set.add(first);
  if (typeof second === 'number') set.add(second);
  return set;
};
const dayToW = (day) => ((new Date(day).getDay() + 6) % 7) + 1; // 周一=1 ... 周日=7
function isRestDayForEmp(restPrefs, emp, day) { const p = restPrefs?.[emp]; if (!p) return false; const set = pairToSet(p); const w = dayToW(day); return set.has(w); }

function buildWhiteFiveTwo({ employees, data, start, end, restPrefs }) {
  const dates = enumerateDates(start, end);
  const next = { ...data };
  for (const d of dates) {
    const r = { ...(next[d] || {}) };
    for (const e of employees) {
      const cur = r[e] || '';
      if (cur === '夜' || cur === '中2') continue;
      r[e] = isRestDayForEmp(restPrefs, e, d) ? '休' : '白';
    }
    next[d] = r;
  }
  return next;
}

function buildWorkBlocks(data, emp, start, end) {
  const days = enumerateDates(start, end);
  const blocks = [];
  let cur = [];
  for (const d of days) {
    const v = getVal(data, d, emp);
    const work = (v !== '休' && v !== '夜' && v !== '中2');
    if (work) { cur.push(d); }
    else { if (cur.length) { blocks.push(cur); cur = []; } }
  }
  if (cur.length) blocks.push(cur);
  return blocks;
}
function cyclesForEmp(data, emp, start, end) {
  const blocks = buildWorkBlocks(data, emp, start, end);
  const cycles = [];
  for (const b of blocks) {
    const k = Math.floor(b.length / 5);
    for (let i = 0; i < k; i++) { cycles.push(b.slice(i * 5, i * 5 + 5)); }
  }
  return cycles; // 每项是 5 个日期
}

function isRightEdgeWhite(data, day, emp) { const v = getVal(data, day, emp); if (v !== '白') return false; const t1 = dateAdd(day, 1); const nxt = getVal(data, t1, emp); return nxt !== '白'; }
function isLeftEdgeMid(data, day, emp) { const v = getVal(data, day, emp); if (v !== '中1') return false; const t0 = dateAdd(day, -1); const pre = getVal(data, t0, emp); return pre !== '中1'; }

function dailyMidCount(data, day, employees) { const row = data[day] || {}; let c = 0; for (const e of employees) { if (row[e] === '中1') c++; } return c; }
function dailyWhiteCount(data, day, employees) { const row = data[day] || {}; let c = 0; for (const e of employees) { if (row[e] === '白') c++; } return c; }
function empMidCountInRange(data, emp, start, end) { let c = 0; for (const d of enumerateDates(start, end)) { if (getVal(data, d, emp) === '中1') c++; } return c; }
function empWhiteCountInRange(data, emp, start, end) { let c = 0; for (const d of enumerateDates(start, end)) { if (getVal(data, d, emp) === '白') c++; } return c; }

function mixedCyclesCount(data, emp, start, end) {
  const cycles = cyclesForEmp(data, emp, start, end);
  let c = 0;
  for (const cyc of cycles) {
    let hasW = false, hasM = false;
    for (const d of cyc) {
      const v = getVal(data, d, emp);
      if (v === '白') hasW = true;
      if (v === '中1') hasM = true;
    }
    if (hasW && hasM) c++;
  }
  return c;
}

function longestRun(data, emp, start, end) {
  let max = 0, cur = 0;
  for (const d of enumerateDates(start, end)) {
    if (isWork(getVal(data, d, emp))) {
      cur++;
      max = Math.max(max, cur);
    } else {
      cur = 0;
    }
  }
  return max;
}

function trySetVal(next, day, emp, newV, { start, end, mixMaxRatio }) {
  const prev = getVal(next, day, emp);
  if (prev === newV) return true;
  if (wouldExceed6(next, emp, day, newV)) return false;
  const prevDay = dateAdd(day, -1), nextDay = dateAdd(day, 1);
  if (newV === '白' && getVal(next, prevDay, emp) === '中1') return false;
  if (newV === '中1' && getVal(next, nextDay, emp) === '白') return false;
  const tmp = JSON.parse(JSON.stringify(next));
  setVal(tmp, day, emp, newV);
  if (typeof mixMaxRatio === 'number') {
    const tot = cyclesForEmp(tmp, emp, start, end).length;
    const allow = Math.ceil(tot * mixMaxRatio - 1e-9);
    const mixed = mixedCyclesCount(tmp, emp, start, end);
    if (mixed > allow) return false;
  }
  setVal(next, day, emp, newV);
  return true;
}

function repairNoMidToWhite({ data, employees, start, end }) {
  const next = { ...data };
  for (const e of employees) {
    const ds = enumerateDates(start, end);
    for (let i = 0; i < ds.length - 1; i++) {
      const a = ds[i], b = ds[i + 1];
      const va = getVal(next, a, e), vb = getVal(next, b, e);
      if (va === '中1' && vb === '白') {
        if (!wouldExceed6(next, e, b, '中1')) { setVal(next, b, e, '中1'); }
        else { setVal(next, a, e, '白'); }
      }
    }
  }
  return next;
}

function applyAlternateByCycle({ employees, data, start, end }, mixMaxRatio = 1) {
  const next = { ...data };
  employees.forEach((e, idx) => {
    const startIsWhite = (idx % 2 === 0);
    const cycles = cyclesForEmp(next, e, start, end);
    cycles.forEach((cyc, ci) => {
      const shouldMid = startIsWhite ? (ci % 2 === 1) : (ci % 2 === 0);
      if (shouldMid) {
        for (const d of cyc) {
          if (isRightEdgeWhite(next, d, e)) trySetVal(next, d, e, '中1', { start, end, mixMaxRatio });
        }
      }
    });
  });
  return repairNoMidToWhite({ data: next, employees, start, end });
}

function clampDailyByRange({ employees, data, start, end, rMin = 0.3, rMax = 0.7, maxRounds = 300, mixMaxRatio = 1 }) {
  let cur = JSON.parse(JSON.stringify(data));
  const days = enumerateDates(start, end);
  for (let round = 0; round < maxRounds; round++) {
    let changed = false;
    for (const d of days) {
      const W = dailyWhiteCount(cur, d, employees);
      const M = dailyMidCount(cur, d, employees);
      const A = W + M; if (A === 0) continue;
      const low = Math.ceil(rMin * W);
      const high = Math.floor(rMax * W);
      if (M > high) {
        let cands = employees.filter(e => isLeftEdgeMid(cur, d, e));
        cands = cands.sort((a, b) => (empWhiteCountInRange(cur, b, start, end) > 0 ? empMidCountInRange(cur, b, start, end) / empWhiteCountInRange(cur, b, start, end) : 99) - (empWhiteCountInRange(cur, a, start, end) > 0 ? empMidCountInRange(cur, a, start, end) / empWhiteCountInRange(cur, a, start, end) : 99));
        for (const e of cands) { if (trySetVal(cur, d, e, '白', { start, end, mixMaxRatio })) { changed = true; if (dailyMidCount(cur, d, employees) <= high) break; } }
      } else if (M < low) {
        let cands = employees.filter(e => isRightEdgeWhite(cur, d, e));
        cands = cands.sort((a, b) => (empWhiteCountInRange(cur, a, start, end) > 0 ? empMidCountInRange(cur, a, start, end) / empWhiteCountInRange(cur, a, start, end) : 0) - (empWhiteCountInRange(cur, b, start, end) > 0 ? empMidCountInRange(cur, b, start, end) / empWhiteCountInRange(cur, b, start, end) : 0));
        for (const e of cands) { if (trySetVal(cur, d, e, '中1', { start, end, mixMaxRatio })) { changed = true; if (dailyMidCount(cur, d, employees) >= low) break; } }
      }
    }
    if (!changed) break;
  }
  return repairNoMidToWhite({ data: cur, employees, start, end });
}

function clampPersonByRange({ employees, data, start, end, pMin = 0.3, pMax = 0.7, maxRounds = 240, mixMaxRatio = 1 }) {
  let cur = JSON.parse(JSON.stringify(data));
  const days = enumerateDates(start, end);
  for (let round = 0; round < maxRounds; round++) {
    let changed = false;
    for (const e of employees) {
      const W = empWhiteCountInRange(cur, e, start, end);
      const M = empMidCountInRange(cur, e, start, end);
      if (W === 0 && M === 0) continue;
      const low = Math.ceil(pMin * Math.max(1, W));
      const high = Math.floor(pMax * Math.max(1, W));
      if (M > high) {
        for (const d of days) {
          if (isLeftEdgeMid(cur, d, e)) {
            if (trySetVal(cur, d, e, '白', { start, end, mixMaxRatio })) { changed = true; break; }
          }
        }
      } else if (M < low) {
        for (const d of days) {
          if (isRightEdgeWhite(cur, d, e)) {
            if (trySetVal(cur, d, e, '中1', { start, end, mixMaxRatio })) { changed = true; break; }
          }
        }
      }
    }
    if (!changed) break;
  }
  return repairNoMidToWhite({ data: cur, employees, start, end });
}

function statsForEmployee(data, emp, start, end) {
  let white = 0, mid = 0, mid2 = 0, night = 0, total = 0;
  for (const day of enumerateDates(start, end)) {
    const v = getVal(data, day, emp);
    if (!v || v === '休') continue;
    total++;
    if (v === '白') white++;
    else if (v === '中1') mid++;
    else if (v === '中2') mid2++;
    else if (v === '夜') night++;
  }
  return { white, mid, mid2, night, total };
}

function sortedByHistory(employees, historyProfile) {
  const totals = historyProfile?.shiftTotals || historyProfile?.shift_totals || {};
  return employees.slice().sort((a, b) => {
    const ta = totals[a]?.total ?? totals[a]?.总 ?? 0;
    const tb = totals[b]?.total ?? totals[b]?.总 ?? 0;
    if (ta !== tb) return ta - tb;
    const wa = totals[a]?.white ?? totals[a]?.白 ?? 0;
    const wb = totals[b]?.white ?? totals[b]?.白 ?? 0;
    if (wa !== wb) return wa - wb;
    return a.localeCompare(b, 'zh-CN');
  });
}

function adjustEmployeeSchedule({ next, emp, days, spanStart, spanEnd, targetWhite, targetMid, targetTotal, mixMaxRatio }) {
  const count = () => {
    let white = 0, mid = 0, total = 0;
    for (const day of days) {
      const v = getVal(next, day, emp);
      if (!v || v === '休') continue;
      total++;
      if (v === '白') white++;
      else if (v === '中1') mid++;
    }
    return { white, mid, total };
  };
  const tryConvert = (from, to) => {
    for (const day of days) {
      const v = getVal(next, day, emp);
      if (v !== from) continue;
      if (to === '休') {
        const row = { ...(next[day] || {}) };
        row[emp] = '休';
        next[day] = row;
        return true;
      }
      if (to === '白' || to === '中1') {
        if (trySetVal(next, day, emp, to, { start: spanStart, end: spanEnd, mixMaxRatio })) {
          return true;
        }
      }
    }
    return false;
  };
  let guard = 0;
  while (guard++ < 200) {
    const { white, mid, total } = count();
    if (total > targetTotal && tryConvert('白', '休')) continue;
    if (total > targetTotal && tryConvert('中1', '休')) continue;
    if (white > targetWhite && tryConvert('白', '中1')) continue;
    if (mid > targetMid && tryConvert('中1', '白')) continue;
    if (white < targetWhite && tryConvert('中1', '白')) continue;
    if (mid < targetMid && tryConvert('白', '中1')) continue;
    break;
  }
}

function adjustWithHistory({ data, employees, start, end, adminDays, historyProfile, mixMaxRatio = 1, yearlyOptimize = false }) {
  const next = JSON.parse(JSON.stringify(data));
  const days = enumerateDates(start, end);
  const totals = historyProfile?.shiftTotals || historyProfile?.shift_totals || {};
  const adminAdjust = Number.isFinite(adminDays) ? adminDays : 0;
  const targetTotal = Math.max(0, Math.round(days.length * (1 - adminAdjust / 30)));
  employees.forEach(emp => {
    const hist = totals[emp] || {};
    const targetWhite = Math.max(0, Math.round((hist.white ?? hist.白 ?? 0) * mixMaxRatio));
    const targetMid = Math.max(0, Math.round((hist.mid ?? hist.中1 ?? 0) * mixMaxRatio));
    adjustEmployeeSchedule({
      next,
      emp,
      days,
      spanStart: start,
      spanEnd: end,
      targetWhite,
      targetMid,
      targetTotal,
      mixMaxRatio
    });
  });
  if (yearlyOptimize) {
    employees.forEach(emp => {
      const hist = totals[emp] || {};
      const maxMixed = Math.max(0, Math.round((hist.mixed ?? hist.混合 ?? 0) * mixMaxRatio));
      let guard = 0;
      while (mixedCyclesCount(next, emp, start, end) > maxMixed && guard++ < 50) {
        const cycles = cyclesForEmp(next, emp, start, end);
        for (const cyc of cycles) {
          const targetDay = cyc.find(day => getVal(next, day, emp) === '中1');
          if (targetDay) {
            setVal(next, targetDay, emp, '白');
            break;
          }
        }
      }
    });
  }
  return repairNoMidToWhite({ data: next, employees, start, end });
}

function autoAssignNightAndM2({ employees, data, start, end, override = false, nightRules, restPrefs = {} }) {
  const next = JSON.parse(JSON.stringify(data));
  const days = enumerateDates(start, end);
  const interval = nightRules?.prioritizeInterval ? 3 : 2;
  const allowNightDay4 = nightRules?.allowNightDay4 === true;
  const allowDoubleMid2 = nightRules?.allowDoubleMid2 === true;
  const enforceRestCap = nightRules?.enforceRestCap !== false;
  const restAfterNight = nightRules?.restAfterNight !== false;
  const restAfterMid2 = nightRules?.restAfterMid2 !== false;
  const sorted = employees.slice().sort((a, b) => (longestRun(next, a, start, end) - longestRun(next, b, start, end)) || a.localeCompare(b, 'zh-CN'));
  const assign = (day, emp, shift) => {
    const row = { ...(next[day] || {}) };
    row[emp] = shift;
    next[day] = row;
  };
  sorted.forEach((emp, idx) => {
    let guard = 0;
    for (const day of days) {
      const current = getVal(next, day, emp);
      if (!override && current && current !== '休') continue;
      if (guard++ > 120) break;
      const w = dow(day);
      if (!allowNightDay4 && w === 4) continue; // 周四夜班禁用
      if (restAfterNight) {
        const prev = dateAdd(day, -1);
        if (getVal(next, prev, emp) === '夜') continue;
      }
      if (enforceRestCap && wouldExceed6(next, emp, day, '夜')) continue;
      assign(day, emp, '夜');
      if (restAfterNight) {
        const after = dateAdd(day, 1);
        assign(after, emp, '休');
      }
      if (restAfterNight) {
        const twoAfter = dateAdd(day, 2);
        assign(twoAfter, emp, '休');
      }
      break;
    }
    for (let offset = idx % interval; offset < days.length; offset += interval) {
      const day = days[offset];
      const current = getVal(next, day, emp);
      if (!override && current && current !== '休') continue;
      if (allowDoubleMid2 || getVal(next, dateAdd(day, -1), emp) !== '中2') {
        assign(day, emp, '中2');
        if (restAfterMid2) assign(dateAdd(day, 1), emp, '休');
      }
    }
  });
  return next;
}

/**
 * 运行自动排班流水线，将原本散落在入口文件中的阶段性算法集中管理。
 *
 * @param {Object} options 运行参数
 * @param {string[]} options.employees 目标员工列表
 * @param {Object} options.data 当前排班数据
 * @param {string} options.start 排班开始日期
 * @param {string} options.end 排班结束日期
 * @param {Object} options.restPrefs 员工休息偏好
 * @param {number} options.mixMax 交替调整上限
 * @param {number} options.rMin 按天最小占比
 * @param {number} options.rMax 按天最大占比
 * @param {number} options.pMin 个人最小占比
 * @param {number} options.pMax 个人最大占比
 * @param {number} options.adminDays 行政天数（白班保底）
 * @param {Object|null} options.historyProfile 历史统计画像
 * @param {boolean} options.yearlyOptimize 是否启用年度均衡
 * @param {(stage: string, done: number, total: number) => void} options.onStage 阶段回调
 * @param {(msg: string) => void} [options.onLog] 日志回调
 * @param {(next: Object) => void} [options.onData] 数据更新回调
 * @param {number} [options.waitMs=20] 阶段之间的等待毫秒数
 *
 * @returns {Promise<Object>} 生成后的最新排班数据
 */
async function runAutoScheduleFlow({
  employees,
  data,
  start,
  end,
  restPrefs,
  mixMax,
  rMin,
  rMax,
  pMin,
  pMax,
  adminDays,
  historyProfile,
  yearlyOptimize,
  onStage,
  onLog,
  onData,
  waitMs = 20
}) {
  const waitForFrame = () => new Promise((resolve) => setTimeout(resolve, waitMs));
  const targets = sortedByHistory(Array.isArray(employees) ? employees : [], historyProfile);

  if (!targets.length) {
    return data;
  }

  const stage = (label, done) => {
    if (typeof onStage === 'function') {
      onStage(label, done, 100);
    }
  };
  const emit = (nextData) => {
    if (typeof onData === 'function') {
      onData(nextData);
    }
  };
  const log = (message) => {
    if (typeof onLog === 'function' && message) {
      onLog(message);
    }
  };

  stage('阶段 1/6：拉齐白班（5白+2休）', 10);
  let cur = buildWhiteFiveTwo({ employees: targets, data, start, end, restPrefs });
  emit(cur);
  await waitForFrame();

  stage('阶段 2/6：按周期交替错开放中班', 30);
  cur = applyAlternateByCycle({ employees: targets, data: cur, start, end }, mixMax);
  emit(cur);
  await waitForFrame();

  stage('阶段 3/6：按天比例收敛', 55);
  cur = clampDailyByRange({ employees: targets, data: cur, start, end, rMin, rMax, maxRounds: 300, mixMaxRatio: mixMax });
  emit(cur);
  await waitForFrame();

  stage('阶段 4/6：个人比例收敛', 78);
  for (let sweep = 0; sweep < 8; sweep++) {
    cur = clampDailyByRange({ employees: targets, data: cur, start, end, rMin, rMax, maxRounds: 220, mixMaxRatio: mixMax });
    cur = clampPersonByRange({ employees: targets, data: cur, start, end, pMin, pMax, maxRounds: 260, mixMaxRatio: mixMax });
    log(`循环微调：第 ${sweep + 1} 轮`);
  }
  emit(cur);
  await waitForFrame();

  stage('阶段 5/6：参考历史与行政天数调整', 92);
  cur = adjustWithHistory({
    data: cur,
    employees: targets,
    start,
    end,
    adminDays,
    historyProfile,
    mixMaxRatio: mixMax,
    yearlyOptimize
  });
  emit(cur);
  await waitForFrame();

  stage('阶段 6/6：校验并微调', 98);
  cur = repairNoMidToWhite({ data: cur, employees: targets, start, end });
  emit(cur);

  return cur;
}

/**
 * 按夜班窗口批量执行夜班/中二分配，入口层只需负责收集参数。
 */
function assignNightForWindows({ employees, data, nightWindows = [], nightOverride, nightRules, restPrefs }) {
  if (!Array.isArray(nightWindows) || nightWindows.length === 0) {
    return data;
  }
  let nextData = data;
  nightWindows.forEach((win) => {
    if (!win || !win.s || !win.e) {
      return;
    }
    nextData = autoAssignNightAndM2({
      employees,
      data: nextData,
      start: win.s,
      end: win.e,
      override: nightOverride,
      nightRules,
      restPrefs
    });
  });
  return nextData;
}

function readJSONFromStorage(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return fallback;
    return JSON.parse(raw);
  } catch (err) {
    console.warn('读取本地存储失败', key, err);
    return fallback;
  }
}

function writeJSONToStorage(key, value) {
  try {
    if (value === undefined) {
      localStorage.removeItem(key);
    } else {
      localStorage.setItem(key, JSON.stringify(value));
    }
  } catch (err) {
    console.warn('写入本地存储失败', key, err);
  }
}

/**
 * 休息偏好缓存：在本地存储保留同一团队 + 时间范围下的配置信息
 * 入口层可通过该工厂方法获得读写函数，避免在组件中直接拼接 key。
 */
function createRestPreferenceStore({ storageKey = REST_PREF_STORAGE_KEY, sanitizeRestPrefsMap: sanitizeFn } = {}) {
  const sanitize = typeof sanitizeFn === 'function' ? sanitizeFn : (value) => sanitizeRestPrefsMap(value);
  const buildKey = (team, start, end) => {
    const safeTeam = (team || 'default').trim() || 'default';
    const safeStart = start || '1970-01-01';
    const safeEnd = end || '1970-01-01';
    return `${safeTeam}__${safeStart}__${safeEnd}`;
  };
  const readAll = () => {
    const stored = readJSONFromStorage(storageKey, {});
    return stored && typeof stored === 'object' ? stored : {};
  };
  const writeAll = (map) => {
    writeJSONToStorage(storageKey, map);
  };

  return {
    /**
     * 读取指定团队与起止日期对应的缓存，并返回经过清洗后的结果。
     */
    load({ team, start, end } = {}) {
      const map = readAll();
      const key = buildKey(team, start, end);
      const hit = map[key];
      if (!hit || typeof hit !== 'object') return null;
      const cleaned = sanitize(hit);
      return Object.keys(cleaned).length ? cleaned : null;
    },
    /**
     * 写入缓存；如传入空对象则清除对应缓存，确保 localStorage 不会无限膨胀。
     */
    save({ team, start, end, prefs } = {}) {
      const map = readAll();
      const key = buildKey(team, start, end);
      if (!prefs || !Object.keys(prefs).length) {
        delete map[key];
      } else {
        map[key] = sanitize(prefs);
      }
      writeAll(map);
    },
    /**
     * 清理某个团队的所有缓存项，供入口层在团队删除时调用。
     */
    clearTeam(team) {
      const map = readAll();
      const safeTeam = (team || 'default').trim() || 'default';
      const next = {};
      Object.entries(map).forEach(([key, value]) => {
        if (!key.startsWith(`${safeTeam}__`)) {
          next[key] = value;
        }
      });
      writeAll(next);
    },
    /**
     * 暴露底层原始数据，便于调试或导出。
     */
    dump() {
      return readAll();
    }
  };
}

function useOrgConfigState({ apiGet, apiPost, normalizeOrgConfig, storageKey = ORG_STORAGE_KEY, debounceMs = 400 }) {
  const [orgConfig, setOrgConfig] = useState(() => {
    const stored = readJSONFromStorage(storageKey, null);
    return normalizeOrgConfig(stored || undefined);
  });
  const [loaded, setLoaded] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const lastSyncedRef = useRef(JSON.stringify(orgConfig));
  const debounceRef = useRef(null);

  const updateOrgConfig = useCallback((updater) => {
    setOrgConfig((prev) => {
      const base = typeof updater === 'function' ? updater(prev) : updater;
      return normalizeOrgConfig(base);
    });
  }, [normalizeOrgConfig]);

  useEffect(() => {
    writeJSONToStorage(storageKey, orgConfig);
  }, [orgConfig, storageKey]);

  const refreshOrgConfig = useCallback(async () => {
    try {
      const res = await apiGet('/org-config');
      if (res && res.config && typeof res.config === 'object') {
        const normalized = normalizeOrgConfig(res.config);
        lastSyncedRef.current = JSON.stringify(normalized);
        setOrgConfig(normalized);
      }
    } catch (err) {
      console.warn('加载 org 配置失败', err);
      throw err;
    }
  }, [apiGet, normalizeOrgConfig]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        await refreshOrgConfig();
      } catch {
        /* 已在 refreshOrgConfig 中记录 */
      }
      if (!cancelled) {
        setLoaded(true);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [refreshOrgConfig]);

  useEffect(() => {
    if (!loaded) {
      return undefined;
    }
    const serialized = JSON.stringify(orgConfig);
    if (serialized === lastSyncedRef.current) {
      return undefined;
    }
    let cancelled = false;
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    debounceRef.current = setTimeout(() => {
      if (cancelled) {
        return;
      }
      setSyncing(true);
      (async () => {
        try {
          await apiPost('/org-config', { config: orgConfig });
          if (!cancelled) {
            lastSyncedRef.current = serialized;
          }
        } catch (err) {
          if (!cancelled) {
            console.warn('保存 org 配置失败', err);
          }
        } finally {
          if (!cancelled) {
            setSyncing(false);
          }
        }
      })();
    }, debounceMs);
    return () => {
      cancelled = true;
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
      setSyncing(false);
    };
  }, [orgConfig, loaded, apiPost, debounceMs]);

  return {
    orgConfig,
    updateOrgConfig,
    loaded,
    syncing,
    refreshOrgConfig,
  };
}

function useTeamStateMap({ orgConfig, defaultStart, defaultEnd, createEmptyTeamState, storageKey = TEAM_STATE_STORAGE_KEY }) {
  const [teamStateMap, setTeamStateMap] = useState(() => {
    const stored = readJSONFromStorage(storageKey, {});
    return stored && typeof stored === 'object' ? stored : {};
  });

  useEffect(() => {
    writeJSONToStorage(storageKey, teamStateMap);
  }, [teamStateMap, storageKey]);

  useEffect(() => {
    setTeamStateMap((prev) => {
      const teams = Array.isArray(orgConfig?.teams) ? orgConfig.teams : [];
      const next = { ...prev };
      const valid = new Set();
      let changed = false;
      teams.forEach((team) => {
        if (!team || !team.id) {
          return;
        }
        const id = team.id;
        valid.add(id);
        if (!next[id]) {
          next[id] = createEmptyTeamState(defaultStart, defaultEnd);
          changed = true;
        }
      });
      Object.keys(next).forEach((id) => {
        if (!valid.has(id)) {
          delete next[id];
          changed = true;
        }
      });
      return changed ? next : prev;
    });
  }, [orgConfig, defaultStart, defaultEnd, createEmptyTeamState]);

  return [teamStateMap, setTeamStateMap];
}

function useProgressLog({ team, fetchProgressLogs, appendProgressLog }) {
  const [logs, setLogs] = useState([]);
  const logEndRef = useRef(null);

  const syncProgressLog = useCallback((message, extra = {}) => {
    if (!message || typeof appendProgressLog !== 'function') return;
    const payload = { team, message, ...extra };
    if (typeof payload.progress === 'number') {
      if (!Number.isFinite(payload.progress)) {
        delete payload.progress;
      } else {
        payload.progress = Math.max(0, Math.min(100, Math.round(payload.progress)));
      }
    }
    if (payload.context && typeof payload.context !== 'object') {
      delete payload.context;
    }
    appendProgressLog(payload).catch(() => {});
  }, [team, appendProgressLog]);

  const pushLog = useCallback((msg, meta = {}) => {
    if (!msg) return;
    const label = `[${new Date().toLocaleTimeString()}] ${msg}`;
    setLogs((prev) => [...prev.slice(-199), label]);
    if (meta.skipPersist) return;
    if (typeof msg === 'string' && msg.startsWith('心跳')) return;
    syncProgressLog(msg, meta);
  }, [syncProgressLog]);

  useEffect(() => {
    if (typeof fetchProgressLogs !== 'function') return undefined;
    let cancelled = false;
    fetchProgressLogs({ team, limit: 50 }).then((res) => {
      if (cancelled || !res || !Array.isArray(res.items)) return;
      const next = res.items.map((item) => {
        const time = item.timestamp ? new Date(item.timestamp).toLocaleTimeString() : new Date().toLocaleTimeString();
        const label = item.stage && item.stage !== item.message ? `${item.stage}：${item.message || ''}` : (item.message || '');
        return `[${time}] ${label}`;
      });
      setLogs(next.slice(-200));
    }).catch(() => {});
    return () => { cancelled = true; };
  }, [team, fetchProgressLogs]);

  useEffect(() => {
    if (logEndRef.current) {
      logEndRef.current.scrollIntoView({ behavior: 'auto' });
    }
  }, [logs]);

  return { logs, pushLog, logEndRef };
}

function useProgressTracker({ pushLog }) {
  const [prog, setProg] = useState({ running: false, stage: '', done: 0, total: 100 });

  const setStage = useCallback((stage, done, total) => {
    const progress = total ? Math.round((done / total) * 100) : done;
    setProg({ running: true, stage, done, total });
    pushLog(stage, { stage, progress });
  }, [pushLog]);

  const endStage = useCallback(() => {
    setProg({ running: false, stage: '完成', done: 100, total: 100 });
    pushLog('执行完成', { stage: '完成', progress: 100 });
  }, [pushLog]);

  const failStage = useCallback((message, extra = {}) => {
    setProg({ running: false, stage: '失败', done: 0, total: 100 });
    const text = message ? String(message) : '执行失败';
    pushLog(text, { ...extra, stage: '失败' });
  }, [pushLog]);

  useEffect(() => {
    if (!prog.running) return undefined;
    const timer = setInterval(() => {
      pushLog('心跳：计算中…', { skipPersist: true });
    }, 1000);
    return () => clearInterval(timer);
  }, [prog.running, pushLog]);

  return { prog, setStage, endStage, failStage };
}

window.AppState = {
  WN,
  today,
  SHIFT_TYPES,
  SHIFT_WORK_TYPES,
  fmt,
  monthRangeOf,
  enumerateDates,
  dow,
  dateAdd,
  monthListBetween,
  monthKeyToSpan,
  weekStartKey,
  isWork,
  getVal,
  setVal,
  countByEmpInRange,
  countRunLeft,
  countRunRight,
  wouldExceed6,
  REST_PAIRS,
  MENU_KEYS,
  defaultShiftColors,
  defaultStaffingAlerts,
  normalizeRestPair,
  sanitizeRestPairValue,
  sanitizeRestPrefsMap,
  normalizeStaffingAlerts,
  contrastColor,
  styleForStaffing,
  normalizeNightRules,
  hashPassword,
  verifyPassword,
  defaultMenuPerms,
  normalizeMenuPerms,
  ensureUniqueId,
  sanitizeIdFromName,
  normalizeTeamEntry,
  defaultOrgConfig,
  normalizeOrgConfig,
  createEmptyTeamState,
  ORG_STORAGE_KEY,
  TEAM_STATE_STORAGE_KEY,
  pairToSet,
  dayToW,
  isRestDayForEmp,
  buildWhiteFiveTwo,
  buildWorkBlocks,
  cyclesForEmp,
  isRightEdgeWhite,
  isLeftEdgeMid,
  dailyMidCount,
  dailyWhiteCount,
  empMidCountInRange,
  empWhiteCountInRange,
  mixedCyclesCount,
  longestRun,
  trySetVal,
  repairNoMidToWhite,
  applyAlternateByCycle,
  clampDailyByRange,
  clampPersonByRange,
  statsForEmployee,
  sortedByHistory,
  adjustEmployeeSchedule,
  adjustWithHistory,
  autoAssignNightAndM2,
  runAutoScheduleFlow,
  assignNightForWindows,
  useOrgConfigState,
  useTeamStateMap,
  useProgressLog,
  useProgressTracker,
  createRestPreferenceStore
};
