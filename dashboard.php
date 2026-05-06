<?php
require_once 'auth.php';
requireLogin();
$me  = ['id'=>(int)$_SESSION['user_id'],'username'=>$_SESSION['username'],'is_admin'=>!empty($_SESSION['is_admin'])];
$pdo = getDB();
$myAv= $pdo->prepare("SELECT avatar FROM user_profiles WHERE user_id=?");
$myAv->execute([$me['id']]); $myAv=($myAv->fetchColumn())?:null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Walker Network</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
:root{
  --bg:       #0a0a0f;
  --surface:  #111118;
  --surface2: #16161f;
  --border:   #1e1e2e;
  --border2:  #252535;
  --text:     #d0d0e0;
  --text2:    #7070a0;
  --accent:   #5c7cfa;
  --accent2:  #4263d9;
  --green:    #37b679;
  --red:      #e64545;
  --radius:   8px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);font-size:14px}
/* ─── SCROLLBAR ─── */
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px}
/* ─── LAYOUT ─── */
#app{display:flex;flex-direction:column;height:100vh}
/* ─── TOPBAR ─── */
#topbar{
  height:48px;padding:0 16px;display:flex;align-items:center;gap:12px;
  background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0;
}
.logo{font-weight:700;font-size:.95rem;letter-spacing:.04em;color:var(--text);
      display:flex;align-items:center;gap:8px}
.logo-icon{width:26px;height:26px;background:var(--accent);border-radius:6px;
           display:flex;align-items:center;justify-content:center;font-size:.8rem;color:#fff}
.spacer{flex:1}
#topbar a,#topbar button{font-size:.8rem;color:var(--text2);text-decoration:none;
  background:none;border:none;cursor:pointer;padding:4px 8px;border-radius:4px;transition:all .15s}
#topbar a:hover,#topbar button:hover{color:var(--text);background:var(--border)}
.badge-admin{background:var(--accent);color:#fff;padding:3px 8px;border-radius:4px;font-size:.75rem;font-weight:600}
/* ─── AVATAR SMALL ─── */
.av-xs{width:28px;height:28px;border-radius:50%;background:var(--border2);border:2px solid var(--border);
       display:flex;align-items:center;justify-content:center;font-size:.75rem;color:var(--text2);
       overflow:hidden;flex-shrink:0;font-weight:600}
.av-xs img{width:100%;height:100%;object-fit:cover}
/* ─── MAIN ─── */
#main{display:flex;flex:1;overflow:hidden}
/* ─── SIDEBAR ─── */
#sidebar{
  width:210px;min-width:210px;background:var(--surface);
  border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;
}
.sb-section{border-bottom:1px solid var(--border)}
.sb-head{
  padding:10px 14px;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;
  color:var(--text2);display:flex;align-items:center;justify-content:space-between;
}
.sb-count{background:var(--border2);color:var(--green);padding:1px 6px;border-radius:10px;font-size:.7rem}
.add-btn{background:none;border:none;color:var(--text2);cursor:pointer;
          font-size:1.1rem;line-height:1;border-radius:4px;padding:1px 4px}
.add-btn:hover{color:var(--accent);background:var(--border)}
.sb-list{overflow-y:auto;max-height:200px}
.sb-item{
  display:flex;align-items:center;gap:8px;padding:7px 12px;
  cursor:pointer;transition:background .12s;border-left:2px solid transparent;
}
.sb-item:hover{background:var(--surface2)}
.sb-item.active{background:var(--surface2);border-left-color:var(--accent)}
.online-dot{width:7px;height:7px;border-radius:50%;background:var(--green);flex-shrink:0}
.sb-name{font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.sb-call{background:none;border:none;color:var(--accent);cursor:pointer;font-size:.9rem;
          display:none;padding:2px 4px;border-radius:3px}
.sb-item:hover .sb-call{display:block}
.sb-call:hover{background:var(--border)}
/* ─── CONTENT ─── */
#content{flex:1;display:flex;flex-direction:column;overflow:hidden}
/* ─── TABS ─── */
#tabs{
  display:flex;align-items:center;background:var(--surface);
  border-bottom:1px solid var(--border);flex-shrink:0;padding:0 4px;gap:2px;
}
.tab{
  padding:11px 14px;cursor:pointer;font-size:.82rem;color:var(--text2);
  border-bottom:2px solid transparent;transition:all .15s;white-space:nowrap;
  border-radius:4px 4px 0 0;
}
.tab:hover{color:var(--text)}
.tab.active{color:var(--accent);border-bottom-color:var(--accent);font-weight:600}
#chat-ctx{font-size:.75rem;color:var(--accent);margin:auto 0 auto -4px;
           max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.8}
/* ─── PANELS ─── */
.panel{flex:1;display:none;flex-direction:column;overflow:hidden}
.panel.active{display:flex}
/* ─── CHAT ─── */
#chat-head{
  padding:10px 16px;background:var(--surface2);border-bottom:1px solid var(--border);
  font-size:.85rem;color:var(--text);flex-shrink:0;display:flex;align-items:center;gap:8px;
}
#chat-head span{color:var(--text2);font-size:.78rem}
#messages{flex:1;overflow-y:auto;padding:14px 16px;display:flex;flex-direction:column;gap:2px}
/* message rows */
.msg-row{display:flex;gap:8px;align-items:flex-end;max-width:76%}
.msg-row.mine{align-self:flex-end;flex-direction:row-reverse}
.msg-av{width:28px;height:28px;border-radius:50%;background:var(--border2);border:1px solid var(--border2);
        flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.72rem;
        color:var(--text2);overflow:hidden;font-weight:600;margin-bottom:2px}
