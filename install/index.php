<?php
// ============================================================
//  install.php — Setup-Wizard & Update-Manager
//  Projekt-Timeline
// ============================================================
session_start();

define('GITHUB_USER',    'theMagicKnight');
define('GITHUB_REPO',    'project_timeline');
define('GITHUB_API',     'https://api.github.com/repos/' . GITHUB_USER . '/' . GITHUB_REPO . '/releases/latest');
define('INSTALL_LOCK',   __DIR__ . '/backups/.installed');
define('CONFIG_FILE',    __DIR__ . '/config.php');
define('BACKUP_DIR',     __DIR__ . '/backups');
define('VERSION_FILE',   __DIR__ . '/version.json');

// ---- Aktuelle lokale Version ----
function lokaleVersion(): string {
    if (file_exists(VERSION_FILE)) {
        $v = json_decode(file_get_contents(VERSION_FILE), true);
        return $v['version'] ?? '0.0.0';
    }
    return '0.0.0';
}

// ---- GitHub API abfragen ----
function githubRelease(): ?array {
    $json = null;

    // Variante 1: cURL (bevorzugt, funktioniert auch wenn allow_url_fopen = Off)
    if (function_exists('curl_init')) {
        $ch = curl_init(GITHUB_API);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'ProjektTimeline-Installer',
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $json = curl_exec($ch);
        if (curl_errno($ch)) $json = null;
        curl_close($ch);
    }

    // Variante 2: file_get_contents als Fallback
    if (!$json && ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: ProjektTimeline-Installer\r\n",
            'timeout' => 10,
        ]]);
        $json = @file_get_contents(GITHUB_API, false, $ctx);
    }

    if (!$json) return null;
    return json_decode($json, true);
}

// ---- Modus bestimmen ----
$istInstalliert = file_exists(CONFIG_FILE);
$aktion         = $_GET['action'] ?? ($_POST['action'] ?? '');
$schritt        = (int)($_SESSION['install_schritt'] ?? 1);

