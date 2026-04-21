<?php
// ============================================================
//  config.example.php — Vorlage für config.php
//
//  Diese Datei kopieren → config.php und Werte eintragen.
//  config.php wird bei Updates NICHT überschrieben.
//  Tabellennamen stehen in tbl.php (wird bei Updates aktualisiert).
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'dein_db_benutzer');
define('DB_PASS', 'dein_db_passwort');
define('DB_NAME', 'deine_datenbank');

// Tabellen-Präfix (bei Strato mit geteilter DB empfohlen)
define('DB_PREFIX', 'tl_');

// Tabellennamen aus tbl.php laden (wird bei Updates aktualisiert)
require_once __DIR__ . '/tbl.php';

// ============================================================
//  Erster Admin — nur beim allerersten Aufruf aktiv
//  Danach diese beiden Zeilen leer lassen oder entfernen!
// ============================================================
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_PASS',  'sicheres_passwort_hier_eintragen');
