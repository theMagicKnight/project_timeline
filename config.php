<?php
// ============================================================
//  Datenbank-Konfiguration — hier anpassen
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'projekt_timeline');

// ============================================================
//  Tabellen-Präfix — bei Strato teilen sich oft mehrere Apps
//  eine Datenbank. Präfix verhindert Namenskonflikte.
//  Beispiel: 'tl_' → Tabellen heißen tl_projekte, tl_rubriken …
//  Leer lassen ('') wenn du eine eigene Datenbank hast.
// ============================================================
define('DB_PREFIX', '');

// Fertige Tabellennamen — diese überall im Code verwenden
define('TBL_PROJEKTE',         DB_PREFIX . 'projekte');
define('TBL_RUBRIKEN',         DB_PREFIX . 'rubriken');
define('TBL_EINTRAEGE',        DB_PREFIX . 'eintraege');
define('TBL_SCHRITTE',         DB_PREFIX . 'timeline_schritte');
define('TBL_BENUTZER',         DB_PREFIX . 'benutzer');
define('TBL_PROJEKT_BENUTZER', DB_PREFIX . 'projekt_benutzer');

// ============================================================
//  Erster Admin — wird beim allerersten Aufruf automatisch angelegt
//  Danach diese Werte leer lassen oder aus der Datei entfernen
// ============================================================
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_PASS',  'admin123');

