<?php
require_once 'config.php';
if(isset($_SESSION['user_id'])){header('Location: dashboard.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $u=trim($_POST['username']??'');$p=trim($_POST['password']??'');
  if($u&&$p){
    try{
      $pdo=getDB();
      $st=$pdo->prepare("SELECT id,username,password,is_admin FROM users WHERE username=? OR email=? LIMIT 1");
      $st->execute([$u,$u]);$row=$st->fetch();
      if($row&&password_verify($p,$row['password'])){
        $_SESSION['user_id']=$row['id'];$_SESSION['username']=$row['username'];$_SESSION['is_admin']=(bool)$row['is_admin'];
        $pdo->prepare("UPDATE users SET last_seen=NOW() WHERE id=?")->execute([$row['id']]);
        header('Location: dashboard.php');exit;
      }else{$error='Username o password errati.';}
    }catch(Exception $e){$error='Errore del server.';}
  }else{$error='Compila tutti i campi.';}
}
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Community Network – Login</title>
<style>
:root{--bg:#0a0a0f;--surface:#111118;--border:#1e1e2e;--text:#d0d0e0;--text2:#7070a0;--accent:#5c7cfa;--green:#37b679;--red:#e64545}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.wrap{width:360px}
.logo{text-align:center;margin-bottom:32px}
.logo-icon{width:48px;height:48px;background:var(--accent);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;margin-bottom:10px}
.logo h1{font-size:1.3rem;color:var(--text)}
.logo p{font-size:.82rem;color:var(--text2);margin-top:4px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:28px}
.field{margin-bottom:16px}
label{display:block;font-size:.78rem;color:var(--text2);margin-bottom:5px;font-weight:500}
input{width:100%;padding:9px 12px;background:var(--bg);border:1px solid #2a2a3e;border-radius:6px;color:var(--text);font-size:.9rem;transition:border-color .15s}
input:focus{outline:none;border-color:var(--accent)}
.btn{width:100%;padding:10px;background:var(--accent);border:none;border-radius:7px;color:#fff;font-size:.9rem;font-weight:700;cursor:pointer;margin-top:8px;transition:background .15s}
.btn:hover{background:#4a6be0}
.err{background:#2a0a0a;border:1px solid var(--red);border-radius:6px;padding:9px 12px;font-size:.82rem;color:#ff8080;margin-bottom:14px}
.foot{text-align:center;margin-top:16px;font-size:.8rem;color:var(--text2)}
.foot a{color:var(--accent);text-decoration:none}
.foot a:hover{text-decoration:underline}
</style></head><body>
<div class="wrap">
  <div class="logo">
    <div class="logo-icon">C</div>
    <h1>Community Network</h1>
    <p>Accedi al tuo account</p>
  </div>
  <div class="card">
    <?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
    <form method="POST">
      <div class="field"><label>Username o Email</label><input type="text" name="username" autofocus required value="<?=htmlspecialchars($_POST['username']??'')?>"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required></div>
      <button class="btn" type="submit">Accedi</button>
    </form>
  </div>
  <div class="foot">Non hai un account? <a href="register.php">Registrati</a></div>
</div>
</body></html>
