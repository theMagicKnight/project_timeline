<?php
require_once __DIR__ . '/config.php';

$pdo = null;
$db_error = null;

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // ---- Tabellen in korrekter Reihenfolge anlegen ----
    // 1. Projekte (keine Abhängigkeiten)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . TBL_PROJEKTE . "` (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            name         VARCHAR(200) NOT NULL,
            beschreibung TEXT,
            farbe        VARCHAR(7) DEFAULT '#4f8ef7',
            erstellt_am  DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // 2. Benutzer (keine Abhängigkeiten)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . TBL_BENUTZER . "` (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            name         VARCHAR(100) NOT NULL,
            email        VARCHAR(200) NOT NULL UNIQUE,
            passwort     VARCHAR(255) NOT NULL,
            rolle        ENUM('admin','benutzer') DEFAULT 'benutzer',
            theme        ENUM('dark','light') DEFAULT 'dark',
            aktiv        TINYINT(1) DEFAULT 1,
            erstellt_am  DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // 3. Projekt-Benutzer (braucht projekte + benutzer)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . TBL_PROJEKT_BENUTZER . "` (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            projekt_id   INT NOT NULL,
            benutzer_id  INT NOT NULL,
            recht        ENUM('lesen','schreiben','verwalten') DEFAULT 'lesen',
            UNIQUE KEY uq_pb (projekt_id, benutzer_id),
            FOREIGN KEY (projekt_id)  REFERENCES `" . TBL_PROJEKTE . "`(id) ON DELETE CASCADE,
            FOREIGN KEY (benutzer_id) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // 4. Rubriken (braucht projekte + benutzer)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . TBL_RUBRIKEN . "` (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            projekt_id   INT NOT NULL,
            name         VARCHAR(200) NOT NULL,
            beschreibung TEXT,
            sortierung   INT DEFAULT 0,
            erstellt_von INT NULL,
            erstellt_am  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (projekt_id)   REFERENCES `" . TBL_PROJEKTE . "`(id) ON DELETE CASCADE,
            FOREIGN KEY (erstellt_von) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;
    ");

    // 5. Einträge (braucht rubriken + benutzer)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . TBL_EINTRAEGE . "` (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            rubrik_id    INT NOT NULL,
            titel        VARCHAR(300) NOT NULL,
            beschreibung TEXT,
            phase        ENUM('idee','start','entwicklung','abschluss') DEFAULT 'idee',
            phase_datum  DATE,
            farbe        VARCHAR(7) DEFAULT '#4f8ef7',
            sortierung   INT DEFAULT 0,
            erstellt_von INT NULL,
            erstellt_am  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (rubrik_id)    REFERENCES `" . TBL_RUBRIKEN . "`(id) ON DELETE CASCADE,
            FOREIGN KEY (erstellt_von) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;
    ");

    // 6. Timeline-Schritte (braucht eintraege + benutzer)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . TBL_SCHRITTE . "` (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            eintrag_id   INT NOT NULL,
            phase        ENUM('idee','start','entwicklung','abschluss') NOT NULL,
            titel        VARCHAR(300) NOT NULL,
            beschreibung TEXT,
            datum        DATE,
            erstellt_von INT NULL,
            erstellt_am  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (eintrag_id)   REFERENCES `" . TBL_EINTRAEGE . "`(id) ON DELETE CASCADE,
            FOREIGN KEY (erstellt_von) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;
    ");

    // 7. Anhänge (Text/Code-Snippets an Einträge oder Schritte)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . TBL_ANHAENGE . "` (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            typ          ENUM('eintrag','schritt') NOT NULL,
            referenz_id  INT NOT NULL,
            titel        VARCHAR(200) NOT NULL,
            sprache      VARCHAR(30) DEFAULT 'plaintext',
            inhalt       LONGTEXT NOT NULL,
            erstellt_von INT NULL,
            erstellt_am  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (erstellt_von) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;
    ");

    // ---- Spalten nachrüsten falls Tabellen bereits existieren ----
    foreach ([TBL_RUBRIKEN, TBL_EINTRAEGE, TBL_SCHRITTE] as $tbl) {
        $cols = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'erstellt_von'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `{$tbl}`
                ADD COLUMN `erstellt_von` INT NULL,
                ADD FOREIGN KEY (erstellt_von) REFERENCES `" . TBL_BENUTZER . "`(id) ON DELETE SET NULL
            ");
        }
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}