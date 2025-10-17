// API 模块：统一封装前端与后端的通信请求
const API_BASE = '/api';

/**
 * 发送 GET 请求，自动处理 JSON 响应与错误提示
 * @param {string} path 接口路径
 */
async function apiGet(path) {
  const resp = await fetch(API_BASE + path, { credentials: 'include' });
  if (!resp.ok) {
    let msg = '请求失败';
    try {
      const err = await resp.json();
      msg = err.message || msg;
    } catch {}
    throw new Error(msg);
  }
  return resp.json();
}

/**
 * 发送 POST 请求，自动序列化数据并在失败时抛出带详情的异常
 * @param {string} path 接口路径
 * @param {object} body 请求体
 */
async function apiPost(path, body) {
  const resp = await fetch(API_BASE + path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body || {}),
    credentials: 'include'
  });
  if (!resp.ok) {
    let payload = null;
    try {
      payload = await resp.json();
    } catch {}
    const err = new Error((payload && payload.message) || '请求失败');
    if (payload) err.payload = payload;
    throw err;
  }
  return resp.json();
}

/**
 * 查询进度日志，可传入 { team, limit }。
 */
async function fetchProgressLogs(params = {}) {
  const search = new URLSearchParams();
  if (params.team) search.set('team', params.team);
  if (params.limit) search.set('limit', params.limit);
  const query = search.toString();
  return apiGet(`/progress${query ? `?${query}` : ''}`);
}

/**
 * 追加一条进度日志。
 */
async function appendProgressLog(body) {
  return apiPost('/progress/log', body || {});
}

window.AppAPI = { API_BASE, apiGet, apiPost, fetchProgressLogs, appendProgressLog };
