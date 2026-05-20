#!/usr/bin/env php
<?php
// ============================================================
//  migrate.php — Migration von 1.3.0 → 1.6.0
//  Einmalig im Hauptverzeichnis ausführen:
//  php migrate.php  ODER  https://deinedomain.de/migrate.php
//  Danach diese Datei löschen!
// ============================================================

$log = [];
$fehler = [];

function ok($msg)  { global $log;    $log[]    = ['ok',  $msg]; }
function err($msg) { global $fehler; $fehler[] = ['err', $msg]; }
function info($msg){ global $log;    $log[]    = ['info',$msg]; }

// ============================================================
//  1. Prüfen ob alte config.php vorhanden
// ============================================================
if (!file_exists(__DIR__ . '/config.php')) {
    err('config.php nicht gefunden — bitte zuerst hochladen');
    goto ausgabe;
}

require_once __DIR__ . '/config.php';
info('config.php gefunden');

// ============================================================
//  2. src/ Ordner anlegen
// ============================================================
foreach (['src', 'install', 'backups'] as $dir) {
    if (!is_dir(__DIR__ . "/$dir")) {
        if (mkdir(__DIR__ . "/$dir", 0755, true)) {
            ok("Ordner /$dir angelegt");
        } else {
            err("Ordner /$dir konnte nicht angelegt werden — bitte manuell anlegen");
        }
    } else {
        info("Ordner /$dir bereits vorhanden");
    }
}

// ============================================================
//  3. Neue config.php schreiben (mit require src/tbl.php)
//     Nur wenn TBL_KOMMENTARE noch nicht definiert ist
// ============================================================
if (!defined('TBL_KOMMENTARE')) {
    $host   = DB_HOST;
    $user   = DB_USER;
    $pass   = DB_PASS;
    $name   = DB_NAME;
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';

    $newConfig = "<?php\n"
        . "// Projekt-Timeline — Konfiguration\n"
        . "// Migriert: " . date('Y-m-d H:i:s') . "\n\n"
        . "define('DB_HOST', '" . addslashes($host)   . "');\n"
        . "define('DB_USER', '" . addslashes($user)   . "');\n"
        . "define('DB_PASS', '" . addslashes($pass)   . "');\n"
        . "define('DB_NAME', '" . addslashes($name)   . "');\n\n"
        . "define('DB_PREFIX', '" . addslashes($prefix) . "');\n\n"
        . "// Tabellennamen aus src/tbl.php laden\n"
        . "require_once __DIR__ . '/src/tbl.php';\n\n"
        . "define('ADMIN_EMAIL', '');\n"
        . "define('ADMIN_PASS',  '');\n";

    if (file_put_contents(__DIR__ . '/config.php', $newConfig)) {
        ok('config.php auf neue Struktur migriert (TBL_* nach src/tbl.php ausgelagert)');
    } else {
        err('config.php konnte nicht geschrieben werden — Schreibrechte prüfen');
    }
} else {
    info('config.php bereits auf neuem Stand');
}

// ============================================================
//  4. Alte Dateien im Root die in src/ verschoben wurden
// ============================================================
$zuVerschieben = ['auth.php', 'db.php', 'tbl.php'];
foreach ($zuVerschieben as $datei) {
    if (file_exists(__DIR__ . "/$datei") && !file_exists(__DIR__ . "/src/$datei")) {
        info("$datei noch im Root vorhanden — wird von src/ überschrieben sobald neue Dateien hochgeladen sind");
    }
}

// ============================================================
//  5. DB-Verbindung herstellen und neue Tabellen anlegen
// ============================================================
if (!file_exists(__DIR__ . '/src/tbl.php')) {
    err('src/tbl.php fehlt — bitte erst neue Dateien hochladen, dann erneut ausführen');
    goto ausgabe;
}

