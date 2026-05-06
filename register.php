<?php
require_once 'config.php';
if(isset($_SESSION['user_id'])){header('Location: dashboard.php');exit;}
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $token=trim($_POST['token']??'');$uname=trim($_POST['username']??'');
  $email=trim($_POST['email']??'');$pw=$_POST['password']??'';$pw2=$_POST['confirm']??'';
  do{
    if(!$token||!$uname||!$email||!$pw||!$pw2){$error='Compila tutti i campi.';break;}
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$error='Email non valida.';break;}
    if(strlen($uname)<3||strlen($uname)>30){$error='Username: 3–30 caratteri.';break;}
    if(!preg_match('/^[a-zA-Z0-9_]+$/',$uname)){$error='Username: solo lettere, numeri e underscore.';break;}
    if(strlen($pw)<6){$error='Password: minimo 6 caratteri.';break;}
    if($pw!==$pw2){$error='Le password non coincidono.';break;}
    try{
      $pdo=getDB();
      $st=$pdo->prepare("SELECT id FROM invite_tokens WHERE token=? AND used_by IS NULL LIMIT 1");
      $st->execute([$token]);$tok=$st->fetch();
      if(!$tok){$error='Token non valido o già usato.';break;}
      $st=$pdo->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
      $st->execute([$uname,$email]);
      if($st->fetch()){$error='Username o email già in uso.';break;}
      $hash=password_hash($pw,PASSWORD_BCRYPT);
      $pdo->prepare("INSERT INTO users (username,email,password) VALUES (?,?,?)")->execute([$uname,$email,$hash]);
      $uid=$pdo->lastInsertId();
      $pdo->prepare("UPDATE invite_tokens SET used_by=?,used_at=NOW() WHERE id=?")->execute([$uid,$tok['id']]);
      $success='Account creato! Ora puoi <a href="login.php">accedere</a>.';
    }catch(Exception $e){$error='Errore del server.';}
  }while(false);
}
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Community Network – Registrazione</title>
<style>
:root{--bg:#0a0a0f;--surface:#111118;--border:#1e1e2e;--text:#d0d0e0;--text2:#7070a0;--accent:#5c7cfa;--green:#37b679;--red:#e64545}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.wrap{width:400px}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{width:48px;height:48px;background:var(--accent);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;margin-bottom:10px}
.logo h1{font-size:1.3rem;color:var(--text)}
.logo p{font-size:.82rem;color:var(--text2);margin-top:4px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:28px}
.field{margin-bottom:14px}
label{display:block;font-size:.78rem;color:var(--text2);margin-bottom:5px;font-weight:500}
input{width:100%;padding:9px 12px;background:var(--bg);border:1px solid #2a2a3e;border-radius:6px;color:var(--text);font-size:.9rem;transition:border-color .15s}
input:focus{outline:none;border-color:var(--accent)}
.hint{font-size:.72rem;color:var(--text2);margin-top:3px}
.btn{width:100%;padding:10px;background:var(--green);border:none;border-radius:7px;color:#fff;font-size:.9rem;font-weight:700;cursor:pointer;margin-top:8px;transition:background .15s}
.btn:hover{background:#2e9e66}
.err{background:#2a0a0a;border:1px solid var(--red);border-radius:6px;padding:9px 12px;font-size:.82rem;color:#ff8080;margin-bottom:14px}
.ok{background:#0a2a1a;border:1px solid var(--green);border-radius:6px;padding:9px 12px;font-size:.82rem;color:#68d391;margin-bottom:14px}
.ok a{color:#68d391}
.foot{text-align:center;margin-top:16px;font-size:.8rem;color:var(--text2)}
.foot a{color:var(--accent);text-decoration:none}
.token-input{font-family:monospace;letter-spacing:.06em}
</style></head><body>
<div class="wrap">
  <div class="logo">
    <div class="logo-icon">C</div>
    <h1>Community Network</h1>
    <p>Crea un nuovo account</p>
  </div>
  <div class="card">
    <?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
    <?php if($success):?><div class="ok"><?=$success?></div><?php else:?>
    <form method="POST">
      <div class="field"><label>Token di invito</label><input class="token-input" type="text" name="token" placeholder="xxxx-xxxx-xxxx-xxxx" required value="<?=htmlspecialchars($_POST['token']??'')?>"><p class="hint">Richiedi il token all'amministratore. SOLO CHI CONOSCE DI PERSONA L'AMMINISTRATORE DEL SITO POTRA' AVERE ACCESSO - E-mail: richiediaccesso@gmail.com</p></div>
      <div class="field"><label>Username</label><input type="text" name="username" required maxlength="30" value="<?=htmlspecialchars($_POST['username']??'')?>"><p class="hint">3–30 caratteri, solo lettere/numeri/underscore</p></div>
      <div class="field"><label>Email</label><input type="email" name="email" required value="<?=htmlspecialchars($_POST['email']??'')?>"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required minlength="6"></div>
      <div class="field"><label>Conferma password</label><input type="password" name="confirm" required></div>
      <button class="btn" type="submit">Registrati</button>
    </form>
    <?php endif;?>
  </div>
  <div class="foot">Hai già un account? <a href="login.php">Accedi</a></div>
</div>
</body></html>
