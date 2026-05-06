<?php
require_once __DIR__ . '/../auth.php';
$user   = apiAuth();
$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
header('Content-Type: application/json');

/* ── GET list ─────────────────────────────────────────────────────────── */
if ($method === 'GET' && $action === 'list') {
    $stmt = $pdo->prepare("
        SELECT g.id, g.name, g.description, g.created_at, g.created_by,
               u.username AS creator,
               (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) AS member_count,
               (SELECT COUNT(*) FROM group_members gm3
                WHERE gm3.group_id = g.id AND gm3.user_id = ?) AS is_member,
               (SELECT gm4.role FROM group_members gm4
                WHERE gm4.group_id = g.id AND gm4.user_id = ?) AS my_role
        FROM `groups` g
        JOIN users u ON u.id = g.created_by
        ORDER BY g.name ASC
    ");
    $stmt->execute([$user['id'], $user['id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

/* ── GET messages ────────────────────────────────────────────────────── */
if ($method === 'GET' && $action === 'messages') {
    $gid   = (int)($_GET['group_id'] ?? 0);
    $after = (int)($_GET['after']    ?? 0);
    if (!$gid) { http_response_code(400); echo json_encode(['error'=>'group_id mancante']); exit; }

    $chk = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
    $chk->execute([$gid, $user['id']]);
    if (!$chk->fetch()) { http_response_code(403); echo json_encode(['error'=>'Non sei membro']); exit; }

    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, m.content, m.created_at,
               u.username, pr.avatar AS sender_avatar,
               a.original AS attach_name, a.mime AS attach_mime,
               a.filename AS attach_file, a.size AS attach_size
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        LEFT JOIN user_profiles pr ON pr.user_id = m.sender_id
        LEFT JOIN attachments a ON a.id = m.attachment_id
        WHERE m.group_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
        LIMIT 100
    ");
    $stmt->execute([$gid, $after]);
    echo json_encode($stmt->fetchAll());
    exit;
}

/* ── GET members ─────────────────────────────────────────────────────── */
if ($method === 'GET' && $action === 'members') {
    $gid  = (int)($_GET['group_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, gm.role, gm.joined_at, pr.avatar,
               (u.last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)) AS online
        FROM group_members gm
        JOIN users u ON u.id = gm.user_id
        LEFT JOIN user_profiles pr ON pr.user_id = u.id
        WHERE gm.group_id = ?
        ORDER BY gm.role DESC, u.username ASC
    ");
    $stmt->execute([$gid]);
    echo json_encode($stmt->fetchAll());
    exit;
}

/* ── POST actions ────────────────────────────────────────────────────── */
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $act  = $data['action'] ?? '';

    if ($act === 'create') {
        $name = substr(trim($data['name'] ?? ''), 0, 80);
        $desc = substr(trim($data['description'] ?? ''), 0, 300);
        if (strlen($name) < 2) { http_response_code(400); echo json_encode(['error'=>'Nome troppo corto']); exit; }
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO `groups` (name,description,created_by) VALUES (?,?,?)")
            ->execute([$name, $desc ?: null, $user['id']]);
        $gid = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO group_members (group_id,user_id,role) VALUES (?,?,'admin')")
            ->execute([$gid, $user['id']]);
        $pdo->commit();
        echo json_encode(['id'=>$gid,'name'=>$name]);
        exit;
    }

    if ($act === 'join') {
        $gid = (int)($data['group_id'] ?? 0);
        if (!$gid) { http_response_code(400); echo json_encode(['error'=>'group_id mancante']); exit; }
        // Verifica gruppo esiste
        $chk = $pdo->prepare("SELECT id FROM `groups` WHERE id=?");
        $chk->execute([$gid]);
        if (!$chk->fetch()) { http_response_code(404); echo json_encode(['error'=>'Gruppo non trovato']); exit; }
        $pdo->prepare("INSERT IGNORE INTO group_members (group_id,user_id,role) VALUES (?,?,'member')")
            ->execute([$gid, $user['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($act === 'leave') {
        $gid = (int)($data['group_id'] ?? 0);
        // Il creatore non può uscire (deve eliminare)
        $chk = $pdo->prepare("SELECT created_by FROM `groups` WHERE id=?");
        $chk->execute([$gid]); $g = $chk->fetch();
        if ($g && (int)$g['created_by'] === $user['id']) {
            http_response_code(400);
            echo json_encode(['error'=>'Il creatore non può uscire, elimina il gruppo']);
            exit;
        }
        $pdo->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?")
            ->execute([$gid, $user['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($act === 'send') {
        $gid          = (int)($data['group_id'] ?? 0);
        $content      = isset($data['content']) ? substr(trim($data['content']), 0, 2000) : null;
        $attachmentId = isset($data['attachment_id']) ? (int)$data['attachment_id'] : null;
        if (!$gid) { http_response_code(400); echo json_encode(['error'=>'group_id mancante']); exit; }
        if (!$content && !$attachmentId) { http_response_code(400); echo json_encode(['error'=>'Messaggio vuoto']); exit; }
        $chk = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
        $chk->execute([$gid, $user['id']]);
        if (!$chk->fetch()) { http_response_code(403); echo json_encode(['error'=>'Non sei membro']); exit; }
        $pdo->prepare("INSERT INTO messages (sender_id,group_id,content,attachment_id) VALUES (?,?,?,?)")
            ->execute([$user['id'], $gid, $content ?: null, $attachmentId]);
        echo json_encode(['id'=>(int)$pdo->lastInsertId()]);
        exit;
    }

    if ($act === 'delete') {
        $gid = (int)($data['group_id'] ?? 0);
        $chk = $pdo->prepare("SELECT created_by FROM `groups` WHERE id=?");
        $chk->execute([$gid]); $g = $chk->fetch();
        if (!$g) { http_response_code(404); echo json_encode(['error'=>'Non trovato']); exit; }
        if ((int)$g['created_by'] !== $user['id'] && !$user['is_admin']) {
            http_response_code(403); echo json_encode(['error'=>'Vietato']); exit;
        }
        $pdo->prepare("DELETE FROM `groups` WHERE id=?")->execute([$gid]);
        echo json_encode(['ok'=>true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error'=>'Azione sconosciuta']);