// tbl.php neu laden falls config.php gerade neu geschrieben wurde
if (!defined('TBL_KOMMENTARE')) {
    require_once __DIR__ . '/src/tbl.php';
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    ok('Datenbankverbindung erfolgreich');
} catch (Exception $e) {
    err('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
    goto ausgabe;
}

// ---- Vorhandene Tabellen prüfen ----
$vorhandene = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
info('Vorhandene Tabellen: ' . implode(', ', $vorhandene));

// ---- Neue Spalten nachrüsten ----
$nachruestungen = [
    TBL_RUBRIKEN  => [['erstellt_von', "INT NULL"]],
    TBL_EINTRAEGE => [['erstellt_von', "INT NULL"]],
    TBL_SCHRITTE  => [['erstellt_von', "INT NULL"]],
];
foreach ($nachruestungen as $tbl => $spalten) {
    if (!in_array($tbl, $vorhandene)) continue;
    foreach ($spalten as [$spalte, $typ]) {
        $exists = $pdo->query("SHOW COLUMNS FROM `$tbl` LIKE '$spalte'")->fetch();
        if (!$exists) {
            try {
                $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN `$spalte` $typ");
                ok("$tbl: Spalte '$spalte' hinzugefügt");
            } catch (Exception $e) {
                err("$tbl: Spalte '$spalte' konnte nicht hinzugefügt werden: " . $e->getMessage());
            }
        } else {
            info("$tbl: Spalte '$spalte' bereits vorhanden");
        }
    }
}

// ---- anhang_count Subquery braucht TBL_ANHAENGE ----
// Prüfen ob tl_anhaenge vorhanden
if (!in_array(TBL_ANHAENGE, $vorhandene)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `" . TBL_ANHAENGE . "` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            typ ENUM('eintrag','schritt') NOT NULL,
            referenz_id INT NOT NULL,
            titel VARCHAR(200) NOT NULL,
            sprache VARCHAR(30) DEFAULT 'plaintext',
            inhalt LONGTEXT NOT NULL,
            erstellt_von INT NULL,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (erstellt_von) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        ok(TBL_ANHAENGE . ' Tabelle angelegt');
    } catch (Exception $e) {
        err(TBL_ANHAENGE . ' konnte nicht angelegt werden: ' . $e->getMessage());
    }
} else {
    info(TBL_ANHAENGE . ' bereits vorhanden');
}

// ---- Kommentare-Tabelle ----
if (!in_array(TBL_KOMMENTARE, $vorhandene)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `" . TBL_KOMMENTARE . "` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            typ ENUM('eintrag','schritt','board') NOT NULL,
            referenz_id INT NOT NULL,
            eltern_id INT NULL,
            inhalt TEXT NOT NULL,
            ist_entscheidung TINYINT(1) DEFAULT 0,
            erstellt_von INT NULL,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (erstellt_von) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE SET NULL,
            FOREIGN KEY (eltern_id) REFERENCES `" . TBL_KOMMENTARE . "`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        ok(TBL_KOMMENTARE . ' Tabelle angelegt');
    } catch (Exception $e) {
        err(TBL_KOMMENTARE . ' konnte nicht angelegt werden: ' . $e->getMessage());
    }
} else {
    // eltern_id und board-Typ nachrüsten falls nötig
    $col = $pdo->query("SHOW COLUMNS FROM `" . TBL_KOMMENTARE . "` LIKE 'eltern_id'")->fetch();
    if (!$col) {
        try {
            $pdo->exec("ALTER TABLE `" . TBL_KOMMENTARE . "` ADD COLUMN `eltern_id` INT NULL, ADD FOREIGN KEY (eltern_id) REFERENCES `" . TBL_KOMMENTARE . "`(id) ON DELETE CASCADE");
            ok(TBL_KOMMENTARE . ': eltern_id hinzugefügt');
        } catch (Exception $e) {
            err(TBL_KOMMENTARE . ': eltern_id Fehler: ' . $e->getMessage());
        }
    } else {
        info(TBL_KOMMENTARE . ': eltern_id bereits vorhanden');
    }
    $typCol = $pdo->query("SHOW COLUMNS FROM `" . TBL_KOMMENTARE . "` LIKE 'typ'")->fetch();
    if ($typCol && strpos($typCol['Type'], 'board') === false) {
        try {
            $pdo->exec("ALTER TABLE `" . TBL_KOMMENTARE . "` MODIFY COLUMN `typ` ENUM('eintrag','schritt','board') NOT NULL");
            ok(TBL_KOMMENTARE . ': typ ENUM um board erweitert');
        } catch (Exception $e) {
            err(TBL_KOMMENTARE . ': typ ENUM Fehler: ' . $e->getMessage());
        }
    } else {
        info(TBL_KOMMENTARE . ': typ ENUM bereits aktuell');
    }
}

// ---- Reaktionen-Tabelle ----
if (!in_array(TBL_REAKTIONEN, $vorhandene)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `" . TBL_REAKTIONEN . "` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kommentar_id INT NOT NULL,
            typ ENUM('👍','👎','❤️','🤔') NOT NULL,
            benutzer_id INT NOT NULL,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_reaktion (kommentar_id, benutzer_id),
            FOREIGN KEY (kommentar_id) REFERENCES `" . TBL_KOMMENTARE . "`(id) ON DELETE CASCADE,
            FOREIGN KEY (benutzer_id) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        ok(TBL_REAKTIONEN . ' Tabelle angelegt');
    } catch (Exception $e) {
        err(TBL_REAKTIONEN . ' konnte nicht angelegt werden: ' . $e->getMessage());
    }
} else {
    info(TBL_REAKTIONEN . ' bereits vorhanden');
}

