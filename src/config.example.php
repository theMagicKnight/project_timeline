<?php
// ============================================================
//  config.example.php — Vorlage für config.php
//
//  Diese Datei ins HAUPTVERZEICHNIS kopieren → config.php
//  und Werte eintragen. config.php wird bei Updates NICHT
//  überschrieben. Tabellennamen stehen in src/tbl.php.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'dein_db_benutzer');
define('DB_PASS', 'dein_db_passwort');
define('DB_NAME', 'deine_datenbank');

// Tabellen-Präfix (bei Strato mit geteilter DB empfohlen)
define('DB_PREFIX', 'tl_');

// Tabellennamen aus src/tbl.php laden
require_once __DIR__ . '/src/tbl.php';

// ============================================================
//  Erster Admin — nur beim allerersten Aufruf aktiv
//  Danach leer lassen oder entfernen!
// ============================================================
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_PASS',  'sicheres_passwort');