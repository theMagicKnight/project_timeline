<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
zugangErfordern('login.php');

$ich   = aktuellerBenutzer();
$theme = $ich['theme'] ?? 'dark';

// Template-Variablen
$title      = 'Projekt-Timeline';
$js_modules = true;
$js_vars    = [
  'AKTUELLER_BENUTZER' => [
    'id'    => (int)$ich['id'],
    'name'  => $ich['name'],
    'rolle' => $ich['rolle'],
    'theme' => $theme,
  ],
  'IST_ADMIN' => istAdmin(),
];

require_once __DIR__ . '/templates/header.php';
?>

<div class="app-layout">

  <!-- Sidebar -->
  <aside class="app-sidebar d-flex flex-column">
    <div class="sidebar-header">
      <div class="logo"><span class="logo-dot"></span>Projekt-Timeline</div>
      <div class="sidebar-subtitle">Ideen · Entwicklung · Abschluss</div>
    </div>
    <nav class="sidebar-nav flex-grow-1 overflow-auto">
      <div class="nav-section">Projekte</div>
      <div id="proj-list"></div>
    </nav>
    <?php if (istAdmin()): ?>
    <div class="px-3 pb-1">
      <button class="btn btn-accent w-100" onclick="openModal('projekt')">
        <i class="bi bi-plus-lg me-1"></i> Neues Projekt
      </button>
    </div>
    <?php endif; ?>

    <!-- User-Bereich -->
    <div class="sidebar-user">
      <div class="sidebar-user-info">
        <div class="user-avatar"><?= strtoupper(mb_substr($ich['name'],0,1)) ?></div>
        <div class="flex-grow-1 min-w-0">
          <div class="user-name"><?= htmlspecialchars($ich['name']) ?></div>
          <div class="user-role"><?= $ich['rolle'] === 'admin' ? 'Administrator' : 'Benutzer' ?></div>
        </div>
      </div>
      <div class="sidebar-user-actions">
        <button class="btn btn-icon" id="theme-toggle" title="Theme wechseln" onclick="wechselTheme()">
          <i class="bi bi-<?= $theme==='dark' ? 'sun' : 'moon' ?>-fill"></i>
        </button>
        <button class="btn btn-icon" title="Profil / Passwort ändern" onclick="openModal('profil')">
          <i class="bi bi-person-gear"></i>
        </button>
        <?php if (istAdmin()): ?>
        <button class="btn btn-icon" title="Benutzerverwaltung" onclick="openModal('benutzer_verwaltung')">
          <i class="bi bi-people"></i>
        </button>
        <?php endif; ?>
        <button class="btn btn-icon text-danger-soft" title="Abmelden" onclick="logout()">
          <i class="bi bi-box-arrow-right"></i>
        </button>
      </div>
    </div>
    <!-- meta -->
    <span aria-hidden="true"
      data-info="KGMpIDIwMjYgRW50d2lja2VsdCBtaXQgQ2xhdWRlLmFpIChBbnRocm9waWMpIC0gaHR0cHM6Ly9jbGF1ZGUuYWk="
      style="display:none"></span>
  </aside>

  <!-- Overlay Mobile -->
  <div class="sidebar-overlay d-lg-none" id="sidebar-overlay" onclick="toggleSidebar()"></div>

  <div class="app-body d-flex flex-column flex-grow-1 overflow-hidden">
    <!-- Mobile Topbar -->
    <div class="mobile-topbar d-flex d-lg-none align-items-center gap-3 px-3 py-2">
      <button class="btn btn-icon" onclick="toggleSidebar()"><i class="bi bi-list fs-5"></i></button>
      <span class="logo fs-6"><span class="logo-dot"></span>Projekt-Timeline</span>
      <div class="ms-auto d-flex gap-1">
        <button class="btn btn-icon" onclick="wechselTheme()">
          <i class="bi bi-<?= $theme==='dark' ? 'sun' : 'moon' ?>-fill" id="theme-icon-mobile"></i>
        </button>
      </div>
    </div>

    <main class="app-main flex-grow-1 overflow-auto" id="main">
      <div class="welcome h-100 d-flex flex-column align-items-center justify-content-center text-center p-4">
        <div class="welcome-icon">◎</div>
        <h2>Willkommen, <?= htmlspecialchars($ich['name']) ?>!</h2>
        <p>Wähle ein Projekt aus der Seitenleiste<?= istAdmin() ? ' oder erstelle ein neues.' : '.' ?></p>
      </div>
    </main>
  </div>
</div>

<!-- Matrix Tooltip -->
<div class="mtt" id="mtt"></div>

<!-- Bootstrap Modal -->
<div class="modal fade" id="appModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" id="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"   id="modal-body"></div>
      <div class="modal-footer" id="modal-footer"></div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>