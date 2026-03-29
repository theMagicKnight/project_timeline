<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Anmelden — Projekt-Timeline</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --accent: #7c6af7;
      --surface: #16181f;
      --border:  #2a2d38;
    }
    body {
      font-family: 'DM Sans', sans-serif;
      min-height: 100dvh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #0e0f14;
    }
    [data-bs-theme="light"] body { background: #f0f2f8; }

    .login-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 40px 36px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 8px 48px rgba(0,0,0,.5);
    }
    .login-logo {
      font-family: 'DM Serif Display', serif;
      font-size: 1.6rem;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }
    .logo-dot {
      width: 10px; height: 10px;
      border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 12px var(--accent);
    }
    .login-sub { color: #5c6070; font-size: .8rem; margin-bottom: 28px; letter-spacing: .5px; text-transform: uppercase; }
    .btn-accent {
      background: var(--accent);
      border: none;
      color: #fff;
      font-family: 'DM Sans', sans-serif;
      font-weight: 500;
      padding: 10px;
      border-radius: 8px;
      transition: background .15s;
    }
    .btn-accent:hover { background: #5b4de0; color: #fff; }
    .form-control, .form-select {
      background: #1e2028;
      border-color: #353844;
      color: #e8eaf0;
      border-radius: 8px;
    }
    .form-control:focus { background: #1e2028; color: #e8eaf0; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(124,106,247,.2); }
    .form-label { font-size: .8rem; font-weight: 500; color: #9296a8; }
    .form-control::placeholder { color: #5c6070; }
    .alert-danger-custom {
      background: rgba(248,113,113,.12);
      border: 1px solid rgba(248,113,113,.3);
      color: #f87171;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: .85rem;
      margin-bottom: 18px;
    }
    .input-group-text {
      background: #1e2028;
      border-color: #353844;
      color: #5c6070;
    }
  </style>
</head>
<body>
<div class="login-card">
  <div class="login-logo"><span class="logo-dot"></span> Projekt-Timeline</div>
  <div class="login-sub">Ideen · Entwicklung · Abschluss</div>

  <?php if ($fehler): ?>
    <div class="alert-danger-custom"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($fehler) ?></div>
  <?php endif; ?>

  <?php if ($db_error): ?>
    <div class="alert-danger-custom"><i class="bi bi-database-x me-2"></i>DB-Fehler: <?= htmlspecialchars($db_error) ?></div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
