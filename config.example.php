<?php
// ============================================================
//  Konfiguration — config.example.php
//  Diese Datei kopieren → config.php und Werte eintragen
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'dein_db_benutzer');
define('DB_PASS', 'dein_db_passwort');
define('DB_NAME', 'deine_datenbank');

// Tabellen-Präfix (bei Strato mit geteilter DB empfohlen)
define('DB_PREFIX', 'tl_');

// Fertige Tabellennamen — nicht ändern
define('TBL_PROJEKTE',         DB_PREFIX . 'projekte');
define('TBL_RUBRIKEN',         DB_PREFIX . 'rubriken');
define('TBL_EINTRAEGE',        DB_PREFIX . 'eintraege');
define('TBL_SCHRITTE',         DB_PREFIX . 'timeline_schritte');
define('TBL_BENUTZER',         DB_PREFIX . 'benutzer');
define('TBL_PROJEKT_BENUTZER', DB_PREFIX . 'projekt_benutzer');

// ============================================================
//  Erster Admin — nur beim allerersten Aufruf aktiv
//  Danach diese beiden Zeilen leer lassen oder entfernen!
// ============================================================
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_PASS',  'sicheres_passwort_hier_eintragen');