.msg-av img{width:100%;height:100%;object-fit:cover}
.msg-body{display:flex;flex-direction:column;gap:2px}
.msg-meta{font-size:.68rem;color:var(--text2);padding:0 2px}
.msg-row.mine .msg-meta{text-align:right}
.bubble{
  padding:8px 12px;border-radius:14px;font-size:.88rem;line-height:1.5;
  word-break:break-word;background:var(--surface2);border:1px solid var(--border);
  border-bottom-left-radius:4px;
}
.mine .bubble{background:var(--accent2);border-color:var(--accent);color:#fff;
              border-bottom-right-radius:4px;border-bottom-left-radius:14px}
/* attachments in chat */
.att-img{max-width:220px;max-height:150px;border-radius:8px;display:block;
         cursor:pointer;margin-top:5px;border:1px solid var(--border2)}
.att-file{
  display:flex;align-items:center;gap:7px;margin-top:5px;
  background:rgba(0,0,0,.3);border:1px solid var(--border2);border-radius:7px;
  padding:6px 10px;font-size:.8rem;color:var(--text2);text-decoration:none;
  transition:background .15s;
}
.att-file:hover{background:rgba(255,255,255,.05)}
/* ─── INPUT AREA ─── */
#input-wrap{border-top:1px solid var(--border);flex-shrink:0}
#att-bar{
  display:none;padding:6px 14px;background:var(--surface2);
  font-size:.78rem;color:var(--text2);border-bottom:1px solid var(--border);
  display:none;align-items:center;gap:8px;
}
#att-bar.visible{display:flex}
#att-bar .rm{color:var(--red);cursor:pointer;margin-left:auto;font-size:.8rem}
#att-bar .rm:hover{color:#ff6b6b}
#input-row{display:flex;gap:8px;padding:10px 14px;align-items:flex-end}
#clip-btn{
  padding:8px 9px;background:var(--surface2);border:1px solid var(--border2);
  border-radius:6px;color:var(--text2);cursor:pointer;font-size:.95rem;flex-shrink:0;
  transition:all .15s;
}
#clip-btn:hover{color:var(--accent);border-color:var(--accent)}
#file-inp{display:none}
#chat-inp{
  flex:1;padding:8px 12px;background:var(--surface2);border:1px solid var(--border2);
  border-radius:8px;color:var(--text);font-size:.88rem;resize:none;font-family:inherit;
  min-height:36px;max-height:120px;transition:border-color .15s;
}
#chat-inp:focus{outline:none;border-color:var(--accent)}
#send-btn{
  padding:8px 16px;background:var(--accent);border:none;border-radius:8px;
  color:#fff;cursor:pointer;font-size:.85rem;font-weight:600;flex-shrink:0;
  transition:background .15s;
}
#send-btn:hover{background:var(--accent2)}
/* ─── GROUPS PANEL ─── */
#groups-panel{padding:16px;overflow-y:auto;flex:1;gap:10px;display:none;flex-direction:column}
#groups-panel.active{display:flex}
.new-grp-btn{
  padding:9px 16px;background:var(--green);border:none;border-radius:6px;
  color:#fff;font-size:.85rem;font-weight:600;cursor:pointer;align-self:flex-start;
  transition:background .15s;
}
.new-grp-btn:hover{background:#2e9e66}
.g-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
  padding:14px;display:flex;flex-direction:column;gap:6px;
}
.g-card-name{font-weight:700;font-size:.92rem;color:var(--text)}
.g-card-desc{font-size:.8rem;color:var(--text2)}
.g-card-meta{font-size:.72rem;color:var(--text2)}
.g-card-meta strong{color:var(--text)}
.g-btns{display:flex;gap:6px;margin-top:4px;flex-wrap:wrap}
.g-btn{
  padding:5px 12px;border:none;border-radius:5px;font-size:.78rem;
  cursor:pointer;font-weight:600;transition:all .15s;
}
.g-btn-chat{background:#1a2a4a;color:var(--accent);border:1px solid var(--accent)}
.g-btn-chat:hover{background:var(--accent);color:#fff}
.g-btn-join{background:#0a2a1a;color:var(--green);border:1px solid var(--green)}
.g-btn-join:hover{background:var(--green);color:#fff}
.g-btn-leave,.g-btn-del{background:#2a0a0a;color:var(--red);border:1px solid var(--red)}
.g-btn-leave:hover,.g-btn-del:hover{background:var(--red);color:#fff}
/* ─── MAP ─── */
#map-panel{position:relative}
#map{flex:1;z-index:1}
#map-tip{
  position:absolute;top:12px;right:12px;z-index:1000;
  background:rgba(10,10,15,.9);border:1px solid var(--border2);border-radius:6px;
  padding:10px 14px;font-size:.78rem;color:var(--text2);max-width:200px;backdrop-filter:blur(4px);
}
#map-tip strong{color:var(--text);display:block;margin-bottom:3px;font-size:.82rem}
/* ─── VIDEO ─── */
#video-panel{
  align-items:center;justify-content:center;gap:18px;padding:24px;
  background:radial-gradient(ellipse at center, #0d0d1a 0%, var(--bg) 100%);
}
#call-stat{font-size:.9rem;color:var(--text2);text-align:center;min-height:20px}
#videos{display:flex;gap:14px;flex-wrap:wrap;justify-content:center;width:100%;max-width:900px}
video{background:#0a0a12;border-radius:10px;border:1px solid var(--border2)}
#remote-v{width:640px;max-width:100%;height:360px;object-fit:cover}
#local-v{width:190px;height:107px;object-fit:cover}
#call-btns{display:flex;gap:10px;flex-wrap:wrap;justify-content:center}
.c-btn{
  padding:9px 18px;border:none;border-radius:7px;font-size:.85rem;
  cursor:pointer;font-weight:600;transition:all .15s;display:none;
}
#c-end{background:var(--red);color:#fff}
#c-end:hover{background:#c53030}
#c-mute,#c-cam{background:var(--border2);color:var(--text)}
#c-mute:hover,#c-cam:hover{background:var(--border)}
#c-end.show,#c-mute.show,#c-cam.show{display:block}
/* ─── MODALS ─── */
.modal{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);
  z-index:9999;align-items:center;justify-content:center;
  backdrop-filter:blur(3px);
}
.modal.open{display:flex}
.modal-box{
  background:var(--surface);border:1px solid var(--border2);border-radius:10px;
  padding:26px;width:380px;max-width:94vw;
}
.modal-box h3{font-size:1rem;margin-bottom:16px;color:var(--text)}
.mf{margin-bottom:12px}
.mf label{display:block;font-size:.78rem;color:var(--text2);margin-bottom:4px}
.mf input,.mf textarea{
  width:100%;padding:8px 11px;background:var(--bg);border:1px solid var(--border2);
  border-radius:6px;color:var(--text);font-size:.88rem;font-family:inherit;
}
.mf input:focus,.mf textarea:focus{outline:none;border-color:var(--accent)}
.m-btns{display:flex;gap:8px;justify-content:flex-end;margin-top:16px}
.m-btn{padding:7px 18px;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:600}
.m-cancel{background:var(--border2);color:var(--text)}
.m-ok{background:var(--green);color:#fff}
/* ─── INCOMING CALL ─── */
#call-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);
              z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
#call-overlay.open{display:flex}
#call-box{
  background:var(--surface);border:1px solid var(--border2);border-radius:12px;
  padding:32px 40px;text-align:center;
}
#call-box h3{font-size:1.1rem;margin-bottom:6px}
#call-box p{color:var(--text2);font-size:.85rem;margin-bottom:22px}
.call-btns{display:flex;gap:12px;justify-content:center}
.call-btns button{
  padding:10px 26px;border:none;border-radius:7px;cursor:pointer;
  font-size:.9rem;font-weight:700;transition:all .15s;
}
#c-accept{background:var(--green);color:#fff}
#c-accept:hover{background:#2e9e66}
#c-reject{background:var(--red);color:#fff}
#c-reject:hover{background:#c53030}
/* ─── EMPTY / LOADING ─── */
.empty{color:var(--text2);font-size:.85rem;padding:12px 0}
</style>
</head>
<body>
<div id="app">

<!-- TOPBAR -->
<div id="topbar">
  <div class="logo">
    <div class="logo-icon">C</div>
    Community Network
  </div>
  <div class="spacer"></div>
  <div class="av-xs" id="my-av">
    <?php if($myAv):?>
      <img src="uploads/avatars/<?=htmlspecialchars($myAv)?>" alt="">
    <?php else: echo htmlspecialchars(strtoupper(substr($me['username'],0,1))); endif;?>
  </div>
  <a href="profile.php"><?=htmlspecialchars($me['username'])?></a>
  <?php if($me['is_admin']):?><a href="admin.php" class="badge-admin">Admin</a><?php endif;?>
  <a href="logout.php">Esci</a>
</div>

<div id="main">

  <!-- SIDEBAR -->
  <div id="sidebar">
    <div class="sb-section">
      <div class="sb-head">
        Online &nbsp;<span class="sb-count" id="sb-online">0</span>
      </div>
      <div class="sb-list" id="sb-users"></div>
    </div>
    <div class="sb-section">
      <div class="sb-head">
        Gruppi
        <button class="add-btn" onclick="openCreateGroup()" title="Crea gruppo">＋</button>
      </div>
      <div class="sb-list" id="sb-groups"></div>
    </div>
  </div>

  <!-- CONTENT -->
  <div id="content">
    <div id="tabs">
      <div class="tab active" data-tab="chat"   onclick="switchTab('chat')">Chat</div>
      <span id="chat-ctx" style="display:none"></span>
      <div class="tab" data-tab="groups" onclick="switchTab('groups')">Gruppi</div>
      <div class="tab" data-tab="map"    onclick="switchTab('map')">Mappa</div>
      <div class="tab" data-tab="video"  onclick="switchTab('video')">Video</div>
    </div>

    <!-- CHAT -->
    <div class="panel active" id="chat-panel">
      <div id="chat-head">
        <span id="chat-head-title">💬 Chat globale</span>
        <span id="chat-head-sub"></span>
      </div>
      <div id="messages"></div>
      <div id="input-wrap">
        <div id="att-bar">
          <span>📎</span><span id="att-name"></span>
          <span class="rm" onclick="clearAtt()">✕ rimuovi</span>
        </div>
        <div id="input-row">
          <button id="clip-btn" onclick="document.getElementById('file-inp').click()" title="Allega file">📎</button>
          <input type="file" id="file-inp" onchange="onFileSelect(this)">
          <textarea id="chat-inp" rows="1" placeholder="Scrivi un messaggio…"></textarea>
          <button id="send-btn" onclick="sendMsg()">Invia ▶</button>
        </div>
      </div>
    </div>

    <!-- GROUPS PANEL -->
    <div class="panel" id="groups-panel">
      <div style="padding:16px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:10px">
        <button class="new-grp-btn" onclick="openCreateGroup()">+ Crea gruppo</button>
        <div id="grp-container"><p class="empty">Caricamento…</p></div>
      </div>
    </div>

    <!-- MAP -->
    <div class="panel" id="map-panel">
      <div id="map-tip">
        <strong>Mappa interattiva</strong>
        Clicca per aggiungere un pin · Tasto destro sul tuo pin per rimuoverlo
      </div>
      <div id="map"></div>
    </div>

    <!-- VIDEO -->
    <div class="panel" id="video-panel">
      <div id="call-stat">Clicca 📞 su un utente per avviare una chiamata</div>
      <div id="videos">
        <video id="remote-v" autoplay playsinline></video>
        <video id="local-v"  autoplay playsinline muted></video>
      </div>
      <div id="call-btns">
        <button id="c-end"  class="c-btn" onclick="endCall()">📵 Termina</button>
        <button id="c-mute" class="c-btn" onclick="toggleMute()">🎤 Muto</button>
        <button id="c-cam"  class="c-btn" onclick="toggleCam()">📷 Camera</button>
      </div>
    </div>
  </div>
</div>
</div><!-- #app -->

<!-- MODAL: pin -->
<div class="modal" id="pin-modal">
  <div class="modal-box">
    <h3>📍 Aggiungi pin</h3>
    <div class="mf">
      <label>Messaggio (opzionale)</label>
      <textarea id="pin-msg" rows="3" placeholder="Descrivi questo luogo…" maxlength="500"></textarea>
    </div>
    <div class="m-btns">
      <button class="m-btn m-cancel" onclick="closePinModal()">Annulla</button>
      <button class="m-btn m-ok"     onclick="submitPin()">Salva pin</button>
    </div>
  </div>
</div>

<!-- MODAL: create group -->
<div class="modal" id="grp-modal">
  <div class="modal-box">
    <h3>Crea nuovo gruppo</h3>
    <div class="mf">
      <label>Nome *</label>
      <input type="text" id="grp-name" maxlength="80" placeholder="es. Team Alpha">
    </div>
    <div class="mf">
      <label>Descrizione</label>
      <textarea id="grp-desc" rows="2" maxlength="300" placeholder="Breve descrizione…"></textarea>
    </div>
    <div class="m-btns">
      <button class="m-btn m-cancel" onclick="closeGrpModal()">Annulla</button>
      <button class="m-btn m-ok"     onclick="submitCreateGroup()">Crea</button>
    </div>
  </div>
</div>

<!-- INCOMING CALL -->
<div id="call-overlay">
  <div id="call-box">
    <h3 id="caller-lbl">Chiamata in arrivo</h3>
    <p>vuole avviare una videochiamata</p>
    <div class="call-btns">
      <button id="c-accept" onclick="acceptCall()">📞 Accetta</button>
      <button id="c-reject" onclick="rejectCall()">📵 Rifiuta</button>
    </div>
  </div>
</div>

<script>
/* ════════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════ */
const ME = {id:<?=$me['id']?>,username:<?=json_encode($me['username'])?>};
let chatMode    = 'global';   // 'global'|'dm'|'group'
let dmPeer      = null;       // {id,username}
let activeGrp   = null;       // {id,name}
let lastMsgId   = 0;
let lastSigId   = 0;
let pendingAtt  = null;       // {id,name,mime}

/* WebRTC */
let callState   = 'idle';     // idle|calling|ringing|in-call
let callPeer    = null;
let incomingPeer= null;
let pc          = null;
let localStream = null;
let muted       = false;
let camOff      = false;
/* caller = chi chiama (true) | callee = chi risponde (false) */
let isCaller    = false;

/* Map */
let lmap        = null;
let pins        = {};
let pendingPin  = null;
let mapReady    = false;

/* ════════════════════════════════════════════════════════
   TABS
═══════════════════════════════════════════════════════ */
function switchTab(t){
  document.querySelectorAll('.tab').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll('.panel').forEach(el=>el.classList.remove('active'));
  document.querySelector('[data-tab="'+t+'"]').classList.add('active');
  document.getElementById(t+'-panel').classList.add('active');
  if(t==='map'    && !mapReady) initMap();
  if(t==='groups') loadGroupsPanel();
}

/* ════════════════════════════════════════════════════════
   USERS SIDEBAR
═══════════════════════════════════════════════════════ */
async function pollUsers(){
  try{
    const list=await apiFetch('api/users.php');
    document.getElementById('sb-online').textContent=list.length;
    const ul=document.getElementById('sb-users');
    ul.innerHTML='';
    list.forEach(u=>{
      if(u.id===ME.id)return;
      const d=document.createElement('div');
      d.className='sb-item'+(dmPeer?.id===u.id?' active':'');
      const av=u.avatar
        ?'<div class="av-xs" style="width:22px;height:22px"><img src="uploads/avatars/'+esc(u.avatar)+'" alt=""></div>'
        :'<div class="av-xs" style="width:22px;height:22px;font-size:.68rem">'+esc(u.username.charAt(0).toUpperCase())+'</div>';
      d.innerHTML=av+'<span class="online-dot"></span><span class="sb-name">'+esc(u.username)+'</span>'
        +'<button class="sb-call" onclick="startCall('+u.id+',\''+esc(u.username)+'\',event)" title="Chiama">📞</button>';
      d.addEventListener('click',e=>{if(!e.target.classList.contains('sb-call'))openDM(u.id,u.username);});
      ul.appendChild(d);
    });
  }catch(e){}
}

/* ════════════════════════════════════════════════════════
   GROUPS SIDEBAR
═══════════════════════════════════════════════════════ */
async function pollGroupsSide(){
  try{
    const groups=await apiFetch('api/groups.php?action=list');
    const ul=document.getElementById('sb-groups');
    ul.innerHTML='';
    const mine=groups.filter(g=>+g.is_member>0);
    if(!mine.length){ul.innerHTML='<div style="padding:8px 14px;font-size:.78rem;color:var(--text2)">Nessun gruppo</div>';return;}
    mine.forEach(g=>{
      const d=document.createElement('div');
      d.className='sb-item'+(activeGrp?.id===g.id?' active':'');
      d.innerHTML='<span style="color:var(--text2);font-size:.85rem;flex-shrink:0">#</span><span class="sb-name">'+esc(g.name)+'</span>';
      d.addEventListener('click',()=>openGrpChat(g.id,g.name));
      ul.appendChild(d);
    });
  }catch(e){}
}

/* ════════════════════════════════════════════════════════
   CHAT MODE SWITCHERS
═══════════════════════════════════════════════════════ */
function openGlobal(){
  chatMode='global';dmPeer=null;activeGrp=null;lastMsgId=0;
  document.getElementById('messages').innerHTML='';
  document.getElementById('chat-head-title').textContent='💬 Chat globale';
  document.getElementById('chat-head-sub').textContent='';
  document.getElementById('chat-ctx').style.display='none';
  switchTab('chat');pollMsgs();
}
function openDM(uid,uname){
  chatMode='dm';dmPeer={id:uid,username:uname};activeGrp=null;lastMsgId=0;
  document.getElementById('messages').innerHTML='';
  document.getElementById('chat-head-title').textContent='✉️ '+uname;
  document.getElementById('chat-head-sub').textContent='DM';
  document.getElementById('chat-ctx').style.display='';
  document.getElementById('chat-ctx').textContent=uname;
  refreshSbActive();switchTab('chat');pollMsgs();
}
function openGrpChat(gid,gname){
  chatMode='group';activeGrp={id:gid,name:gname};dmPeer=null;lastMsgId=0;
  document.getElementById('messages').innerHTML='';
  document.getElementById('chat-head-title').textContent='# '+gname;
  document.getElementById('chat-head-sub').textContent='Gruppo';
  document.getElementById('chat-ctx').style.display='';
  document.getElementById('chat-ctx').textContent=gname;
  refreshSbActive();switchTab('chat');pollMsgs();
}
function refreshSbActive(){
  document.querySelectorAll('#sb-users .sb-item,#sb-groups .sb-item').forEach(el=>el.classList.remove('active'));
}

/* ════════════════════════════════════════════════════════
   MESSAGES
═══════════════════════════════════════════════════════ */
async function pollMsgs(){
  try{
    let url;
    if(chatMode==='group'&&activeGrp)
      url='api/groups.php?action=messages&group_id='+activeGrp.id+'&after='+lastMsgId;
    else{
      url='api/messages.php?after='+lastMsgId;
      if(chatMode==='dm'&&dmPeer) url+='&peer='+dmPeer.id;
    }
    const msgs=await apiFetch(url);
    if(!Array.isArray(msgs))return;
    const box=document.getElementById('messages');
    const atBot=box.scrollHeight-box.scrollTop-box.clientHeight<60;
    msgs.forEach(m=>{if(+m.id>lastMsgId)lastMsgId=+m.id;box.appendChild(buildMsgEl(m));});
    if(atBot)box.scrollTop=box.scrollHeight;
  }catch(e){}
}

function buildMsgEl(m){
  const mine=+m.sender_id===ME.id;
  const row=document.createElement('div');
  row.className='msg-row'+(mine?' mine':'');
  const av=m.sender_avatar
    ?'<div class="msg-av"><img src="uploads/avatars/'+esc(m.sender_avatar)+'" alt=""></div>'
    :'<div class="msg-av">'+esc(m.username.charAt(0).toUpperCase())+'</div>';
  let att='';
  if(m.attach_file){
    if(m.attach_mime&&m.attach_mime.startsWith('image/')){
      att='<img class="att-img" src="uploads/files/'+esc(m.attach_file)+'" onclick="window.open(this.src)" alt="'+esc(m.attach_name||'')+'">';
    }else{
      const kb=m.attach_size?Math.round(+m.attach_size/1024)+'KB':'';
      att='<a class="att-file" href="uploads/files/'+esc(m.attach_file)+'" download="'+esc(m.attach_name||'')+'" target="_blank">📎 '+esc(m.attach_name||'file')+(kb?' &middot; '+kb:'')+'</a>';
    }
  }
  const txt=m.content?esc(m.content):'';
  row.innerHTML=av+'<div class="msg-body"><div class="msg-meta">'+esc(m.username)+' · '+relTime(m.created_at)+'</div><div class="bubble">'+txt+att+'</div></div>';
  return row;
}

/* ════════════════════════════════════════════════════════
   FILE UPLOAD
═══════════════════════════════════════════════════════ */
async function onFileSelect(inp){
  const f=inp.files[0];if(!f)return;
  const bar=document.getElementById('att-bar'),nm=document.getElementById('att-name');
  nm.textContent='Caricamento '+f.name+'…';bar.classList.add('visible');
  const fd=new FormData();fd.append('file',f);
  try{
    const d=await apiFetch('api/upload.php',{method:'POST',body:fd,noJson:true});
    if(d.error){alert('Errore upload: '+d.error);clearAtt();return;}
    pendingAtt={id:d.id,name:d.original,mime:d.mime};
    nm.textContent=d.original+' ('+Math.round(d.size/1024)+' KB)';
  }catch(e){alert('Upload fallito');clearAtt();}
  inp.value='';
}
function clearAtt(){pendingAtt=null;document.getElementById('att-bar').classList.remove('visible');document.getElementById('att-name').textContent='';}

/* ════════════════════════════════════════════════════════
   SEND MESSAGE
═══════════════════════════════════════════════════════ */
async function sendMsg(){
  const inp=document.getElementById('chat-inp'),content=inp.value.trim();
  if(!content&&!pendingAtt)return;
  inp.value='';
  const body={content:content||null};
  if(pendingAtt)body.attachment_id=pendingAtt.id;
  clearAtt();
  try{
    if(chatMode==='group'&&activeGrp){
      body.action='send';body.group_id=activeGrp.id;
      await apiFetch('api/groups.php',{method:'POST',json:body});
    }else{
      if(chatMode==='dm'&&dmPeer)body.receiver_id=dmPeer.id;
      await apiFetch('api/messages.php',{method:'POST',json:body});
    }
    pollMsgs();
  }catch(e){}
}
document.getElementById('chat-inp').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();}});

/* ════════════════════════════════════════════════════════
   GROUPS PANEL
═══════════════════════════════════════════════════════ */
async function loadGroupsPanel(){
  const c=document.getElementById('grp-container');
  c.innerHTML='<p class="empty">Caricamento…</p>';
  try{
    const gs=await apiFetch('api/groups.php?action=list');
    if(!gs.length){c.innerHTML='<p class="empty">Nessun gruppo ancora. Creane uno!</p>';return;}
    c.innerHTML='';
    gs.forEach(g=>{
      const isM=+g.is_member>0;
      const isMine=+g.created_by===ME.id;
      const card=document.createElement('div');card.className='g-card';
      let btns=isM
        ?'<button class="g-btn g-btn-chat" onclick="openGrpChat('+g.id+',\''+esc(g.name)+'\')">💬 Apri chat</button>'
         +(isMine?'<button class="g-btn g-btn-del" onclick="deleteGrp('+g.id+')">🗑 Elimina</button>'
                 :'<button class="g-btn g-btn-leave" onclick="leaveGrp('+g.id+')">← Esci</button>')
        :'<button class="g-btn g-btn-join" onclick="joinGrp('+g.id+')">+ Entra</button>';
      card.innerHTML='<div class="g-card-name"># '+esc(g.name)+'</div>'
        +(g.description?'<div class="g-card-desc">'+esc(g.description)+'</div>':'')
        +'<div class="g-card-meta">Creato da <strong>'+esc(g.creator)+'</strong> · '+g.member_count+' membri</div>'
        +'<div class="g-btns">'+btns+'</div>';
      c.appendChild(card);
    });
  }catch(e){c.innerHTML='<p class="empty" style="color:var(--red)">Errore caricamento</p>';}
}

function openCreateGroup(){document.getElementById('grp-name').value='';document.getElementById('grp-desc').value='';document.getElementById('grp-modal').classList.add('open');setTimeout(()=>document.getElementById('grp-name').focus(),60);}
function closeGrpModal(){document.getElementById('grp-modal').classList.remove('open');}
async function submitCreateGroup(){
  const nm=document.getElementById('grp-name').value.trim();
  if(!nm){alert('Nome obbligatorio');return;}
  const desc=document.getElementById('grp-desc').value.trim();
  await apiFetch('api/groups.php',{method:'POST',json:{action:'create',name:nm,description:desc}});
  closeGrpModal();loadGroupsPanel();pollGroupsSide();
}
async function joinGrp(id){
  await apiFetch('api/groups.php',{method:'POST',json:{action:'join',group_id:id}});
  loadGroupsPanel();pollGroupsSide();
}
async function leaveGrp(id){
  if(!confirm('Abbandonare il gruppo?'))return;
  await apiFetch('api/groups.php',{method:'POST',json:{action:'leave',group_id:id}});
  if(activeGrp?.id===id)openGlobal();
  loadGroupsPanel();pollGroupsSide();
}
async function deleteGrp(id){
  if(!confirm('Eliminare definitivamente il gruppo e tutti i messaggi?'))return;
  await apiFetch('api/groups.php',{method:'POST',json:{action:'delete',group_id:id}});
  if(activeGrp?.id===id)openGlobal();
  loadGroupsPanel();pollGroupsSide();
}

/* ════════════════════════════════════════════════════════
   MAP
═══════════════════════════════════════════════════════ */
function initMap(){
  mapReady=true;
  lmap=L.map('map').setView([41.9,12.5],5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    attribution:'© <a href="https://openstreetmap.org">OpenStreetMap</a>'
  }).addTo(lmap);
  lmap.on('click',e=>openPinModal(e.latlng));
  loadPins();
}
function openPinModal(ll){
  pendingPin=ll;
  document.getElementById('pin-msg').value='';
  document.getElementById('pin-modal').classList.add('open');
  setTimeout(()=>document.getElementById('pin-msg').focus(),60);
}
function closePinModal(){document.getElementById('pin-modal').classList.remove('open');}
async function submitPin(){
  if(!pendingPin)return;
  const lat=pendingPin.lat, lng=pendingPin.lng;
  const msg=document.getElementById('pin-msg').value.trim();
  pendingPin=null;
  closePinModal();
  const res=await apiFetch('api/map.php',{method:'POST',json:{lat,lng,message:msg||null}});
  if(res&&res.error){console.error('Pin error:',res.error,res);}
  else loadPins();
}
async function loadPins(){
  if(!lmap)return;
  try{
    const rows=await apiFetch('api/map.php');
    Object.values(pins).forEach(m=>lmap.removeLayer(m));pins={};
    if(!Array.isArray(rows))return;
    rows.forEach(p=>{
      const popup='<b>'+esc(p.username)+'</b>'+(p.message?'<br><span style="font-size:.85rem">'+esc(p.message)+'</span>':'')+'<br><small style="color:#999">'+relTime(p.created_at)+'</small>';
      const m=L.marker([+p.lat,+p.lng]).addTo(lmap).bindPopup(popup);
      if(+p.user_id===ME.id){m.on('contextmenu',()=>removePin(p.id));}
      pins[p.id]=m;
    });
  }catch(e){}
}
async function removePin(id){
  if(!confirm('Rimuovere il pin?'))return;
  await apiFetch('api/map.php',{method:'DELETE',json:{id}});loadPins();
}

/* ════════════════════════════════════════════════════════
   WEBRTC — flusso corretto:
   Caller  → call-request → Callee
   Callee  → call-accepted → Caller
   Caller  → createOffer → offer → Callee
   Callee  → createAnswer → answer → Caller
   Entrambi si scambiano ICE candidates
═══════════════════════════════════════════════════════ */
const ICE={iceServers:[
  {urls:'stun:stun.l.google.com:19302'},
  {urls:'stun:stun1.l.google.com:19302'},
  {urls:'stun:stun2.l.google.com:19302'},
]};

function updateCallUI(){
  const st=document.getElementById('call-stat');
  document.getElementById('c-end').classList.remove('show');
  document.getElementById('c-mute').classList.remove('show');
  document.getElementById('c-cam').classList.remove('show');
  switch(callState){
    case 'idle':    st.textContent='Clicca 📞 su un utente per avviare una chiamata'; break;
    case 'calling': st.textContent='📞 In attesa che '+callPeer.username+' risponda…';
      document.getElementById('c-end').classList.add('show'); break;
    case 'ringing': st.textContent='📲 Connessione in corso…';
      document.getElementById('c-end').classList.add('show'); break;
    case 'in-call': st.textContent='🟢 In chiamata con '+callPeer.username;
      document.getElementById('c-end').classList.add('show');
      document.getElementById('c-mute').classList.add('show');
      document.getElementById('c-cam').classList.add('show'); break;
  }
}

async function getMedia(){
  localStream=await navigator.mediaDevices.getUserMedia({video:true,audio:true});
  const lv=document.getElementById('local-v');
  lv.srcObject=localStream;
  // Force play even if panel was hidden when srcObject was set
  try{ await lv.play(); }catch(e){}
}

function buildPC(){
  pc=new RTCPeerConnection(ICE);
  localStream.getTracks().forEach(t=>pc.addTrack(t,localStream));
  pc.onicecandidate=e=>{if(e.candidate)sendSig(callPeer.id,'ice',{candidate:e.candidate});};
  pc.ontrack=e=>{
    const rv=document.getElementById('remote-v');
    if(rv.srcObject!==e.streams[0])rv.srcObject=e.streams[0];
  };
  pc.onconnectionstatechange=()=>{
    if(pc.connectionState==='failed'||pc.connectionState==='disconnected'){
      document.getElementById('call-stat').textContent='⚠️ Connessione persa';
    }
  };
}

/* CALLER: inizia la chiamata */
async function startCall(uid,uname,ev){
  ev.stopPropagation();
  if(callState!=='idle'){alert('Sei già in una chiamata.');return;}
  callPeer={id:uid,username:uname};callState='calling';isCaller=true;
  updateCallUI();
  switchTab('video');
  await sendSig(uid,'call-request',{caller:ME.username});
  // Già prendo il media così il caller è pronto ad inviare l'offer non appena arriva call-accepted
  try{ await getMedia(); }catch(e){ console.warn('Media error:',e); }
}

/* CALLER riceve call-accepted: il media è già pronto, crea l'offer */
async function onCallAccepted(){
  if(callState!=='calling')return;
  callState='in-call';updateCallUI();
  // Se per qualsiasi motivo il media non fosse pronto, lo prende ora
  if(!localStream){ try{ await getMedia(); }catch(e){ console.warn('Media error:',e); } }
  buildPC();
  const offer=await pc.createOffer();
  await pc.setLocalDescription(offer);
  await sendSig(callPeer.id,'offer',{sdp:offer});
}

/* CALLEE: riceve offer → crea answer */
async function onOffer(sdp){
  // Se per qualsiasi motivo il media non è pronto (es. chiamata in arrivo mentre si naviga)
  if(!localStream){ try{ await getMedia(); }catch(e){ console.warn('Media error (offer):',e); } }
  if(!pc) buildPC();
  await pc.setRemoteDescription(new RTCSessionDescription(sdp));
  const answer=await pc.createAnswer();
  await pc.setLocalDescription(answer);
  await sendSig(callPeer.id,'answer',{sdp:answer});
  callState='in-call';updateCallUI();
  // Forza render video locale nel caso il tab fosse nascosto
  const lv=document.getElementById('local-v');
  if(lv.srcObject) try{ await lv.play(); }catch(e){}
}

/* CALLER: riceve answer */
async function onAnswer(sdp){
  await pc.setRemoteDescription(new RTCSessionDescription(sdp));
  callState='in-call';updateCallUI();
}

async function onIce(cand){if(pc&&pc.remoteDescription)await pc.addIceCandidate(new RTCIceCandidate(cand));}

/* CALLEE: accetta la chiamata */
async function acceptCall(){
  document.getElementById('call-overlay').classList.remove('open');
  callPeer=incomingPeer;callState='ringing';isCaller=false;
  updateCallUI();switchTab('video');
  // Prendi media prima di confermare così quando arriva l'offer sei già pronto
  try{ await getMedia(); }catch(e){ console.warn('Media error (callee):',e); }
  // Segnala al caller che hai accettato → lui creerà l'offer
  await sendSig(callPeer.id,'call-accepted',{});
  callState='in-call';updateCallUI();
  /* Ora il PC verrà costruito dentro onOffer() quando arriverà l'offer */
}

async function rejectCall(){
  document.getElementById('call-overlay').classList.remove('open');
  if(incomingPeer)await sendSig(incomingPeer.id,'call-rejected',{});
  incomingPeer=null;callState='idle';updateCallUI();
}

async function endCall(){
  if(callPeer)await sendSig(callPeer.id,'call-end',{});
  cleanupCall();
}

function cleanupCall(){
  if(pc){pc.close();pc=null;}
  if(localStream){localStream.getTracks().forEach(t=>t.stop());localStream=null;}
  document.getElementById('local-v').srcObject=null;
  document.getElementById('remote-v').srcObject=null;
  callState='idle';callPeer=null;incomingPeer=null;isCaller=false;
  updateCallUI();
}

function toggleMute(){muted=!muted;localStream?.getAudioTracks().forEach(t=>t.enabled=!muted);document.getElementById('c-mute').textContent=muted?'🔇 Riattiva':'🎤 Muto';}
function toggleCam(){camOff=!camOff;localStream?.getVideoTracks().forEach(t=>t.enabled=!camOff);document.getElementById('c-cam').textContent=camOff?'📷 Attiva':'📷 Camera';}

/* ════════════════════════════════════════════════════════
   SIGNALS POLLING
═══════════════════════════════════════════════════════ */
async function sendSig(to,type,payload){
  await apiFetch('api/signals.php',{method:'POST',json:{to,type,payload}});
}

// Buffer ICE candidates ricevuti prima che pc sia pronto
let iceBuf=[];

async function pollSigs(){
  try{
    const sigs=await apiFetch('api/signals.php?after='+lastSigId);
    if(!Array.isArray(sigs))return;
    for(const s of sigs){
      if(+s.id>lastSigId)lastSigId=+s.id;
      const p=JSON.parse(s.payload);
      switch(s.type){
        case 'call-request':
          if(callState==='idle'){
            incomingPeer={id:+s.from_user_id,username:s.from_username};
            callState='ringing';
            document.getElementById('caller-lbl').textContent=s.from_username;
            document.getElementById('call-overlay').classList.add('open');
          }else{
            await sendSig(+s.from_user_id,'call-rejected',{reason:'busy'});
          }
          break;
        case 'call-accepted':
          if(callState==='calling') await onCallAccepted();
          break;
        case 'call-rejected':
          if(callState==='calling'){cleanupCall();alert(callPeer?.username+' ha rifiutato la chiamata.');}
          break;
        case 'call-end':
          if(callState!=='idle') cleanupCall();
          break;
        case 'offer':
          await onOffer(p.sdp);
          // Svuota buffer ICE
          for(const c of iceBuf) await onIce(c);iceBuf=[];
          break;
        case 'answer':
          await onAnswer(p.sdp);
          for(const c of iceBuf) await onIce(c);iceBuf=[];
          break;
        case 'ice':
          if(pc&&pc.remoteDescription) await onIce(p.candidate);
          else iceBuf.push(p.candidate);
          break;
      }
    }
  }catch(e){}
}

/* ════════════════════════════════════════════════════════
   API HELPER
═══════════════════════════════════════════════════════ */
async function apiFetch(url,opts={}){
  const fetchOpts={method:opts.method||'GET'};
  if(opts.json){fetchOpts.headers={'Content-Type':'application/json'};fetchOpts.body=JSON.stringify(opts.json);}
  else if(opts.body){fetchOpts.body=opts.body;}
  const r=await fetch(url,fetchOpts);
  if(opts.noJson){return r.json();}
  return r.json();
}

/* ════════════════════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════════════════ */
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function relTime(ts){
  const d=new Date(ts.includes('T')?ts:ts.replace(' ','T')+'Z');
  const diff=Math.floor((Date.now()-d)/1000);
  if(diff<60)return 'adesso';if(diff<3600)return Math.floor(diff/60)+'m fa';
  if(diff<86400)return Math.floor(diff/3600)+'h fa';return d.toLocaleDateString('it-IT');
}

/* ════════════════════════════════════════════════════════
   PING
═══════════════════════════════════════════════════════ */
async function ping(){await apiFetch('api/ping.php',{method:'POST'});}

/* ════════════════════════════════════════════════════════
   BOOT
═══════════════════════════════════════════════════════ */
pollMsgs();pollUsers();pollGroupsSide();ping();
setInterval(pollMsgs,  2000);
setInterval(pollSigs,  1200);
setInterval(pollUsers, 8000);
setInterval(pollGroupsSide,15000);
setInterval(()=>{if(mapReady)loadPins();},15000);
setInterval(ping,30000);
</script>
</body>
</html>
