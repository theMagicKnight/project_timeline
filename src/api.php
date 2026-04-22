<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['api'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if (!$pdo) throw new Exception("Keine Datenbankverbindung");

    // ===== AUTH ==============================================

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['ok' => true]);
        exit;
    }

    // Alle weiteren Endpunkte erfordern Login
    if (!istEingeloggt()) {
        http_response_code(401);
        echo json_encode(['error' => 'Nicht eingeloggt']);
        exit;
    }

    $ich = aktuellerBenutzer();

    // ===== EIGENES PROFIL ====================================

    if ($action === 'profil_aendern') {
        // Passwort ändern
        if (!empty($input['passwort_alt']) && !empty($input['passwort_neu'])) {
            $s = $pdo->prepare("SELECT passwort FROM `" . TBL_BENUTZER . "` WHERE id=?");
            $s->execute([$ich['id']]);
            $row = $s->fetch();
            if (!$row || !password_verify($input['passwort_alt'], $row['passwort'])) {
                echo json_encode(['error' => 'Aktuelles Passwort ist falsch']); exit;
            }
            if (strlen($input['passwort_neu']) < 6) {
                echo json_encode(['error' => 'Neues Passwort muss mindestens 6 Zeichen haben']); exit;
            }
            $pdo->prepare("UPDATE `" . TBL_BENUTZER . "` SET passwort=? WHERE id=?")
                ->execute([password_hash($input['passwort_neu'], PASSWORD_DEFAULT), $ich['id']]);
        }
        // Name ändern
        if (!empty($input['name'])) {
            $pdo->prepare("UPDATE `" . TBL_BENUTZER . "` SET name=? WHERE id=?")
                ->execute([trim($input['name']), $ich['id']]);
            $_SESSION['benutzer']['name'] = trim($input['name']);
        }
        echo json_encode(['ok' => true]);

    } elseif ($action === 'theme_aendern') {
        $theme = $input['theme'] === 'light' ? 'light' : 'dark';
        $pdo->prepare("UPDATE `" . TBL_BENUTZER . "` SET theme=? WHERE id=?")->execute([$theme, $ich['id']]);
        $_SESSION['benutzer']['theme'] = $theme;
        echo json_encode(['ok' => true, 'theme' => $theme]);

    // ===== BENUTZERVERWALTUNG (Admin) ========================

    } elseif ($action === 'benutzer_liste') {
        apiZugang('admin');
        $rows = $pdo->query("SELECT id, name, email, rolle, theme, aktiv, erstellt_am FROM `" . TBL_BENUTZER . "` ORDER BY erstellt_am")->fetchAll();
        echo json_encode($rows);

    } elseif ($action === 'benutzer_erstellen') {
        apiZugang('admin');
        $name  = trim($input['name']  ?? '');
        $email = trim($input['email'] ?? '');
        $pass  = trim($input['passwort'] ?? '');
        $rolle = in_array($input['rolle'] ?? '', ['admin','benutzer']) ? $input['rolle'] : 'benutzer';
        if (!$name || !$email || !$pass) { echo json_encode(['error' => 'Name, E-Mail und Passwort erforderlich']); exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['error' => 'Ungültige E-Mail']); exit; }
        if (strlen($pass) < 6) { echo json_encode(['error' => 'Passwort mind. 6 Zeichen']); exit; }
        // Duplikat prüfen
        $dup = $pdo->prepare("SELECT id FROM `" . TBL_BENUTZER . "` WHERE email=?"); $dup->execute([$email]);
        if ($dup->fetch()) { echo json_encode(['error' => 'E-Mail bereits vergeben']); exit; }
        $s = $pdo->prepare("INSERT INTO `" . TBL_BENUTZER . "` (name, email, passwort, rolle) VALUES (?,?,?,?)");
        $s->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $rolle]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);

    } elseif ($action === 'benutzer_aktualisieren') {
        apiZugang('admin');
        $bid = (int)$input['id'];
        if (!empty($input['name'])) {
            $pdo->prepare("UPDATE `" . TBL_BENUTZER . "` SET name=? WHERE id=?")->execute([trim($input['name']), $bid]);
        }
        if (!empty($input['rolle']) && in_array($input['rolle'], ['admin','benutzer'])) {
            // Letzten Admin nicht degradieren
            if ($input['rolle'] === 'benutzer') {
                $admins = $pdo->query("SELECT COUNT(*) FROM `" . TBL_BENUTZER . "` WHERE rolle='admin' AND aktiv=1")->fetchColumn();
                $istDieserAdmin = $pdo->prepare("SELECT rolle FROM `" . TBL_BENUTZER . "` WHERE id=?"); $istDieserAdmin->execute([$bid]);
                $row = $istDieserAdmin->fetch();
                if ($admins <= 1 && $row['rolle'] === 'admin') { echo json_encode(['error' => 'Letzten Admin nicht degradieren']); exit; }
            }
            $pdo->prepare("UPDATE `" . TBL_BENUTZER . "` SET rolle=? WHERE id=?")->execute([$input['rolle'], $bid]);
        }
        if (isset($input['aktiv'])) {
            $pdo->prepare("UPDATE `" . TBL_BENUTZER . "` SET aktiv=? WHERE id=?")->execute([$input['aktiv'] ? 1 : 0, $bid]);
        }
        if (!empty($input['passwort_neu']) && strlen($input['passwort_neu']) >= 6) {
            $pdo->prepare("UPDATE `" . TBL_BENUTZER . "` SET passwort=? WHERE id=?")
                ->execute([password_hash($input['passwort_neu'], PASSWORD_DEFAULT), $bid]);
        }
        echo json_encode(['ok' => true]);

    } elseif ($action === 'benutzer_loeschen') {
        apiZugang('admin');
        $bid = (int)$input['id'];
        if ($bid === $ich['id']) { echo json_encode(['error' => 'Eigenes Konto nicht löschbar']); exit; }
        $pdo->prepare("DELETE FROM `" . TBL_BENUTZER . "` WHERE id=?")->execute([$bid]);
        echo json_encode(['ok' => true]);

    // ===== PROJEKTZUGANG (Admin) =============================

    } elseif ($action === 'projekt_benutzer_liste') {
        apiZugang('admin');
        $pid = (int)$_GET['id'];
        $s = $pdo->prepare("SELECT pb.id, pb.recht, b.id as benutzer_id, b.name, b.email, b.rolle
            FROM `" . TBL_PROJEKT_BENUTZER . "` pb JOIN `" . TBL_BENUTZER . "` b ON b.id=pb.benutzer_id
            WHERE pb.projekt_id=? ORDER BY b.name");
        $s->execute([$pid]);
        echo json_encode($s->fetchAll());

    } elseif ($action === 'projekt_benutzer_setzen') {
        apiZugang('admin');
        $pid   = (int)$input['projekt_id'];
        $bid   = (int)$input['benutzer_id'];
        $recht = in_array($input['recht'] ?? '', ['lesen','schreiben','verwalten']) ? $input['recht'] : 'lesen';
        $pdo->prepare("INSERT INTO `" . TBL_PROJEKT_BENUTZER . "` (projekt_id, benutzer_id, recht)
                       VALUES (?,?,?) ON DUPLICATE KEY UPDATE recht=?")
            ->execute([$pid, $bid, $recht, $recht]);
        echo json_encode(['ok' => true]);

    } elseif ($action === 'projekt_benutzer_entfernen') {
        apiZugang('admin');
        $pdo->prepare("DELETE FROM `" . TBL_PROJEKT_BENUTZER . "` WHERE projekt_id=? AND benutzer_id=?")
            ->execute([(int)$input['projekt_id'], (int)$input['benutzer_id']]);
        echo json_encode(['ok' => true]);

    // ===== PROJEKTE ==========================================

    } elseif ($action === 'projekte_liste') {
        // Admin sieht alle, andere nur ihre zugewiesenen
        if (istAdmin()) {
            $rows = $pdo->query("SELECT * FROM `" . TBL_PROJEKTE . "` ORDER BY erstellt_am DESC")->fetchAll();
        } else {
            $s = $pdo->prepare("SELECT p.*, pb.recht FROM `" . TBL_PROJEKTE . "` p
                JOIN projekt_benutzer pb ON pb.projekt_id=p.id
                WHERE pb.benutzer_id=? ORDER BY p.erstellt_am DESC");
            $s->execute([$ich['id']]);
            $rows = $s->fetchAll();
        }
        echo json_encode($rows);

    } elseif ($action === 'projekt_erstellen') {
        apiZugang('admin');
        $s = $pdo->prepare("INSERT INTO `" . TBL_PROJEKTE . "` (name,beschreibung,farbe) VALUES (?,?,?)");
        $s->execute([$input['name'], $input['beschreibung']??'', $input['farbe']??'#4f8ef7']);
        $pid = $pdo->lastInsertId();
        // Ersteller bekommt automatisch Verwalten-Recht
        $pdo->prepare("INSERT INTO `" . TBL_PROJEKT_BENUTZER . "` (projekt_id,benutzer_id,recht) VALUES (?,?,'verwalten')")
            ->execute([$pid, $ich['id']]);
        echo json_encode(['id' => $pid, 'ok' => true]);

    } elseif ($action === 'projekt_aktualisieren') {
        $recht = projektRecht((int)$input['id'], $pdo);
        if (!hatRecht($recht, 'verwalten')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("UPDATE `" . TBL_PROJEKTE . "` SET name=?,beschreibung=?,farbe=? WHERE id=?")
            ->execute([$input['name'], $input['beschreibung']??'', $input['farbe']??'#4f8ef7', $input['id']]);
        echo json_encode(['ok' => true]);

    } elseif ($action === 'projekt_loeschen') {
        apiZugang('admin');
        $pdo->prepare("DELETE FROM `" . TBL_PROJEKTE . "` WHERE id=?")->execute([$input['id']]);
        echo json_encode(['ok' => true]);

    } elseif ($action === 'projekt_detail') {
        $pid   = (int)$_GET['id'];
        $recht = projektRecht($pid, $pdo);
        if (!$recht) { http_response_code(403); echo json_encode(['error'=>'Kein Zugang']); exit; }
        $p = $pdo->prepare("SELECT * FROM `" . TBL_PROJEKTE . "` WHERE id=?"); $p->execute([$pid]); $projekt = $p->fetch();

        $r = $pdo->prepare("
            SELECT r.*, b.name AS erstellt_von_name
            FROM `" . TBL_RUBRIKEN . "` r
            LEFT JOIN `" . TBL_BENUTZER . "` b ON b.id = r.erstellt_von
            WHERE r.projekt_id=? ORDER BY r.sortierung, r.erstellt_am
        ");
        $r->execute([$pid]); $rubriken = $r->fetchAll();

        foreach ($rubriken as &$rub) {
            $e = $pdo->prepare("
                SELECT e.*, b.name AS erstellt_von_name,
                    (SELECT COUNT(*) FROM `" . TBL_ANHAENGE . "` a
                     WHERE a.typ='eintrag' AND a.referenz_id=e.id) AS anhang_count,
                    (SELECT COUNT(*) FROM `" . TBL_KOMMENTARE . "` k
                     WHERE k.typ='eintrag' AND k.referenz_id=e.id) AS kommentar_count
                FROM `" . TBL_EINTRAEGE . "` e
                LEFT JOIN `" . TBL_BENUTZER . "` b ON b.id = e.erstellt_von
                WHERE e.rubrik_id=? ORDER BY e.sortierung, e.erstellt_am
            ");
            $e->execute([$rub['id']]); $eintraege = $e->fetchAll();
            foreach ($eintraege as &$ent) {
                $s = $pdo->prepare("
                    SELECT ts.*, b.name AS erstellt_von_name
                    FROM `" . TBL_SCHRITTE . "` ts
                    LEFT JOIN `" . TBL_BENUTZER . "` b ON b.id = ts.erstellt_von
                    WHERE ts.eintrag_id=? ORDER BY ts.datum, ts.erstellt_am
                ");
                $s->execute([$ent['id']]); $ent['schritte'] = $s->fetchAll();
            }
            $rub['eintraege'] = $eintraege;
        }
        echo json_encode(['projekt' => $projekt, 'rubriken' => $rubriken, 'mein_recht' => $recht]);

    // ===== RUBRIKEN ==========================================

    } elseif ($action === 'rubrik_erstellen') {
        $pid   = (int)($input['projekt_id'] ?? 0);
        $recht = projektRecht($pid, $pdo);
        if (!hatRecht($recht, 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $s = $pdo->prepare("INSERT INTO `" . TBL_RUBRIKEN . "` (projekt_id,name,beschreibung,erstellt_von) VALUES (?,?,?,?)");
        $s->execute([$pid, $input['name'], $input['beschreibung']??'', $ich['id']]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);

    } elseif ($action === 'rubrik_aktualisieren') {
        $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_RUBRIKEN . "` r WHERE r.id=?"); $row->execute([$input['id']]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("UPDATE `" . TBL_RUBRIKEN . "` SET name=?,beschreibung=? WHERE id=?")
            ->execute([$input['name'], $input['beschreibung']??'', $input['id']]);
        echo json_encode(['ok' => true]);

    } elseif ($action === 'rubrik_loeschen') {
        $row = $pdo->prepare("SELECT projekt_id FROM `" . TBL_RUBRIKEN . "` WHERE id=?"); $row->execute([$input['id']]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'verwalten')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("DELETE FROM `" . TBL_RUBRIKEN . "` WHERE id=?")->execute([$input['id']]);
        echo json_encode(['ok' => true]);

    // ===== EINTRÄGE ==========================================

    } elseif ($action === 'eintrag_erstellen') {
        $row = $pdo->prepare("SELECT projekt_id FROM `" . TBL_RUBRIKEN . "` WHERE id=?"); $row->execute([$input['rubrik_id']]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $s = $pdo->prepare("INSERT INTO `" . TBL_EINTRAEGE . "` (rubrik_id,titel,beschreibung,phase,phase_datum,farbe,erstellt_von) VALUES (?,?,?,?,?,?,?)");
        $s->execute([$input['rubrik_id'],$input['titel'],$input['beschreibung']??'',$input['phase']??'idee',$input['phase_datum']?:null,$input['farbe']??'#4f8ef7',$ich['id']]);
        $eid = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO `" . TBL_SCHRITTE . "` (eintrag_id,phase,titel,datum,erstellt_von) VALUES (?,?,?,?,?)")
            ->execute([$eid,$input['phase']??'idee','Startpunkt: '.$input['titel'],$input['phase_datum']?:date('Y-m-d'),$ich['id']]);
        echo json_encode(['id' => $eid, 'ok' => true]);

    } elseif ($action === 'eintrag_aktualisieren') {
        $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_EINTRAEGE . "` e JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE e.id=?"); $row->execute([$input['id']]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("UPDATE `" . TBL_EINTRAEGE . "` SET titel=?,beschreibung=?,phase=?,phase_datum=?,farbe=? WHERE id=?")
            ->execute([$input['titel'],$input['beschreibung']??'',$input['phase'],$input['phase_datum']?:null,$input['farbe']??'#4f8ef7',$input['id']]);
        echo json_encode(['ok' => true]);

    } elseif ($action === 'eintrag_loeschen') {
        $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_EINTRAEGE . "` e JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE e.id=?"); $row->execute([$input['id']]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'verwalten')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("DELETE FROM `" . TBL_EINTRAEGE . "` WHERE id=?")->execute([$input['id']]);
        echo json_encode(['ok' => true]);

    // ===== SCHRITTE ==========================================

    } elseif ($action === 'schritt_erstellen') {
        $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_EINTRAEGE . "` e JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE e.id=?"); $row->execute([$input['eintrag_id']]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $s = $pdo->prepare("INSERT INTO `" . TBL_SCHRITTE . "` (eintrag_id,phase,titel,beschreibung,datum,erstellt_von) VALUES (?,?,?,?,?,?)");
        $s->execute([$input['eintrag_id'],$input['phase'],$input['titel'],$input['beschreibung']??'',$input['datum']?:null,$ich['id']]);
        $pdo->prepare("UPDATE `" . TBL_EINTRAEGE . "` SET phase=?,phase_datum=? WHERE id=?")
            ->execute([$input['phase'],$input['datum']?:date('Y-m-d'),$input['eintrag_id']]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);

    } elseif ($action === 'schritt_loeschen') {
        $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_SCHRITTE . "` ts JOIN `" . TBL_EINTRAEGE . "` e ON e.id=ts.eintrag_id JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE ts.id=?"); $row->execute([$input['id']]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'verwalten')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("DELETE FROM `" . TBL_SCHRITTE . "` WHERE id=?")->execute([$input['id']]);
        echo json_encode(['ok' => true]);

    // ===== ANHÄNGE ===========================================

    } elseif ($action === 'anhang_laden') {
        // Alle Anhänge für einen Eintrag oder Schritt laden
        $typ = in_array($input['typ'] ?? '', ['eintrag','schritt']) ? $input['typ'] : 'eintrag';
        $rid = (int)($input['referenz_id'] ?? 0);
        $s = $pdo->prepare("
            SELECT a.*, b.name AS erstellt_von_name
            FROM `" . TBL_ANHAENGE . "` a
            LEFT JOIN `" . TBL_BENUTZER . "` b ON b.id = a.erstellt_von
            WHERE a.typ=? AND a.referenz_id=?
            ORDER BY a.erstellt_am
        ");
        $s->execute([$typ, $rid]);
        echo json_encode($s->fetchAll());

    } elseif ($action === 'anhang_erstellen') {
        // Recht prüfen über Projekt-Zugehörigkeit
        $typ = in_array($input['typ'] ?? '', ['eintrag','schritt']) ? $input['typ'] : 'eintrag';
        $rid = (int)($input['referenz_id'] ?? 0);

        if ($typ === 'eintrag') {
            $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_EINTRAEGE . "` e JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE e.id=?");
        } else {
            $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_SCHRITTE . "` ts JOIN `" . TBL_EINTRAEGE . "` e ON e.id=ts.eintrag_id JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE ts.id=?");
        }
        $row->execute([$rid]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }

        $titel   = trim($input['titel']   ?? '');
        $inhalt  = trim($input['inhalt']  ?? '');
        $sprache = trim($input['sprache'] ?? 'plaintext');
        if (!$titel || !$inhalt) { echo json_encode(['error' => 'Titel und Inhalt erforderlich']); exit; }

        $s = $pdo->prepare("INSERT INTO `" . TBL_ANHAENGE . "` (typ, referenz_id, titel, sprache, inhalt, erstellt_von) VALUES (?,?,?,?,?,?)");
        $s->execute([$typ, $rid, $titel, $sprache, $inhalt, $ich['id']]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);

    } elseif ($action === 'anhang_loeschen') {
        $aid = (int)$input['id'];
        // Projekt ermitteln für Rechte-Check
        $row = $pdo->prepare("
            SELECT r.projekt_id FROM `" . TBL_ANHAENGE . "` a
            LEFT JOIN `" . TBL_EINTRAEGE . "` e ON (a.typ='eintrag' AND e.id=a.referenz_id)
            LEFT JOIN `" . TBL_SCHRITTE . "` ts ON (a.typ='schritt' AND ts.id=a.referenz_id)
            LEFT JOIN `" . TBL_EINTRAEGE . "` e2 ON (a.typ='schritt' AND e2.id=ts.eintrag_id)
            LEFT JOIN `" . TBL_RUBRIKEN . "` r ON (r.id=COALESCE(e.rubrik_id, e2.rubrik_id))
            WHERE a.id=?
        ");
        $row->execute([$aid]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'verwalten')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("DELETE FROM `" . TBL_ANHAENGE . "` WHERE id=?")->execute([$aid]);
        echo json_encode(['ok' => true]);


    // ===== KOMMENTARE =========================================

    } elseif ($action === 'kommentare_laden') {
        $typ = in_array($input['typ'] ?? '', ['eintrag','schritt']) ? $input['typ'] : 'eintrag';
        $rid = (int)($input['referenz_id'] ?? 0);
        $bid = $ich['id'];
        $s = $pdo->prepare("
            SELECT k.*,
                   b.name AS autor_name,
                   (SELECT COUNT(*) FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND typ='👍') AS r_gut,
                   (SELECT COUNT(*) FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND typ='👎') AS r_nein,
                   (SELECT COUNT(*) FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND typ='❤️') AS r_herz,
                   (SELECT COUNT(*) FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND typ='🤔') AS r_denk,
                   (SELECT typ FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND benutzer_id=?) AS meine_reaktion
            FROM `" . TBL_KOMMENTARE . "` k
            LEFT JOIN `" . TBL_BENUTZER . "` b ON b.id = k.erstellt_von
            WHERE k.typ=? AND k.referenz_id=?
            ORDER BY k.erstellt_am ASC
        ");
        $s->execute([$bid, $typ, $rid]);
        echo json_encode($s->fetchAll());

    } elseif ($action === 'kommentar_erstellen') {
        $typ    = in_array($input['typ'] ?? '', ['eintrag','schritt']) ? $input['typ'] : 'eintrag';
        $rid    = (int)($input['referenz_id'] ?? 0);
        $inhalt = trim($input['inhalt'] ?? '');
        if (!$inhalt) { echo json_encode(['error' => 'Kein Inhalt']); exit; }
        if ($typ === 'eintrag') {
            $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_EINTRAEGE . "` e JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE e.id=?");
        } else {
            $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_SCHRITTE . "` ts JOIN `" . TBL_EINTRAEGE . "` e ON e.id=ts.eintrag_id JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE ts.id=?");
        }
        $row->execute([$rid]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $s = $pdo->prepare("INSERT INTO `" . TBL_KOMMENTARE . "` (typ, referenz_id, inhalt, erstellt_von) VALUES (?,?,?,?)");
        $s->execute([$typ, $rid, $inhalt, $ich['id']]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);

    } elseif ($action === 'kommentar_entscheidung') {
        $kid = (int)$input['id'];
        $row = $pdo->prepare("
            SELECT r.projekt_id FROM `" . TBL_KOMMENTARE . "` k
            LEFT JOIN `" . TBL_EINTRAEGE . "` e  ON (k.typ='eintrag' AND e.id=k.referenz_id)
            LEFT JOIN `" . TBL_SCHRITTE . "`  ts ON (k.typ='schritt' AND ts.id=k.referenz_id)
            LEFT JOIN `" . TBL_EINTRAEGE . "` e2 ON (k.typ='schritt' AND e2.id=ts.eintrag_id)
            LEFT JOIN `" . TBL_RUBRIKEN . "`  r  ON r.id=COALESCE(e.rubrik_id, e2.rubrik_id)
            WHERE k.id=?
        ");
        $row->execute([$kid]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'verwalten')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("UPDATE `" . TBL_KOMMENTARE . "` SET ist_entscheidung = 1 - ist_entscheidung WHERE id=?")->execute([$kid]);
        echo json_encode(['ok' => true]);

    } elseif ($action === 'reaktion_setzen') {
        $kid = (int)$input['kommentar_id'];
        $typ = in_array($input['typ'] ?? '', ['👍','👎','❤️','🤔']) ? $input['typ'] : null;
        if (!$typ) { echo json_encode(['error' => 'Ungültige Reaktion']); exit; }
        $existing = $pdo->prepare("SELECT typ FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=? AND benutzer_id=?");
        $existing->execute([$kid, $ich['id']]); $alte = $existing->fetch();
        if ($alte && $alte['typ'] === $typ) {
            $pdo->prepare("DELETE FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=? AND benutzer_id=?")->execute([$kid, $ich['id']]);
            echo json_encode(['ok' => true, 'aktion' => 'entfernt']);
        } else {
            $pdo->prepare("INSERT INTO `" . TBL_REAKTIONEN . "` (kommentar_id, typ, benutzer_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE typ=?, erstellt_am=NOW()")
                ->execute([$kid, $typ, $ich['id'], $typ]);
            echo json_encode(['ok' => true, 'aktion' => 'gesetzt']);
        }



    // ===== BOARD-THEMEN ======================================

    } elseif ($action === 'board_themen_liste') {
        $pid = (int)($_GET['id'] ?? 0);
        if (!projektRecht($pid, $pdo)) { http_response_code(403); echo json_encode(['error'=>'Kein Zugang']); exit; }
        $s = $pdo->prepare("
            SELECT t.*,
                   b.name AS erstellt_von_name,
                   (SELECT COUNT(*) FROM `" . TBL_KOMMENTARE . "` k WHERE k.typ='board' AND k.referenz_id=t.id) AS antwort_count,
                   (SELECT COUNT(*) FROM `" . TBL_KOMMENTARE . "` k WHERE k.typ='board' AND k.referenz_id=t.id AND k.ist_entscheidung=1) AS entscheidung_count,
                   r.name AS rubrik_name,
                   CASE t.ref_typ
                     WHEN 'eintrag' THEN (SELECT titel FROM `" . TBL_EINTRAEGE . "` WHERE id=t.ref_id)
                     WHEN 'schritt' THEN (SELECT titel FROM `" . TBL_SCHRITTE . "` WHERE id=t.ref_id)
                     ELSE NULL
                   END AS ref_titel
            FROM `" . TBL_BOARD_THEMEN . "` t
            LEFT JOIN `" . TBL_BENUTZER . "` b ON b.id = t.erstellt_von
            LEFT JOIN `" . TBL_RUBRIKEN . "` r ON r.id = t.rubrik_id
            WHERE t.projekt_id=?
            ORDER BY t.erstellt_am DESC
        ");
        $s->execute([$pid]);
        echo json_encode($s->fetchAll());

    } elseif ($action === 'board_thema_erstellen') {
        $pid   = (int)($input['projekt_id'] ?? 0);
        $titel = trim($input['titel'] ?? '');
        if (!$titel) { echo json_encode(['error' => 'Kein Titel']); exit; }
        if (!hatRecht(projektRecht($pid, $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $refTyp = in_array($input['ref_typ'] ?? '', ['eintrag','schritt']) ? $input['ref_typ'] : null;
        $refId  = $refTyp ? (int)($input['ref_id'] ?? 0) : null;
        $s = $pdo->prepare("INSERT INTO `" . TBL_BOARD_THEMEN . "` (projekt_id, titel, ref_typ, ref_id, erstellt_von) VALUES (?,?,?,?,?)");
        $s->execute([$pid, $titel, $refTyp, $refId, $ich['id']]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);

    } elseif ($action === 'board_thema_detail') {
        $tid = (int)($_GET['id'] ?? 0);
        $t   = $pdo->prepare("SELECT t.*, b.name AS erstellt_von_name,
            r.name AS rubrik_name,
            CASE t.ref_typ
              WHEN 'eintrag' THEN (SELECT titel FROM `" . TBL_EINTRAEGE . "` WHERE id=t.ref_id)
              WHEN 'schritt' THEN (SELECT titel FROM `" . TBL_SCHRITTE . "` WHERE id=t.ref_id)
              ELSE NULL
            END AS ref_titel
            FROM `" . TBL_BOARD_THEMEN . "` t
            LEFT JOIN `" . TBL_BENUTZER . "` b ON b.id=t.erstellt_von
            LEFT JOIN `" . TBL_RUBRIKEN . "` r ON r.id=t.rubrik_id
            WHERE t.id=?");
        $t->execute([$tid]); $thema = $t->fetch();
        if (!$thema) { http_response_code(404); echo json_encode(['error'=>'Nicht gefunden']); exit; }
        if (!projektRecht((int)$thema['projekt_id'], $pdo)) { http_response_code(403); echo json_encode(['error'=>'Kein Zugang']); exit; }

        // Alle Kommentare mit Reaktionen laden
        $bid = $ich['id'];
        $k = $pdo->prepare("
            SELECT k.*, b.name AS autor_name,
                   (SELECT COUNT(*) FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND typ='👍') AS r_gut,
                   (SELECT COUNT(*) FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND typ='👎') AS r_nein,
                   (SELECT COUNT(*) FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND typ='❤️') AS r_herz,
                   (SELECT COUNT(*) FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND typ='🤔') AS r_denk,
                   (SELECT typ FROM `" . TBL_REAKTIONEN . "` WHERE kommentar_id=k.id AND benutzer_id=?) AS meine_reaktion
            FROM `" . TBL_KOMMENTARE . "` k
            LEFT JOIN `" . TBL_BENUTZER . "` b ON b.id=k.erstellt_von
            WHERE k.typ='board' AND k.referenz_id=?
            ORDER BY k.erstellt_am ASC
        ");
        $k->execute([$bid, $tid]);
        $kommentare = $k->fetchAll();
        echo json_encode(['thema' => $thema, 'kommentare' => $kommentare]);

    } elseif ($action === 'board_kommentar_erstellen') {
        $tid    = (int)($input['thema_id'] ?? 0);
        $inhalt = trim($input['inhalt'] ?? '');
        $eltId  = isset($input['eltern_id']) ? (int)$input['eltern_id'] : null;
        if (!$inhalt) { echo json_encode(['error' => 'Kein Inhalt']); exit; }
        $thema  = $pdo->prepare("SELECT projekt_id FROM `" . TBL_BOARD_THEMEN . "` WHERE id=?"); $thema->execute([$tid]); $t = $thema->fetch();
        if (!hatRecht(projektRecht((int)$t['projekt_id'], $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $s = $pdo->prepare("INSERT INTO `" . TBL_KOMMENTARE . "` (typ, referenz_id, eltern_id, inhalt, erstellt_von) VALUES ('board',?,?,?,?)");
        $s->execute([$tid, $eltId, $inhalt, $ich['id']]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);

    } elseif ($action === 'board_rubrik_erstellen') {
        // Aus Entscheidung eine Rubrik erstellen und ans Thema koppeln
        $tid   = (int)($input['thema_id'] ?? 0);
        $name  = trim($input['name'] ?? '');
        $thema = $pdo->prepare("SELECT * FROM `" . TBL_BOARD_THEMEN . "` WHERE id=?"); $thema->execute([$tid]); $t = $thema->fetch();
        if (!$t) { echo json_encode(['error' => 'Thema nicht gefunden']); exit; }
        if (!hatRecht(projektRecht((int)$t['projekt_id'], $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        // Rubrik anlegen
        $r = $pdo->prepare("INSERT INTO `" . TBL_RUBRIKEN . "` (projekt_id, name, erstellt_von) VALUES (?,?,?)");
        $r->execute([$t['projekt_id'], $name ?: $t['titel'], $ich['id']]);
        $rid = $pdo->lastInsertId();
        // Thema mit Rubrik koppeln
        $pdo->prepare("UPDATE `" . TBL_BOARD_THEMEN . "` SET rubrik_id=? WHERE id=?")->execute([$rid, $tid]);
        echo json_encode(['id' => $rid, 'ok' => true]);

    } elseif ($action === 'board_thema_von_ref') {
        // Schritt/Eintrag → Board-Thema erstellen
        $refTyp = in_array($input['ref_typ'] ?? '', ['eintrag','schritt']) ? $input['ref_typ'] : null;
        $refId  = (int)($input['ref_id'] ?? 0);
        $pid    = (int)($input['projekt_id'] ?? 0);
        $titel  = trim($input['titel'] ?? '');
        if (!$refTyp || !$refId || !$titel) { echo json_encode(['error' => 'Fehlende Parameter']); exit; }
        if (!hatRecht(projektRecht($pid, $pdo), 'schreiben')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        // Prüfen ob schon ein Thema verknüpft
        $ex = $pdo->prepare("SELECT id FROM `" . TBL_BOARD_THEMEN . "` WHERE ref_typ=? AND ref_id=? AND projekt_id=?");
        $ex->execute([$refTyp, $refId, $pid]); $existing = $ex->fetch();
        if ($existing) { echo json_encode(['id' => $existing['id'], 'ok' => true, 'existed' => true]); exit; }
        $s = $pdo->prepare("INSERT INTO `" . TBL_BOARD_THEMEN . "` (projekt_id, titel, ref_typ, ref_id, erstellt_von) VALUES (?,?,?,?,?)");
        $s->execute([$pid, $titel, $refTyp, $refId, $ich['id']]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true, 'existed' => false]);


    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unbekannte Aktion: '.$action]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}