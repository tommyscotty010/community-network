<?php
require_once 'auth.php';
requireAdmin();

$pdo = getDB();
$msg = '';

// Generate token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate') {
        $token = bin2hex(random_bytes(16)); // 32 hex chars
        // Format as xxxx-xxxx-xxxx-xxxx-xxxx-xxxx-xxxx-xxxx
        $token = implode('-', str_split($token, 4));
        $pdo->prepare("INSERT INTO invite_tokens (token, created_by) VALUES (?, ?)")
            ->execute([$token, $_SESSION['user_id']]);
        $msg = "Token generato: <code style='background:#111;padding:2px 6px;border-radius:3px'>{$token}</code>";
    } elseif ($_POST['action'] === 'delete_token' && !empty($_POST['token_id'])) {
        $pdo->prepare("DELETE FROM invite_tokens WHERE id = ? AND used_by IS NULL")
            ->execute([(int)$_POST['token_id']]);
    } elseif ($_POST['action'] === 'delete_user' && !empty($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== (int)$_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0")->execute([$uid]);
        }
    } elseif ($_POST['action'] === 'toggle_admin' && !empty($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== (int)$_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = ?")->execute([$uid]);
        }
    }
}

$tokens = $pdo->query("
    SELECT t.*, u.username AS used_by_name, c.username AS created_by_name
    FROM invite_tokens t
    JOIN users c ON c.id = t.created_by
    LEFT JOIN users u ON u.id = t.used_by
    ORDER BY t.created_at DESC
")->fetchAll();

$users = $pdo->query("
    SELECT id, username, email, is_admin, last_seen, created_at
    FROM users ORDER BY created_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin – Community Network</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: #0a0a0f; color: #d0d0e0; font-size: 14px; }
#wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
h1 { font-size: 1.3rem; margin-bottom: 4px; }
.sub { color: #666; font-size: .85rem; margin-bottom: 24px; }
.back { color: #63b3ed; text-decoration: none; font-size: .85rem; display: inline-block; margin-bottom: 20px; }
.back:hover { color: #90cdf4; }
h2 { font-size: 1rem; margin-bottom: 12px; color: #bbb; }
section { margin-bottom: 36px; }
.msg { background: #1a3a2a; border: 1px solid #276749; border-radius: 5px;
       padding: 10px 14px; margin-bottom: 16px; color: #68d391; font-size: .88rem; }
table { width: 100%; border-collapse: collapse; background: #111118; border-radius: 6px; overflow: hidden; }
th { background: #181818; padding: 9px 12px; text-align: left; font-size: .78rem;
     text-transform: uppercase; letter-spacing: .06em; color: #666; border-bottom: 1px solid #222; }
td { padding: 9px 12px; border-bottom: 1px solid #1a1a1a; font-size: .88rem; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
.badge { padding: 2px 7px; border-radius: 4px; font-size: .75rem; }
.badge.used   { background: #1a2e1a; color: #68d391; }
.badge.unused { background: #2a2a14; color: #f6e05e; }
.badge.admin  { background: #2b2360; color: #b794f4; }
code { font-family: monospace; font-size: .85rem; color: #a0aec0; word-break: break-all; }
.btn-sm { padding: 4px 10px; border: none; border-radius: 4px; font-size: .8rem;
          cursor: pointer; }
.btn-red   { background: #c53030; color: #fff; }
.btn-green { background: #276749; color: #fff; }
.btn-gen { padding: 9px 20px; background: #2b6cb0; border: none; border-radius: 6px;
           color: #fff; font-size: .9rem; cursor: pointer; }
.btn-gen:hover { background: #2c5282; }
</style>
</head>
<body>
<div id="wrap">
  <a class="back" href="dashboard.php">← Torna alla dashboard</a>
  <h1>Pannello Amministrazione</h1>
  <p class="sub">Community Network</p>

  <?php if ($msg): ?>
    <div class="msg"><?= $msg ?></div>
  <?php endif; ?>

  <!-- TOKEN GENERATION -->
  <section>
    <h2>Token di invito</h2>
    <form method="POST" style="margin-bottom:16px">
      <input type="hidden" name="action" value="generate">
      <button class="btn-gen" type="submit">+ Genera nuovo token</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>Token</th>
          <th>Stato</th>
          <th>Creato il</th>
          <th>Usato da</th>
          <th>Usato il</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$tokens): ?>
          <tr><td colspan="6" style="color:#555;text-align:center;padding:20px">Nessun token generato</td></tr>
        <?php endif; ?>
        <?php foreach ($tokens as $t): ?>
        <tr>
          <td><code><?= htmlspecialchars($t['token']) ?></code></td>
          <td>
            <?php if ($t['used_by']): ?>
              <span class="badge used">Usato</span>
            <?php else: ?>
              <span class="badge unused">Disponibile</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['created_at']))) ?></td>
          <td><?= $t['used_by_name'] ? htmlspecialchars($t['used_by_name']) : '—' ?></td>
          <td><?= $t['used_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($t['used_at']))) : '—' ?></td>
          <td>
            <?php if (!$t['used_by']): ?>
              <form method="POST" onsubmit="return confirm('Eliminare il token?')">
                <input type="hidden" name="action" value="delete_token">
                <input type="hidden" name="token_id" value="<?= $t['id'] ?>">
                <button class="btn-sm btn-red" type="submit">Elimina</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- USERS -->
  <section>
    <h2>Utenti registrati</h2>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Username</th>
          <th>Email</th>
          <th>Ruolo</th>
          <th>Ultimo accesso</th>
          <th>Registrato il</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td style="color:#555"><?= $u['id'] ?></td>
          <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
          <td style="color:#888"><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?php if ($u['is_admin']): ?>
              <span class="badge admin">Admin</span>
            <?php else: ?>
              <span style="color:#666">Utente</span>
            <?php endif; ?>
          </td>
          <td style="color:#888">
            <?= $u['last_seen'] ? date('d/m/Y H:i', strtotime($u['last_seen'])) : '—' ?>
          </td>
          <td style="color:#888"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td style="display:flex;gap:6px">
            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
              <form method="POST">
                <input type="hidden" name="action" value="toggle_admin">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn-sm btn-green" type="submit">
                  <?= $u['is_admin'] ? 'Togli admin' : 'Fai admin' ?>
                </button>
              </form>
              <form method="POST" onsubmit="return confirm('Eliminare utente <?= htmlspecialchars($u['username']) ?>?')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn-sm btn-red" type="submit">Elimina</button>
              </form>
            <?php else: ?>
              <span style="color:#555;font-size:.78rem">(sei tu)</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
</body>
</html>