// ---- Board-Themen-Tabelle ----
if (!in_array(TBL_BOARD_THEMEN, $vorhandene)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `" . TBL_BOARD_THEMEN . "` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            projekt_id INT NOT NULL,
            titel VARCHAR(300) NOT NULL,
            ref_typ ENUM('eintrag','schritt') NULL,
            ref_id INT NULL,
            rubrik_id INT NULL,
            erstellt_von INT NULL,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (projekt_id) REFERENCES `" . TBL_PROJEKTE . "`(id) ON DELETE CASCADE,
            FOREIGN KEY (rubrik_id) REFERENCES `" . TBL_RUBRIKEN . "`(id) ON DELETE SET NULL,
            FOREIGN KEY (erstellt_von) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        ok(TBL_BOARD_THEMEN . ' Tabelle angelegt');
    } catch (Exception $e) {
        err(TBL_BOARD_THEMEN . ' konnte nicht angelegt werden: ' . $e->getMessage());
    }
} else {
    info(TBL_BOARD_THEMEN . ' bereits vorhanden');
}

// ============================================================
//  Ausgabe
// ============================================================
ausgabe:
$istHtml = php_sapi_name() !== 'cli';
if ($istHtml): ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Migration 1.3.0 → 1.6.0</title>
<style>
  body { font-family: monospace; background: #0e0f14; color: #e8eaf0; padding: 2rem; max-width: 700px; margin: 0 auto; }
  h1 { font-size: 1.2rem; color: #7c6af7; margin-bottom: 1.5rem; }
  .ok   { color: #34d399; } .ok::before   { content: '✓  '; }
  .info { color: #9296a8; } .info::before { content: '→  '; }
  .err  { color: #f87171; } .err::before  { content: '✗  '; }
  li { margin: 4px 0; font-size: .88rem; list-style: none; }
  .summary { margin-top: 1.5rem; padding: 12px 16px; border-radius: 8px; font-size: .9rem; }
  .summary.ok  { background: rgba(52,211,153,.1); border: 1px solid rgba(52,211,153,.3); color: #34d399; }
  .summary.err { background: rgba(248,113,113,.1); border: 1px solid rgba(248,113,113,.3); color: #f87171; }
  a { color: #7c6af7; }
  .hinweis { margin-top: 1rem; padding: 12px 16px; border-radius: 8px; background: rgba(240,180,41,.1); border: 1px solid rgba(240,180,41,.3); color: #f0b429; font-size: .84rem; }
</style>
</head>
<body>
<h1>🔄 Migration 1.3.0 → 1.6.0</h1>
<ul>
<?php foreach (array_merge($log, $fehler) as [$typ, $msg]): ?>
  <li class="<?= $typ ?>"><?= htmlspecialchars($msg) ?></li>
<?php endforeach; ?>
</ul>

<?php if (empty($fehler)): ?>
<div class="summary ok">✓ Migration erfolgreich abgeschlossen!</div>
<div class="hinweis">
  ⚠️ Nächste Schritte:<br>
  1. Alle neuen Dateien aus der 1.6.0 ZIP hochladen (src/, install/, assets/js/board.js, assets/js/diskussion.js etc.)<br>
  2. Alte Dateien im Root löschen: auth.php, db.php, tbl.php, config.example.php<br>
  3. <strong>Diese migrate.php Datei löschen!</strong><br>
  4. <a href="index.php">App starten</a>
</div>
<?php else: ?>
<div class="summary err">✗ Es gab <?= count($fehler) ?> Fehler — bitte beheben und erneut ausführen</div>
<?php endif; ?>

</body>
</html>
<?php else:
    // CLI Ausgabe
    foreach (array_merge($log, $fehler) as [$typ, $msg]) {
        $prefix = $typ === 'ok' ? '✓' : ($typ === 'err' ? '✗' : '→');
        echo "$prefix $msg\n";
    }
    if (empty($fehler)) {
        echo "\n✓ Migration erfolgreich!\n";
        echo "→ Jetzt neue Dateien hochladen und migrate.php löschen.\n";
    } else {
        echo "\n✗ " . count($fehler) . " Fehler aufgetreten.\n";
    }
endif;