<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Ersten Admin anlegen falls noch keiner existiert
if ($pdo) ersterAdminAnlegen($pdo);

// Bereits eingeloggt → weiterleiten
if (istEingeloggt()) {
    header('Location: index.php');
    exit;
}

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $passwort = trim($_POST['passwort'] ?? '');

    if ($email && $passwort && $pdo) {
        $s = $pdo->prepare("SELECT * FROM `" . TBL_BENUTZER . "` WHERE email=? AND aktiv=1");
        $s->execute([$email]);
        $user = $s->fetch();

        if ($user && password_verify($passwort, $user['passwort'])) {
            $_SESSION['benutzer'] = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'rolle' => $user['rolle'],
                'theme' => $user['theme'],
            ];
            header('Location: index.php');
            exit;
        } else {
            $fehler = 'E-Mail oder Passwort falsch.';
        }
    } else {
        $fehler = 'Bitte alle Felder ausfüllen.';
    }
}

// Template-Variablen
$title     = 'Anmelden — Projekt-Timeline';
$theme     = 'dark';
$extra_css = 'assets/css/login.css';

require_once __DIR__ . '/templates/header.php';
?>

<div class="login-card">
  <div class="login-logo"><span class="logo-dot"></span> Projekt-Timeline</div>
  <div class="login-sub">Ideen · Entwicklung · Abschluss</div>

  <?php if ($fehler): ?>
    <div class="alert-danger-custom">
      <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($fehler) ?>
    </div>
  <?php endif; ?>

  <?php if ($db_error): ?>
    <div class="alert-danger-custom">
      <i class="bi bi-database-x me-2"></i>DB-Fehler: <?= htmlspecialchars($db_error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="mb-3">
      <label class="form-label">E-Mail-Adresse</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        <input type="email" name="email" class="form-control"
               placeholder="deine@email.de"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               autocomplete="email" autofocus>
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label">Passwort</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="passwort" class="form-control"
               placeholder="••••••••" autocomplete="current-password">
      </div>
    </div>
    <button type="submit" class="btn btn-accent w-100">
      <i class="bi bi-box-arrow-in-right me-2"></i>Anmelden
    </button>
  </form>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>