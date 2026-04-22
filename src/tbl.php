<?php
// ============================================================
//  tbl.php — Tabellennamen
//
//  Diese Datei wird bei Updates automatisch überschrieben.
//  Neue Tabellen werden hier ergänzt — config.php bleibt
//  dabei IMMER unberührt.
//
//  Voraussetzung: DB_PREFIX muss in config.php definiert sein.
// ============================================================

define('TBL_PROJEKTE',         DB_PREFIX . 'projekte');
define('TBL_RUBRIKEN',         DB_PREFIX . 'rubriken');
define('TBL_EINTRAEGE',        DB_PREFIX . 'eintraege');
define('TBL_SCHRITTE',         DB_PREFIX . 'timeline_schritte');
define('TBL_BENUTZER',         DB_PREFIX . 'benutzer');
define('TBL_PROJEKT_BENUTZER', DB_PREFIX . 'projekt_benutzer');
define('TBL_ANHAENGE',         DB_PREFIX . 'anhaenge');
define('TBL_KOMMENTARE',       DB_PREFIX . 'kommentare');
define('TBL_REAKTIONEN',       DB_PREFIX . 'reaktionen');
define('TBL_BOARD_THEMEN',     DB_PREFIX . 'board_themen');