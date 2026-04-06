<?php
// ============================================================
//  auth.php — Session & Rechte-Helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Aktuell eingeloggter Benutzer ----
function aktuellerBenutzer(): ?array {
    return $_SESSION['benutzer'] ?? null;
}

// ---- Ist überhaupt jemand eingeloggt? ----
function istEingeloggt(): bool {
    return isset($_SESSION['benutzer']);
}

// ---- Ist der Benutzer Admin? ----
function istAdmin(): bool {
    return ($_SESSION['benutzer']['rolle'] ?? '') === 'admin';
}

// ---- Recht des Benutzers auf ein Projekt prüfen ----
// Gibt zurück: 'admin' | 'verwalten' | 'schreiben' | 'lesen' | null
function projektRecht(int $projektId, \PDO $pdo): ?string {
    $b = aktuellerBenutzer();
    if (!$b) return null;
    if ($b['rolle'] === 'admin') return 'admin';

    $s = $pdo->prepare("SELECT recht FROM `" . TBL_PROJEKT_BENUTZER . "` WHERE projekt_id=? AND benutzer_id=?");
    $s->execute([$projektId, $b['id']]);
    $row = $s->fetch();
    return $row ? $row['recht'] : null;
}

// ---- Recht >= Mindest-Recht? ----
function hatRecht(?string $istRecht, string $mindestRecht): bool {
    $stufen = ['lesen' => 1, 'schreiben' => 2, 'verwalten' => 3, 'admin' => 4];
    $ist    = $stufen[$istRecht]    ?? 0;
    $mind   = $stufen[$mindestRecht] ?? 99;
    return $ist >= $mind;
}

// ---- Zugang erzwingen (für index.php) ----
function zugangErfordern(string $redirect = 'login.php'): void {
    if (!istEingeloggt()) {
        header("Location: $redirect");
        exit;
    }
}

// ---- JSON-Fehler bei fehlenden Rechten (für api.php) ----
function apiZugang(string $mindestRecht = 'lesen', int $projektId = 0, \PDO $pdo = null): void {
    if (!istEingeloggt()) {
        http_response_code(401);
        echo json_encode(['error' => 'Nicht eingeloggt']);
        exit;
    }
    if ($mindestRecht === 'admin' && !istAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Keine Admin-Rechte']);
        exit;
    }
    if ($projektId && $pdo && $mindestRecht !== 'admin') {
        $recht = projektRecht($projektId, $pdo);
        if (!hatRecht($recht, $mindestRecht)) {
            http_response_code(403);
            echo json_encode(['error' => 'Keine ausreichenden Rechte']);
            exit;
        }
    }
}

// ---- Ersten Admin automatisch anlegen (falls noch kein Benutzer existiert) ----
function ersterAdminAnlegen(\PDO $pdo): void {
    $count = $pdo->query("SELECT COUNT(*) FROM `" . TBL_BENUTZER . "`")->fetchColumn();
    if ($count == 0 && defined('ADMIN_EMAIL') && defined('ADMIN_PASS')) {
        $pdo->prepare("INSERT INTO `" . TBL_BENUTZER . "` (name, email, passwort, rolle) VALUES (?,?,?,?)")
            ->execute(['Admin', ADMIN_EMAIL, password_hash(ADMIN_PASS, PASSWORD_DEFAULT), 'admin']);
    }
}