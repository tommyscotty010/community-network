<?php
require_once 'auth.php';
requireLogin();

$pdo    = getDB();
$userId = (int)$_SESSION['user_id'];
$error  = '';
$success= '';

// Load current data
$user = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.is_admin, u.created_at,
           p.bio, p.avatar
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = ?
");
$user->execute([$userId]);
$user = $user->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── UPDATE BIO ────────────────────────────────────────────────────────────
    if ($action === 'profile') {
        $bio = substr(trim($_POST['bio'] ?? ''), 0, 300);

        // Handle avatar upload
        $avatarPath = $user['avatar'];
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $file    = $_FILES['avatar'];
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if (!in_array($file['type'], $allowed)) {
                $error = 'Formato immagine non valido (jpeg, png, gif, webp).';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Avatar troppo grande (max 2MB).';
            } else {
                $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
                $name = 'av_' . $userId . '_' . time() . '.' . $ext;
                $dest = __DIR__ . '/uploads/avatars/' . $name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Delete old avatar
                    if ($user['avatar'] && file_exists(__DIR__ . '/uploads/avatars/' . $user['avatar'])) {
                        unlink(__DIR__ . '/uploads/avatars/' . $user['avatar']);
                    }
                    $avatarPath = $name;
                }
            }
        }

        if (!$error) {
            $pdo->prepare("
                INSERT INTO user_profiles (user_id, bio, avatar)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE bio = VALUES(bio), avatar = VALUES(avatar)
            ")->execute([$userId, $bio ?: null, $avatarPath]);
            $user['bio']    = $bio;
            $user['avatar'] = $avatarPath;
            $success = 'Profilo aggiornato.';
        }
    }

    // ── CHANGE PASSWORD ───────────────────────────────────────────────────────
    if ($action === 'password') {
        $current = $_POST['current'] ?? '';
        $new     = $_POST['new']     ?? '';
        $confirm = $_POST['confirm'] ?? '';

        $row = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $row->execute([$userId]);
        $row = $row->fetch();

        if (!password_verify($current, $row['password'])) {
            $error = 'Password attuale errata.';
        } elseif (strlen($new) < 6) {
            $error = 'Nuova password: minimo 6 caratteri.';
        } elseif ($new !== $confirm) {
            $error = 'Le password non coincidono.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
            $success = 'Password cambiata con successo.';
        }
    }
}

// Load stats
$msgCount = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
$msgCount->execute([$userId]); $msgCount = (int)$msgCount->fetchColumn();
$pinCount = $pdo->prepare("SELECT COUNT(*) FROM map_pins WHERE user_id = ?");
$pinCount->execute([$userId]); $pinCount = (int)$pinCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profilo – Walker Network</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: #0a0a0f; color: #d0d0e0; font-size: 14px; }
#wrap { max-width: 720px; margin: 0 auto; padding: 28px 16px; }
.back { color: #63b3ed; text-decoration: none; font-size: .85rem; display: inline-block; margin-bottom: 20px; }
.back:hover { color: #90cdf4; }
.profile-header { display: flex; align-items: center; gap: 20px; margin-bottom: 28px; }
.avatar-wrap { position: relative; }
.avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
          background: #222; border: 2px solid #333; display: flex; align-items: center;
          justify-content: center; font-size: 2rem; color: #555; overflow: hidden; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.profile-info h1 { font-size: 1.4rem; }
.profile-info .sub { color: #666; font-size: .85rem; }
.stats { display: flex; gap: 20px; margin-top: 8px; }
.stat { font-size: .82rem; color: #888; }
.stat strong { color: #aaa; }
.card { background: #111118; border: 1px solid #1e1e2e; border-radius: 8px;
        padding: 22px; margin-bottom: 20px; }
.card h2 { font-size: .95rem; margin-bottom: 16px; color: #bbb; }
label { display: block; font-size: .82rem; color: #888; margin-bottom: 4px; }
input[type=text], input[type=email], input[type=password], textarea {
    width: 100%; padding: 8px 11px; background: #0a0a0f; border: 1px solid #2a2a2a;
    border-radius: 5px; color: #d0d0e0; font-size: .9rem; font-family: inherit; }
input:focus, textarea:focus { outline: none; border-color: #444; }
textarea { resize: vertical; height: 80px; }
.field { margin-bottom: 14px; }
.hint { font-size: .76rem; color: #555; margin-top: 3px; }
.btn { padding: 8px 20px; background: #2b6cb0; border: none; border-radius: 5px;
       color: #fff; font-size: .88rem; cursor: pointer; }
.btn:hover { background: #2c5282; }
.btn-green { background: #276749; }
.btn-green:hover { background: #22543d; }
.error   { background: #4a1a1a; border: 1px solid #7f2020; border-radius: 5px;
           padding: 9px 12px; font-size: .85rem; color: #fc8181; margin-bottom: 16px; }
.success { background: #1a3a2a; border: 1px solid #276749; border-radius: 5px;
           padding: 9px 12px; font-size: .85rem; color: #68d391; margin-bottom: 16px; }
.avatar-preview { width: 60px; height: 60px; border-radius: 50%; object-fit: cover;
                  border: 1px solid #333; display: none; margin-top: 8px; }
</style>
</head>
<body>
<div id="wrap">
  <a class="back" href="dashboard.php">← Dashboard</a>

  <div class="profile-header">
    <div class="avatar-wrap">
      <div class="avatar">
        <?php if ($user['avatar']): ?>
          <img src="uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="">
        <?php else: ?>
          <?= mb_strtoupper(mb_substr($user['username'],0,1)) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="profile-info">
      <h1><?= htmlspecialchars($user['username']) ?></h1>
      <div class="sub"><?= htmlspecialchars($user['email']) ?>
        <?= $user['is_admin'] ? ' · <span style="color:#b794f4">Admin</span>' : '' ?>
      </div>
      <div class="stats">
        <div class="stat">Messaggi: <strong><?= $msgCount ?></strong></div>
        <div class="stat">Pin sulla mappa: <strong><?= $pinCount ?></strong></div>
        <div class="stat">Iscritto: <strong><?= date('d/m/Y', strtotime($user['created_at'])) ?></strong></div>
      </div>
    </div>
  </div>

  <?php if ($error):   ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- PROFILE FORM -->
  <div class="card">
    <h2>Modifica profilo</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="profile">
      <div class="field">
        <label>Avatar</label>
        <input type="file" name="avatar" accept="image/*"
               onchange="previewAvatar(this)" style="color:#888">
        <img id="av-preview" class="avatar-preview">
        <p class="hint">JPG/PNG/GIF/WEBP – max 2MB</p>
      </div>
      <div class="field">
        <label>Bio</label>
        <textarea name="bio" maxlength="300"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        <p class="hint">Max 300 caratteri</p>
      </div>
      <button class="btn btn-green" type="submit">Salva profilo</button>
    </form>
  </div>

  <!-- PASSWORD FORM -->
  <div class="card">
    <h2>Cambia password</h2>
    <form method="POST">
      <input type="hidden" name="action" value="password">
      <div class="field">
        <label>Password attuale</label>
        <input type="password" name="current" required>
      </div>
      <div class="field">
        <label>Nuova password</label>
        <input type="password" name="new" required minlength="6">
      </div>
      <div class="field">
        <label>Conferma nuova password</label>
        <input type="password" name="confirm" required>
      </div>
      <button class="btn" type="submit">Cambia password</button>
    </form>
  </div>
</div>
<script>
function previewAvatar(input) {
  const preview = document.getElementById('av-preview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>