// ============================================================
//  AJAX-Handler
// ============================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // DB-Verbindung testen
    if ($_GET['ajax'] === 'db_test') {
        $host = trim($_POST['host'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['pass'] ?? '';
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4");
            echo json_encode(['ok' => true, 'msg' => 'Verbindung erfolgreich!']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // GitHub Release abfragen
    if ($_GET['ajax'] === 'github_check') {
        $release = githubRelease();
        if (!$release) {
            echo json_encode(['ok' => false, 'msg' => 'GitHub nicht erreichbar']);
            exit;
        }
        $remoteVersion = ltrim($release['tag_name'] ?? '0.0.0', 'v');
        $lokal         = lokaleVersion();
        echo json_encode([
            'ok'             => true,
            'lokal'          => $lokal,
            'remote'         => $remoteVersion,
            'update'         => version_compare($remoteVersion, $lokal, '>'),
            'name'           => $release['name'] ?? '',
            'changelog'      => $release['body'] ?? '',
            'zip_url'        => $release['zipball_url'] ?? '',
            'published'      => substr($release['published_at'] ?? '', 0, 10),
        ]);
        exit;
    }

    // Backup erstellen
    if ($_GET['ajax'] === 'backup') {
        $ergebnis = [];
        // config.php sichern
        if (file_exists(CONFIG_FILE)) {
            $ziel = BACKUP_DIR . '/config_' . date('Ymd_His') . '.php';
            if (copy(CONFIG_FILE, $ziel)) $ergebnis[] = 'config.php gesichert';
            else { echo json_encode(['ok'=>false,'msg'=>'config.php Backup fehlgeschlagen']); exit; }
        }
        // DB-Dump wenn config vorhanden
        if (file_exists(CONFIG_FILE)) {
            require_once CONFIG_FILE;
            try {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $sql  = "-- Projekt-Timeline DB Backup " . date('Y-m-d H:i:s') . "\n";
                $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
                $tbls = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tbls as $tbl) {
                    $create = $pdo->query("SHOW CREATE TABLE `$tbl`")->fetch();
                    $sql .= "DROP TABLE IF EXISTS `$tbl`;\n";
                    $sql .= $create['Create Table'] . ";\n\n";
                    $rows = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", $row);
                        $sql .= "INSERT INTO `$tbl` VALUES (" . implode(',', $vals) . ");\n";
                    }
                    $sql .= "\n";
                }
                $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
                $sqlDatei = BACKUP_DIR . '/db_' . date('Ymd_His') . '.sql';
                file_put_contents($sqlDatei, $sql);
                $ergebnis[] = 'Datenbank gesichert';
            } catch (Exception $e) {
                $ergebnis[] = 'DB-Backup Fehler: ' . $e->getMessage();
            }
        }
        echo json_encode(['ok' => true, 'msg' => implode(', ', $ergebnis)]);
        exit;
    }

    // Update durchführen
    if ($_GET['ajax'] === 'update') {
        $zipUrl = $_POST['zip_url'] ?? '';
        if (!$zipUrl) { echo json_encode(['ok'=>false,'msg'=>'Keine ZIP-URL']); exit; }

        // ZIP herunterladen — cURL bevorzugt, file_get_contents als Fallback
        $zipData = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($zipUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => 'ProjektTimeline-Installer',
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $zipData = curl_exec($ch);
            if (curl_errno($ch)) $zipData = null;
            curl_close($ch);
        }
        if (!$zipData && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'method'          => 'GET',
                'header'          => "User-Agent: ProjektTimeline-Installer\r\n",
                'follow_location' => true,
                'timeout'         => 60,
            ]]);
            $zipData = @file_get_contents($zipUrl, false, $ctx);
        }
        if (!$zipData) { echo json_encode(['ok'=>false,'msg'=>'ZIP konnte nicht heruntergeladen werden (cURL und allow_url_fopen nicht verfügbar)']); exit; }

        $tmpZip = sys_get_temp_dir() . '/pt_update_' . time() . '.zip';
        file_put_contents($tmpZip, $zipData);

        // Entpacken
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) { echo json_encode(['ok'=>false,'msg'=>'ZIP konnte nicht geöffnet werden']); exit; }

        $tmpDir = sys_get_temp_dir() . '/pt_update_' . time();
        $zip->extractTo($tmpDir);
        $zip->close();
        unlink($tmpZip);

        // Ersten Unterordner finden (GitHub packt in Unterordner)
        $dirs = glob($tmpDir . '/*', GLOB_ONLYDIR);
        $src  = $dirs[0] ?? $tmpDir;

        // Dateien kopieren (config.php und backups/ überspringen)
        $skip = ['config.php', 'backups'];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $count = 0;
        foreach ($iter as $item) {
            $rel  = str_replace($src . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $teile = explode(DIRECTORY_SEPARATOR, $rel);
            if (in_array($teile[0], $skip)) continue;
            $ziel = __DIR__ . DIRECTORY_SEPARATOR . $rel;
            if ($item->isDir()) {
                if (!is_dir($ziel)) mkdir($ziel, 0755, true);
            } else {
                copy($item->getPathname(), $ziel);
                $count++;
            }
        }

        // Temp aufräumen
        array_map('unlink', glob($tmpDir . '/*/*'));
        array_map('rmdir',  glob($tmpDir . '/*'));
        @rmdir($tmpDir);

        echo json_encode(['ok'=>true, 'msg'=>"$count Dateien aktualisiert"]);
        exit;
    }

    exit;
}

