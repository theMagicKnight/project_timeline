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
        $r = $pdo->prepare("SELECT * FROM `" . TBL_RUBRIKEN . "` WHERE projekt_id=? ORDER BY sortierung,erstellt_am");
        $r->execute([$pid]); $rubriken = $r->fetchAll();
        foreach ($rubriken as &$rub) {
            $e = $pdo->prepare("SELECT * FROM `" . TBL_EINTRAEGE . "` WHERE rubrik_id=? ORDER BY sortierung,erstellt_am");
            $e->execute([$rub['id']]); $eintraege = $e->fetchAll();
            foreach ($eintraege as &$ent) {
                $s = $pdo->prepare("SELECT * FROM `" . TBL_SCHRITTE . "` WHERE eintrag_id=? ORDER BY datum,erstellt_am");
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
        $s = $pdo->prepare("INSERT INTO `" . TBL_RUBRIKEN . "` (projekt_id,name,beschreibung) VALUES (?,?,?)");
        $s->execute([$pid, $input['name'], $input['beschreibung']??'']);
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
        $s = $pdo->prepare("INSERT INTO `" . TBL_EINTRAEGE . "` (rubrik_id,titel,beschreibung,phase,phase_datum,farbe) VALUES (?,?,?,?,?,?)");
        $s->execute([$input['rubrik_id'],$input['titel'],$input['beschreibung']??'',$input['phase']??'idee',$input['phase_datum']?:null,$input['farbe']??'#4f8ef7']);
        $eid = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO `" . TBL_SCHRITTE . "` (eintrag_id,phase,titel,datum) VALUES (?,?,?,?)")
            ->execute([$eid,$input['phase']??'idee','Startpunkt: '.$input['titel'],$input['phase_datum']?:date('Y-m-d')]);
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
        $s = $pdo->prepare("INSERT INTO `" . TBL_SCHRITTE . "` (eintrag_id,phase,titel,beschreibung,datum) VALUES (?,?,?,?,?)");
        $s->execute([$input['eintrag_id'],$input['phase'],$input['titel'],$input['beschreibung']??'',$input['datum']?:null]);
        $pdo->prepare("UPDATE `" . TBL_EINTRAEGE . "` SET phase=?,phase_datum=? WHERE id=?")
            ->execute([$input['phase'],$input['datum']?:date('Y-m-d'),$input['eintrag_id']]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);

    } elseif ($action === 'schritt_loeschen') {
        $row = $pdo->prepare("SELECT r.projekt_id FROM `" . TBL_SCHRITTE . "` ts JOIN `" . TBL_EINTRAEGE . "` e ON e.id=ts.eintrag_id JOIN `" . TBL_RUBRIKEN . "` r ON r.id=e.rubrik_id WHERE ts.id=?"); $row->execute([$input['id']]); $r = $row->fetch();
        if (!hatRecht(projektRecht((int)$r['projekt_id'], $pdo), 'verwalten')) { http_response_code(403); echo json_encode(['error'=>'Keine Rechte']); exit; }
        $pdo->prepare("DELETE FROM `" . TBL_SCHRITTE . "` WHERE id=?")->execute([$input['id']]);
        echo json_encode(['ok' => true]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unbekannte Aktion: '.$action]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
