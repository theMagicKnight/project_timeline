/* ============================================================
   auth.js — Login, Logout, Theme-Schalter, Sidebar-Toggle
   ============================================================ */

async function logout() {
  await api('logout');
  window.location.href = 'login.php';
}

async function wechselTheme() {
  aktuellesTheme = aktuellesTheme === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-bs-theme', aktuellesTheme);
  const isDark = aktuellesTheme === 'dark';
  document.querySelectorAll('#theme-toggle i, #theme-icon-mobile').forEach(el => {
    el.className = `bi bi-${isDark ? 'sun' : 'moon'}-fill`;
  });
  await api('theme_aendern', {theme: aktuellesTheme});
}

function toggleSidebar() {
  document.querySelector('.app-sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('open');
}
function schliesseSidebar() {
  document.querySelector('.app-sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('open');
}