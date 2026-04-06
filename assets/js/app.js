/* ============================================================
   app.js — Start, Event-Listener, Version, Easter Egg
   ============================================================ */

// ============================================================
//  Start
// ============================================================
ladeSidebar();
checkVersion();

// Matrix bei Fenstergrößenänderung neu rendern
let resizeTimer;
window.addEventListener('resize', () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => {
    if (aktiverTab === 'matrix' && aktivProjekt) {
      api('projekt_detail', null, `&id=${aktivProjekt.id}`)
        .then(d => renderMatrix(d.rubriken));
    }
  }, 300);
});

// ============================================================
//  Easter Egg — F12 Console
// ============================================================
const _cr = decodeURIComponent(atob('wqkgMjAyNiBFbnR3aWNrZWx0IG1pdCBDbGF1ZGUuYWkgKEFudGhyb3BpYykg4oCUIGh0dHBzOi8vY2xhdWRlLmFp').split('').map(c=>'%'+('00'+c.charCodeAt(0).toString(16)).slice(-2)).join(''));
console.log('\n%c' + _cr + '\n', 'color:#7c6af7;font-size:13px;font-weight:600;font-family:monospace');
console.log('%cHallo Neugieriger! 👋  Schön dass du vorbeischaust.', 'color:#9296a8;font-size:11px;font-family:monospace');

// ============================================================
//  Versions-Check
// ============================================================
async function checkVersion() {
  try {
    const r = await fetch('version.json?_=' + Date.now());
    if (!r.ok) return;
    const data        = await r.json();
    const neueVersion = data.version;
    const alteVersion = localStorage.getItem('app_version');
    if (!alteVersion) {
      localStorage.setItem('app_version', neueVersion);
      console.log('%cVersion ' + neueVersion, 'color:#7c6af7;font-size:11px;font-family:monospace');
      return;
    }
    if (alteVersion !== neueVersion) {
      console.log('%cNeue Version verfügbar: ' + neueVersion, 'color:#34d399;font-size:11px;font-family:monospace');
      zeigeUpdateToast(neueVersion);
    }
  } catch(e) { /* version.json nicht erreichbar — ignorieren */ }
}

function zeigeUpdateToast(version) {
  document.getElementById('update-toast')?.remove();
  const toast = document.createElement('div');
  toast.id = 'update-toast';
  toast.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:9999;background:var(--surface);border:1px solid var(--accent);border-radius:12px;padding:14px 20px;font-size:.85rem;color:var(--text);box-shadow:0 8px 32px rgba(0,0,0,.5);display:flex;align-items:center;gap:14px;animation:slideUp .3s ease;white-space:nowrap;`;
  toast.innerHTML = `
    <span style="color:var(--accent)"><i class="bi bi-arrow-repeat"></i></span>
    <span>Neue Version <strong>${version}</strong> verfügbar</span>
    <button onclick="aktualisiereApp('${version}')" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:6px 14px;font-family:inherit;font-size:.82rem;font-weight:500;cursor:pointer">Jetzt laden</button>
    <button onclick="this.closest('#update-toast').remove()" style="background:transparent;border:none;color:var(--text3);cursor:pointer;font-size:1rem;padding:0 2px">✕</button>`;
  document.body.appendChild(toast);
}

function aktualisiereApp(neueVersion) {
  localStorage.setItem('app_version', neueVersion);
  window.location.reload(true);
}