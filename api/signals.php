<?php
require_once __DIR__ . '/../auth.php';
$user = apiAuth();
$pdo  = getDB();
header('Content-Type: application/json');

// Pulizia segnali vecchi (5 minuti di TTL)
$pdo->exec("DELETE FROM webrtc_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $after = (int)($_GET['after'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT s.id, s.from_user_id, s.type, s.payload, s.created_at,
               u.username AS from_username
        FROM webrtc_signals s
        JOIN users u ON u.id = s.from_user_id
        WHERE s.to_user_id = ?
          AND s.id > ?
          AND s.processed = 0
        ORDER BY s.id ASC
        LIMIT 100
    ");
    $stmt->execute([$user['id'], $after]);
    $signals = $stmt->fetchAll();

    // Marca come processati
    if ($signals) {
        $ids = array_column($signals, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE webrtc_signals SET processed = 1 WHERE id IN ($ph)")
            ->execute($ids);
    }

    echo json_encode($signals);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $toUser = (int)($data['to']      ?? 0);
    $type   = trim($data['type']     ?? '');
    $payload= json_encode($data['payload'] ?? []);

    $allowed = ['call-request','call-accepted','call-rejected','call-end','offer','answer','ice'];
    if (!$toUser || !in_array($type, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Segnale non valido']);
        exit;
    }

    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $chk->execute([$toUser]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Utente non trovato']);
        exit;
    }

    $pdo->prepare("
        INSERT INTO webrtc_signals (from_user_id, to_user_id, type, payload)
        VALUES (?, ?, ?, ?)
    ")->execute([$user['id'], $toUser, $type, $payload]);

    echo json_encode(['id' => (int)$pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
