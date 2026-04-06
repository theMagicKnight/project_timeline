/* ============================================================
   api.js — API-Wrapper, Hilfsfunktionen, Rechte
   ============================================================ */

function hatRecht(mindest) {
  const ist  = RECHTE_STUFEN[aktivesRecht] ?? 0;
  const mind = RECHTE_STUFEN[mindest] ?? 99;
  return ist >= mind || IST_ADMIN;
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function J(o)        { return JSON.stringify(o).replace(/"/g,"'"); }
function fmtDate(d)  { if (!d) return ''; return new Date(d+'T00:00:00').toLocaleDateString('de-DE',{day:'2-digit',month:'short',year:'numeric'}); }
function phaseOrder(p) { return ['idee','start','entwicklung','abschluss'].indexOf(p); }
function vorname(name) { if (!name) return ''; return name.trim().split(' ')[0]; }

async function api(action, data=null, params='') {
  const url  = `api.php?api=${action}${params}`;
  const opts = data
    ? { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) }
    : { method:'GET' };
  const r = await fetch(url, opts);
  if (r.status === 401) { window.location.href = 'login.php'; return; }
  const j = await r.json();
  if (j.error) throw new Error(j.error);
  return j;
}

function notify(msg, type='success') {
  const icon = type==='success'
    ? '<i class="bi bi-check-lg text-success"></i>'
    : '<i class="bi bi-exclamation-triangle text-danger"></i>';
  const n = document.createElement('div');
  n.className = `app-toast ${type}`;
  n.innerHTML = `${icon} ${esc(msg)}`;
  document.body.appendChild(n);
  setTimeout(()=>n.remove(), 3200);
}