// ============================================================
//  POST — Installations-Schritte
// ============================================================
$fehler  = '';
$erfolg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$istInstalliert) {

    // Schritt 2 → 3: DB-Daten speichern
    if ($aktion === 'db_speichern') {
        $_SESSION['install_db'] = [
            'host'   => trim($_POST['db_host'] ?? 'localhost'),
            'name'   => trim($_POST['db_name'] ?? ''),
            'user'   => trim($_POST['db_user'] ?? ''),
            'pass'   => $_POST['db_pass'] ?? '',
            'prefix' => trim($_POST['db_prefix'] ?? 'tl_'),
        ];
        $_SESSION['install_schritt'] = 3;
        $schritt = 3;
    }

    // Schritt 3 → 4: Tabellen anlegen
    if ($aktion === 'tabellen_anlegen') {
        $db = $_SESSION['install_db'] ?? [];
        try {
            $pdo = new PDO("mysql:host={$db['host']};charset=utf8mb4", $db['user'], $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db['name']}`");
            $p = $db['prefix'];
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            foreach ([
                "CREATE TABLE IF NOT EXISTS `{$p}projekte` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, beschreibung TEXT, farbe VARCHAR(7) DEFAULT '#4f8ef7', erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `{$p}benutzer` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(200) NOT NULL UNIQUE, passwort VARCHAR(255) NOT NULL, rolle ENUM('admin','benutzer') DEFAULT 'benutzer', theme ENUM('dark','light') DEFAULT 'dark', aktiv TINYINT(1) DEFAULT 1, erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `{$p}projekt_benutzer` (id INT AUTO_INCREMENT PRIMARY KEY, projekt_id INT NOT NULL, benutzer_id INT NOT NULL, recht ENUM('lesen','schreiben','verwalten') DEFAULT 'lesen', UNIQUE KEY uq_pb (projekt_id, benutzer_id), FOREIGN KEY (projekt_id) REFERENCES `{$p}projekte`(id) ON DELETE CASCADE, FOREIGN KEY (benutzer_id) REFERENCES `{$p}benutzer`(id) ON DELETE CASCADE) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `{$p}rubriken` (id INT AUTO_INCREMENT PRIMARY KEY, projekt_id INT NOT NULL, name VARCHAR(200) NOT NULL, beschreibung TEXT, sortierung INT DEFAULT 0, erstellt_von INT NULL, erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (projekt_id) REFERENCES `{$p}projekte`(id) ON DELETE CASCADE, FOREIGN KEY (erstellt_von) REFERENCES `{$p}benutzer`(id) ON DELETE SET NULL) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `{$p}eintraege` (id INT AUTO_INCREMENT PRIMARY KEY, rubrik_id INT NOT NULL, titel VARCHAR(300) NOT NULL, beschreibung TEXT, phase ENUM('idee','start','entwicklung','abschluss') DEFAULT 'idee', phase_datum DATE, farbe VARCHAR(7) DEFAULT '#4f8ef7', sortierung INT DEFAULT 0, erstellt_von INT NULL, erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (rubrik_id) REFERENCES `{$p}rubriken`(id) ON DELETE CASCADE, FOREIGN KEY (erstellt_von) REFERENCES `{$p}benutzer`(id) ON DELETE SET NULL) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `{$p}timeline_schritte` (id INT AUTO_INCREMENT PRIMARY KEY, eintrag_id INT NOT NULL, phase ENUM('idee','start','entwicklung','abschluss') NOT NULL, titel VARCHAR(300) NOT NULL, beschreibung TEXT, datum DATE, erstellt_von INT NULL, erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (eintrag_id) REFERENCES `{$p}eintraege`(id) ON DELETE CASCADE, FOREIGN KEY (erstellt_von) REFERENCES `{$p}benutzer`(id) ON DELETE SET NULL) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `{$p}anhaenge` (id INT AUTO_INCREMENT PRIMARY KEY, typ ENUM('eintrag','schritt') NOT NULL, referenz_id INT NOT NULL, titel VARCHAR(200) NOT NULL, sprache VARCHAR(30) DEFAULT 'plaintext', inhalt LONGTEXT NOT NULL, erstellt_von INT NULL, erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (erstellt_von) REFERENCES `{$p}benutzer`(id) ON DELETE SET NULL) ENGINE=InnoDB",
            ] as $sql) { $pdo->exec($sql); }
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $_SESSION['install_schritt'] = 4;
            $schritt = 4;
        } catch (Exception $e) {
            $fehler  = $e->getMessage();
            $schritt = 3;
        }
    }

    // Schritt 4 → 5: Admin + config.php schreiben
    if ($aktion === 'admin_anlegen') {
        $db    = $_SESSION['install_db'] ?? [];
        $name  = trim($_POST['admin_name']  ?? 'Admin');
        $email = trim($_POST['admin_email'] ?? '');
        $pass  = trim($_POST['admin_pass']  ?? '');
        $pass2 = trim($_POST['admin_pass2'] ?? '');

        if (!$email || !$pass) {
            $fehler = 'Bitte E-Mail und Passwort eingeben.';
            $schritt = 4;
        } elseif ($pass !== $pass2) {
            $fehler = 'Passwörter stimmen nicht überein.';
            $schritt = 4;
        } elseif (strlen($pass) < 6) {
            $fehler = 'Passwort muss mindestens 6 Zeichen lang sein.';
            $schritt = 4;
        } else {
            try {
                $p   = $db['prefix'];
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
                    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->prepare("INSERT INTO `{$p}benutzer` (name,email,passwort,rolle) VALUES (?,?,?,?)")
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);

                // config.php schreiben — nur DB-Zugangsdaten + require tbl.php
                $prefix = $db['prefix'];
                $config = "<?php\n"
                    . "// Projekt-Timeline — Konfiguration\n"
                    . "// Erstellt: " . date('Y-m-d H:i:s') . "\n"
                    . "// Diese Datei wird bei Updates NICHT überschrieben.\n\n"
                    . "define('DB_HOST', '" . addslashes($db['host']) . "');\n"
                    . "define('DB_USER', '" . addslashes($db['user']) . "');\n"
                    . "define('DB_PASS', '" . addslashes($db['pass']) . "');\n"
                    . "define('DB_NAME', '" . addslashes($db['name']) . "');\n\n"
                    . "define('DB_PREFIX', '" . addslashes($prefix) . "');\n\n"
                    . "// Tabellennamen aus src/tbl.php laden (wird bei Updates aktualisiert)\n"
                    . "require_once __DIR__ . '/src/tbl.php';\n\n"
                    . "// Admin-Erstanlage — nach Installation leer lassen\n"
                    . "define('ADMIN_EMAIL', '');\n"
                    . "define('ADMIN_PASS',  '');\n";

                file_put_contents(CONFIG_FILE, $config);
                file_put_contents(INSTALL_LOCK, date('Y-m-d H:i:s'));
                session_destroy();
                $_SESSION['install_schritt'] = 5;
                $schritt = 5;
            } catch (Exception $e) {
                $fehler  = $e->getMessage();
                $schritt = 4;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projekt-Timeline — <?= $istInstalliert ? 'Update-Manager' : 'Installation' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --accent:   #7c6af7;
      --accent2:  #5b4de0;
      --surface:  #16181f;
      --surface2: #1e2028;
      --surface3: #252830;
      --border:   #2a2d38;
      --text:     #e8eaf0;
      --text2:    #9296a8;
      --text3:    #5c6070;
      --green:    #34d399;
      --red:      #f87171;
      --gold:     #f0b429;
    }
    body {
      font-family: 'DM Sans', sans-serif;
      background: #0e0f14;
      color: var(--text);
      min-height: 100dvh;
    }

    /* ---- HEADER ---- */
    .inst-header {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 18px 32px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .logo { font-family: 'DM Serif Display', serif; font-size: 1.4rem; display:flex; align-items:center; gap:10px; }
    .logo-dot { width:10px;height:10px;border-radius:50%;background:var(--accent);box-shadow:0 0 12px var(--accent); }
    .inst-badge {
      font-size:.7rem;padding:3px 10px;border-radius:20px;font-weight:600;
      letter-spacing:.5px;text-transform:uppercase;
      background:rgba(124,106,247,.15);color:var(--accent);
    }

    /* ---- PROGRESS ---- */
    .progress-steps {
      display: flex;
      align-items: center;
      gap: 0;
      padding: 24px 32px 0;
      max-width: 640px;
      margin: 0 auto;
    }
    .step-item { display:flex;flex-direction:column;align-items:center;flex:1; }
    .step-circle {
      width: 36px; height: 36px; border-radius: 50%;
      border: 2px solid var(--border);
      background: var(--surface2);
      display: flex; align-items: center; justify-content: center;
      font-size: .82rem; font-weight: 600; color: var(--text3);
      transition: all .3s ease;
      position: relative; z-index: 1;
    }
    .step-circle.active  { border-color: var(--accent); background: var(--accent); color: #fff; box-shadow: 0 0 16px rgba(124,106,247,.4); }
    .step-circle.done    { border-color: var(--green);  background: var(--green);  color: #fff; }
    .step-label { font-size:.72rem; color:var(--text3); margin-top:6px; white-space:nowrap; }
    .step-label.active { color: var(--accent); font-weight:500; }
    .step-label.done   { color: var(--green); }
    .step-line { flex:1; height:2px; background:var(--border); margin-top:-18px; transition:background .3s; }
    .step-line.done { background: var(--green); }

    /* ---- CARD ---- */
    .inst-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 32px;
      max-width: 600px;
      margin: 32px auto;
      animation: fadeUp .3s ease;
    }
    @keyframes fadeUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }

    .inst-title { font-family:'DM Serif Display',serif; font-size:1.6rem; margin-bottom:6px; }
    .inst-sub   { color:var(--text2); font-size:.9rem; margin-bottom:24px; }

    /* ---- FORM ---- */
    .form-control, .form-select {
      background: var(--surface2); border-color: var(--border);
      color: var(--text); border-radius: 8px; font-family:inherit;
    }
    .form-control:focus, .form-select:focus {
      background: var(--surface2); border-color: var(--accent); color: var(--text);
      box-shadow: 0 0 0 3px rgba(124,106,247,.2);
    }
    .form-control::placeholder { color:var(--text3); }
    .form-label { font-size:.8rem; font-weight:500; color:var(--text2); }
    .input-group-text { background:var(--surface2); border-color:var(--border); color:var(--text3); }

    /* ---- BUTTONS ---- */
    .btn-accent {
      background:var(--accent);color:#fff;border:none;border-radius:8px;
      font-family:inherit;font-weight:500;padding:10px 20px;
      transition:background .15s;display:inline-flex;align-items:center;gap:8px;
    }
    .btn-accent:hover { background:var(--accent2);color:#fff; }
    .btn-outline-light-custom {
      background:transparent;color:var(--text2);border:1px solid var(--border);
      border-radius:8px;font-family:inherit;padding:10px 20px;
      transition:all .15s;display:inline-flex;align-items:center;gap:8px;
    }
    .btn-outline-light-custom:hover { background:var(--surface2);color:var(--text); }

    /* ---- STATUS ---- */
    .status-box {
      border-radius:10px;padding:12px 16px;font-size:.85rem;
      display:flex;align-items:center;gap:10px;margin-bottom:16px;
    }
    .status-ok      { background:rgba(52,211,153,.1); border:1px solid rgba(52,211,153,.3); color:var(--green); }
    .status-err     { background:rgba(248,113,113,.1); border:1px solid rgba(248,113,113,.3); color:var(--red); }
    .status-info    { background:rgba(124,106,247,.1); border:1px solid rgba(124,106,247,.3); color:var(--accent); }
    .status-warning { background:rgba(240,180,41,.1);  border:1px solid rgba(240,180,41,.3);  color:var(--gold); }

    /* ---- TEST BUTTON ---- */
    .test-result { display:none;margin-top:10px; }
    #test-spinner { display:none; }

    /* ---- UPDATE CARD ---- */
    .version-current {
      display:flex;align-items:center;gap:12px;
      padding:16px;background:var(--surface2);border-radius:10px;margin-bottom:16px;
    }
    .version-badge {
      font-size:.75rem;font-weight:600;padding:4px 12px;border-radius:20px;
      background:rgba(52,211,153,.15);color:var(--green);
    }
    .version-badge.update { background:rgba(240,180,41,.15);color:var(--gold); }

    .changelog-box {
      background:var(--surface3);border:1px solid var(--border);
      border-radius:8px;padding:14px 16px;font-size:.82rem;
      color:var(--text2);max-height:200px;overflow-y:auto;
      white-space:pre-wrap;margin:12px 0;display:none;
    }

    /* ---- PROGRESS BAR ---- */
    .update-progress { display:none;margin-top:16px; }
    .prog-bar-wrap { background:var(--surface3);border-radius:8px;height:8px;overflow:hidden;margin:8px 0; }
    .prog-bar { height:100%;background:var(--accent);border-radius:8px;width:0%;transition:width .3s ease; }
    .prog-label { font-size:.78rem;color:var(--text3);margin-top:4px; }

    /* ---- FERTIG ---- */
    .fertig-icon {
      width:72px;height:72px;border-radius:50%;
      background:rgba(52,211,153,.15);border:2px solid var(--green);
      display:flex;align-items:center;justify-content:center;
      font-size:2rem;margin:0 auto 20px;
      animation: popIn .4s cubic-bezier(.175,.885,.32,1.275);
    }
    @keyframes popIn { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }

    .backup-list { font-size:.8rem;color:var(--text3);margin-top:8px; }
    .backup-list li { padding:2px 0; }
  </style>
</head>
<body>

<!-- Header -->
<div class="inst-header">
  <div class="logo"><span class="logo-dot"></span> Projekt-Timeline</div>
  <span class="inst-badge"><?= $istInstalliert ? 'Update-Manager' : 'Installation' ?></span>
</div>

<?php if (!$istInstalliert): ?>
<!-- ============================================================
     INSTALLATIONS-WIZARD
     ============================================================ -->

<!-- Fortschritts-Schritte -->
<div class="progress-steps">
  <?php
  $schritte = ['Willkommen','Datenbank','Tabellen','Admin','Fertig'];
  foreach ($schritte as $i => $label):
      $nr     = $i + 1;
      $isDone = $nr < $schritt;
      $isAkt  = $nr === $schritt;
  ?>
  <div class="step-item">
    <div class="step-circle <?= $isDone?'done':($isAkt?'active':'') ?>">
      <?= $isDone ? '<i class="bi bi-check-lg"></i>' : $nr ?>
    </div>
    <div class="step-label <?= $isDone?'done':($isAkt?'active':'') ?>"><?= $label ?></div>
  </div>
  <?php if ($nr < count($schritte)): ?>
  <div class="step-line <?= $isDone?'done':'' ?>"></div>
  <?php endif; ?>
  <?php endforeach; ?>
</div>

<!-- Schritt-Inhalte -->
<div style="padding:0 16px">

<?php if ($schritt === 1): ?>
<!-- Schritt 1: Willkommen -->
<div class="inst-card text-center">
  <div style="font-size:3rem;margin-bottom:16px">🚀</div>
  <div class="inst-title">Willkommen!</div>
  <div class="inst-sub">Projekt-Timeline wird jetzt auf deinem Server installiert.<br>Der Vorgang dauert nur wenige Minuten.</div>

  <div class="status-box status-info">
    <i class="bi bi-info-circle-fill"></i>
    <span>Du benötigst: PHP 7.1+, MySQL/MariaDB, eine leere Datenbank</span>
  </div>

  <form method="POST">
    <input type="hidden" name="action" value="db_speichern">
    <div style="text-align:left">
      <div class="mb-3">
        <label class="form-label">Datenbankserver (Host)</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-server"></i></span>
          <input class="form-control" name="db_host" value="localhost" placeholder="localhost oder IP" required>
        </div>
        <div class="form-text" style="color:var(--text3)">Bei Strato z.B. mysql5-12.server.lan</div>
      </div>
      <div class="mb-3">
        <label class="form-label">Datenbankname</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-database"></i></span>
          <input class="form-control" name="db_name" placeholder="projekt_timeline" required>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label">Benutzername</label>
          <input class="form-control" name="db_user" placeholder="root" required>
        </div>
        <div class="col-6">
          <label class="form-label">Passwort</label>
          <input class="form-control" type="password" name="db_pass" placeholder="••••••••">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Tabellen-Präfix</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-tag"></i></span>
          <input class="form-control" name="db_prefix" value="tl_" placeholder="tl_">
        </div>
        <div class="form-text" style="color:var(--text3)">Nützlich bei geteilten Datenbanken (z.B. Strato)</div>
      </div>
    </div>

    <!-- Verbindungstest -->
    <div class="d-flex gap-2 align-items-center mb-3">
      <button type="button" class="btn-outline-light-custom" onclick="testVerbindung()">
        <span id="test-spinner" class="spinner-border spinner-border-sm"></span>
        <i class="bi bi-plug" id="test-icon"></i> Verbindung testen
      </button>
    </div>
    <div class="test-result status-box" id="test-result"></div>

    <button type="submit" class="btn-accent w-100">
      Weiter <i class="bi bi-arrow-right"></i>
    </button>
  </form>
</div>

<?php elseif ($schritt === 3): ?>
<!-- Schritt 3: Tabellen anlegen -->
<div class="inst-card text-center">
  <div style="font-size:3rem;margin-bottom:16px">🗄️</div>
  <div class="inst-title">Tabellen anlegen</div>
  <div class="inst-sub">Folgende Tabellen werden in der Datenbank <strong><?= htmlspecialchars($_SESSION['install_db']['name'] ?? '') ?></strong> erstellt:</div>

  <?php if ($fehler): ?>
  <div class="status-box status-err"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($fehler) ?></div>
  <?php endif; ?>

  <?php $p = $_SESSION['install_db']['prefix'] ?? 'tl_'; ?>
  <div style="text-align:left;background:var(--surface2);border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:.83rem;font-family:monospace;color:var(--text2)">
    <?php foreach (['projekte','benutzer','projekt_benutzer','rubriken','eintraege','timeline_schritte','anhaenge'] as $t): ?>
    <div><span style="color:var(--green)">✓</span> <?= $p.$t ?></div>
    <?php endforeach; ?>
  </div>

  <form method="POST">
    <input type="hidden" name="action" value="tabellen_anlegen">
    <button type="submit" class="btn-accent w-100" id="tbl-btn" onclick="this.innerHTML='<span class=\'spinner-border spinner-border-sm\'></span> Erstelle Tabellen…'">
      <i class="bi bi-database-add"></i> Tabellen jetzt anlegen
    </button>
  </form>
</div>

<?php elseif ($schritt === 4): ?>
<!-- Schritt 4: Admin-Konto -->
<div class="inst-card">
  <div class="inst-title">Admin-Konto erstellen</div>
  <div class="inst-sub">Dieser Benutzer hat vollen Zugriff auf alle Projekte und die Benutzerverwaltung.</div>

  <?php if ($fehler): ?>
  <div class="status-box status-err"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($fehler) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="action" value="admin_anlegen">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input class="form-control" name="admin_name" value="Admin" required>
    </div>
    <div class="mb-3">
      <label class="form-label">E-Mail-Adresse *</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        <input class="form-control" type="email" name="admin_email" placeholder="admin@example.com" required>
      </div>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-6">
        <label class="form-label">Passwort * (min. 6 Zeichen)</label>
        <input class="form-control" type="password" name="admin_pass" placeholder="••••••••" required>
      </div>
      <div class="col-6">
        <label class="form-label">Passwort wiederholen</label>
        <input class="form-control" type="password" name="admin_pass2" placeholder="••••••••" required>
      </div>
    </div>
    <button type="submit" class="btn-accent w-100">
      <i class="bi bi-person-check"></i> Installation abschließen
    </button>
  </form>
</div>

<?php elseif ($schritt === 5): ?>
<!-- Schritt 5: Fertig -->
<div class="inst-card text-center">
  <div class="fertig-icon">✓</div>
  <div class="inst-title">Installation erfolgreich!</div>
  <div class="inst-sub" style="margin-bottom:24px">Projekt-Timeline ist bereit. Die <code>config.php</code> wurde automatisch erstellt.</div>

  <div class="status-box status-ok" style="text-align:left">
    <i class="bi bi-shield-check-fill"></i>
    <div>
      <strong>Sicherheitshinweis:</strong><br>
      <small>Beschränke den Zugriff auf <code>install.php</code> oder lösche sie nach der Installation.</small>
    </div>
  </div>

  <a href="index.php" class="btn-accent" style="text-decoration:none;display:inline-flex">
    <i class="bi bi-rocket-takeoff"></i> Jetzt starten
  </a>
</div>

<?php endif; ?>
</div>

<?php else: ?>
<!-- ============================================================
     UPDATE-MANAGER (bereits installiert)
     ============================================================ -->
<div style="padding:0 16px">
<div class="inst-card">
  <div class="inst-title"><i class="bi bi-arrow-repeat me-2" style="color:var(--accent)"></i>Update-Manager</div>
  <div class="inst-sub">Prüft GitHub auf neue Versionen und führt Updates sicher durch.</div>

  <!-- Aktuelle Version -->
  <div class="version-current">
    <div>
      <div style="font-size:.75rem;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Installierte Version</div>
      <div style="font-size:1.1rem;font-weight:600"><?= htmlspecialchars(lokaleVersion()) ?></div>
    </div>
    <span class="version-badge ms-auto">Aktuell</span>
  </div>

  <!-- GitHub-Check -->
  <div id="github-check">
    <button class="btn-accent" onclick="githubPruefen()">
      <i class="bi bi-github"></i> GitHub prüfen
    </button>
  </div>

  <!-- Ergebnis -->
  <div id="update-result" style="display:none;margin-top:16px">
    <div id="update-status" class="status-box"></div>

    <div id="update-neu" style="display:none">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div>
          <div style="font-size:.75rem;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Verfügbare Version</div>
          <div style="font-size:1.1rem;font-weight:600" id="neue-version"></div>
        </div>
        <span class="version-badge update ms-auto">Update verfügbar</span>
      </div>

      <button class="btn-outline-light-custom btn-sm mb-2" onclick="toggleChangelog()">
        <i class="bi bi-card-text"></i> Changelog anzeigen
      </button>
      <div class="changelog-box" id="changelog-box"></div>

      <!-- Update-Schritte -->
      <div class="update-progress" id="update-progress">
        <div class="prog-bar-wrap"><div class="prog-bar" id="prog-bar"></div></div>
        <div class="prog-label" id="prog-label">Starte…</div>
      </div>

      <div class="d-flex gap-2 mt-3" id="update-btn-wrap">
        <button class="btn-accent" onclick="updateStarten()">
          <i class="bi bi-cloud-download"></i> Backup + Update durchführen
        </button>
      </div>

      <div class="status-box status-info mt-3" style="font-size:.8rem">
        <i class="bi bi-info-circle"></i>
        <div>
          Das Update sichert <code>config.php</code> und die Datenbank automatisch in <code>backups/</code>.
          Die <code>config.php</code> wird <strong>nicht</strong> überschrieben.
        </div>
      </div>
    </div>
  </div>

  <!-- Backup-Liste -->
  <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
    <div style="font-size:.78rem;font-weight:500;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">
      <i class="bi bi-archive me-1"></i> Gespeicherte Backups
    </div>
    <?php
    $backups = array_merge(
        glob(BACKUP_DIR . '/config_*.php') ?: [],
        glob(BACKUP_DIR . '/db_*.sql') ?: []
    );
    rsort($backups);
    ?>
    <?php if ($backups): ?>
    <ul class="backup-list list-unstyled">
      <?php foreach (array_slice($backups, 0, 8) as $b): ?>
      <li><i class="bi bi-file-earmark-code me-1" style="color:var(--accent)"></i>
        <?= htmlspecialchars(basename($b)) ?>
        <span style="color:var(--text3);font-size:.75rem"> — <?= round(filesize($b)/1024, 1) ?> KB</span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div style="color:var(--text3);font-size:.82rem">Noch keine Backups vorhanden.</div>
    <?php endif; ?>
  </div>
</div>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ---- Verbindungstest ----
async function testVerbindung() {
  const form = document.querySelector('form');
  const fd   = new FormData(form);
  document.getElementById('test-spinner').style.display = 'inline-block';
  document.getElementById('test-icon').style.display    = 'none';
  const result = document.getElementById('test-result');
  result.style.display = 'none';

  const r    = await fetch('install.php?ajax=db_test', {method:'POST', body: new URLSearchParams({
    host: fd.get('db_host'),
    name: fd.get('db_name'),
    user: fd.get('db_user'),
    pass: fd.get('db_pass'),
  })});
  const data = await r.json();

  document.getElementById('test-spinner').style.display = 'none';
  document.getElementById('test-icon').style.display    = 'inline';
  result.style.display = 'flex';
  result.className = 'test-result status-box ' + (data.ok ? 'status-ok' : 'status-err');
  result.innerHTML = `<i class="bi bi-${data.ok?'check-circle-fill':'x-circle-fill'}"></i> ${data.msg}`;
}

// ---- GitHub prüfen ----
async function githubPruefen() {
  const btn = document.querySelector('#github-check button');
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Prüfe GitHub…';
  btn.disabled  = true;

  const r    = await fetch('install.php?ajax=github_check');
  const data = await r.json();

  document.getElementById('update-result').style.display = 'block';
  const status = document.getElementById('update-status');

  if (!data.ok) {
    status.className = 'status-box status-err';
    status.innerHTML = `<i class="bi bi-x-circle-fill"></i> ${data.msg}`;
    return;
  }

  if (!data.update) {
    status.className = 'status-box status-ok';
    status.innerHTML = `<i class="bi bi-check-circle-fill"></i> Du hast bereits die neueste Version <strong>${data.lokal}</strong>!`;
    btn.innerHTML = '<i class="bi bi-github"></i> GitHub prüfen';
    btn.disabled  = false;
    return;
  }

  status.className = 'status-box status-warning';
  status.innerHTML = `<i class="bi bi-arrow-up-circle-fill"></i> Version <strong>${data.remote}</strong> verfügbar (veröffentlicht ${data.published})`;

  document.getElementById('update-neu').style.display  = 'block';
  document.getElementById('neue-version').textContent  = data.remote;
  document.getElementById('changelog-box').textContent = data.changelog || 'Kein Changelog verfügbar.';
  document.getElementById('update-btn-wrap').dataset.zipUrl = data.zip_url;

  btn.innerHTML = '<i class="bi bi-github"></i> Erneut prüfen';
  btn.disabled  = false;
}

function toggleChangelog() {
  const box = document.getElementById('changelog-box');
  box.style.display = box.style.display === 'block' ? 'none' : 'block';
}

// ---- Update durchführen ----
async function updateStarten() {
  const zipUrl = document.getElementById('update-btn-wrap').dataset.zipUrl;
  const prog   = document.getElementById('update-progress');
  const bar    = document.getElementById('prog-bar');
  const label  = document.getElementById('prog-label');
  const btnWrap = document.getElementById('update-btn-wrap');

  btnWrap.style.display = 'none';
  prog.style.display    = 'block';

  // Schritt 1: Backup
  fortschritt(bar, 20, label, 'Erstelle Backup…');
  const bk = await fetch('install.php?ajax=backup', {method:'POST'});
  const bkData = await bk.json();
  if (!bkData.ok) { fehlerZeigen(label, bkData.msg); return; }

  // Schritt 2: Update laden
  fortschritt(bar, 50, label, 'Lade Update von GitHub…');
  const up = await fetch('install.php?ajax=update', {method:'POST',
    body: new URLSearchParams({zip_url: zipUrl})});
  const upData = await up.json();
  if (!upData.ok) { fehlerZeigen(label, upData.msg); return; }

  // Fertig
  fortschritt(bar, 100, label, '✓ Update abgeschlossen!');
  bar.style.background = '#34d399';

  setTimeout(() => {
    document.getElementById('update-result').innerHTML = `
      <div class="status-box status-ok">
        <i class="bi bi-check-circle-fill"></i>
        <div><strong>Update erfolgreich!</strong><br>
        <small>${bkData.msg} · ${upData.msg}</small></div>
      </div>
      <a href="index.php" style="text-decoration:none" class="btn-accent d-inline-flex mt-3">
        <i class="bi bi-rocket-takeoff"></i> App neu starten
      </a>`;
  }, 800);
}

function fortschritt(bar, pct, label, text) {
  bar.style.width    = pct + '%';
  label.textContent  = text;
}
function fehlerZeigen(label, msg) {
  label.textContent = '✕ Fehler: ' + msg;
  label.style.color = '#f87171';
  document.getElementById('update-btn-wrap').style.display = 'flex';
}
</script>
</body>
</html>