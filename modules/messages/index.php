<?php
require_once '../../includes/check_auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkAuth(); 
$user = getUser();
$pdo = getDB();
$uid = (int)$user['id'];

// Récupérer tous les utilisateurs et le dernier message échangé (le cas échéant)
$stmt = $pdo->prepare("
  SELECT 
      u.id as contact_id, u.nom, u.prenom, u.role, u.actif,
      (
          SELECT contenu FROM messages 
          WHERE (expediteur_id = ? AND destinataire_id = u.id) OR (expediteur_id = u.id AND destinataire_id = ?)
          ORDER BY created_at DESC LIMIT 1
      ) as last_msg,
      (
          SELECT expediteur_id FROM messages 
          WHERE (expediteur_id = ? AND destinataire_id = u.id) OR (expediteur_id = u.id AND destinataire_id = ?)
          ORDER BY created_at DESC LIMIT 1
      ) as last_msg_exp,
      (
          SELECT created_at FROM messages 
          WHERE (expediteur_id = ? AND destinataire_id = u.id) OR (expediteur_id = u.id AND destinataire_id = ?)
          ORDER BY created_at DESC LIMIT 1
      ) as last_msg_time,
      (
          SELECT COUNT(*) FROM messages 
          WHERE expediteur_id = u.id AND destinataire_id = ? AND lu = 0
      ) as unread_count
  FROM utilisateurs u
  WHERE u.id != ? AND u.actif = 1
  ORDER BY last_msg_time DESC, u.prenom ASC
");
$stmt->execute([$uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid]);
$contactsList = $stmt->fetchAll();

$roleColors = ['admin'=>'#7c3aed','medecin'=>'#0891b2','patient'=>'#2563eb','laborantin'=>'#059669','caissier'=>'#d97706'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KLINIK — Messagerie</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <style>
    .chat-layout { display:flex;gap:20px;height:calc(100vh - 160px);min-height:500px;margin-bottom:20px; }
    
    /* Colonne gauche */
    .chat-sidebar { width:320px;background:#fff;border-radius:14px;border:1.5px solid #eef0f6;display:flex;flex-direction:column;box-shadow:0 2px 10px rgba(26,58,110,.05);flex-shrink:0; }
    .cs-header { padding:18px 20px;border-bottom:1px solid #eef0f6; }
    .cs-header h3 { font-family:"Oswald",sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center; }
    .cs-search { position:relative; }
    .cs-search .material-icons { position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px;color:#9ca3af; }
    .cs-search input { width:100%;padding:9px 12px 9px 36px;border:1.5px solid #eef0f6;border-radius:8px;font-size:.85rem;background:#f8f9fc;outline:none; }
    .cs-list { flex:1;overflow-y:auto; }
    
    .conv-item { display:flex;gap:12px;padding:14px 20px;border-bottom:1px solid #f5f7fb;cursor:pointer;transition:all .15s; }
    .conv-item:hover { background:#f8faff; }
    .conv-item.active { background:var(--blue-light);border-left:3px solid var(--blue-bright);padding-left:17px; }
    .conv-avatar { width:42px;height:42px;border-radius:50%;background:#1e3a5f;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;flex-shrink:0;font-family:'Oswald',sans-serif;position:relative; }
    .conv-role-dot { position:absolute;bottom:0;right:0;width:12px;height:12px;border-radius:50%;border:2px solid #fff; }
    .conv-content { flex:1;min-width:0; }
    .conv-top { display:flex;justify-content:space-between;align-items:center;margin-bottom:2px; }
    .conv-name { font-size:.9rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .conv-time { font-size:.7rem;color:var(--muted); }
    .conv-bottom { display:flex;justify-content:space-between;align-items:center; }
    .conv-msg { font-size:.82rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .conv-msg.unread { font-weight:700;color:var(--text); }
    .unread-badge { background:var(--danger);color:#fff;font-size:.65rem;font-weight:700;padding:2px 6px;border-radius:10px; }

    /* Colonne droite */
    .chat-main { flex:1;background:#fff;border-radius:14px;border:1.5px solid #eef0f6;display:flex;flex-direction:column;box-shadow:0 2px 10px rgba(26,58,110,.05);position:relative; }
    .cm-header { padding:16px 24px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;gap:14px;background:#fefefe;border-radius:14px 14px 0 0; }
    .cm-name { font-family:"Oswald",sans-serif;font-size:1.1rem;color:var(--blue); }
    .cm-role { font-size:.75rem;color:var(--muted);background:#f1f5f9;padding:2px 8px;border-radius:4px;text-transform:uppercase;font-weight:600; }
    
    .cm-body { flex:1;padding:24px;overflow-y:auto;background:#f8f9fc;display:flex;flex-direction:column;gap:16px; }
    .msg-bubble { max-width:70%;padding:12px 16px;border-radius:14px;font-size:.9rem;line-height:1.4;position:relative; }
    .msg-received { align-self:flex-start;background:#fff;border:1px solid #eef0f6;color:var(--text);border-bottom-left-radius:4px;box-shadow:0 2px 4px rgba(0,0,0,.02); }
    .msg-sent { align-self:flex-end;background:var(--blue-bright);color:#fff;border-bottom-right-radius:4px;box-shadow:0 2px 4px rgba(37,99,235,.15); }
    .msg-time { font-size:.65rem;margin-top:6px;opacity:.7;text-align:right; }
    
    .cm-footer { padding:16px 24px;border-top:1px solid #eef0f6;background:#fff;border-radius:0 0 14px 14px;display:flex;gap:12px;align-items:center; }
    .cm-input { flex:1;padding:12px 16px;border:1.5px solid #eef0f6;border-radius:24px;font-size:.9rem;outline:none;background:#f8f9fc;transition:border .2s; }
    .cm-input:focus { border-color:var(--blue-bright);background:#fff; }
    .cm-send { width:44px;height:44px;border-radius:50%;background:var(--blue-bright);color:#fff;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:transform .15s, background .15s; }
    .cm-send:hover { transform:scale(1.05);background:var(--blue); }
    .cm-send .material-icons { font-size:20px;margin-left:2px; }
    
    .empty-chat { flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);background:#f8f9fc;border-radius:14px; }
    .empty-chat .material-icons { font-size:64px;color:#cbd5e1;margin-bottom:16px; }

    /* Modal Nouveau Message */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200;}
    .modal-overlay.open{display:flex;}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:400px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease;}
    .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .modal-header h3{font-family:"Oswald",sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase;}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted);}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  </style>
</head>
<body>

<div class="main-wrapper" style="margin-left: 0; padding: 0;">
  <header class="topbar">
    <a class="topbar-logo" href="../../<?= $user['role'] ?>/dashboard.php">
      <img src="../../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'">
      <span class="logo-name">KLINIK</span>
    </a>
    <div class="topbar-left">
      <a href="../../<?= $user['role'] ?>/dashboard.php" class="btn-outline" style="padding:6px 12px;display:flex;align-items:center;gap:6px">
        <span class="material-icons" style="font-size:16px">arrow_back</span> Retour
      </a>
    </div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user">
        <div class="topbar-avatar" id="topbarAvatar"><?= strtoupper($user['prenom'][0].$user['nom'][0]) ?></div>
        <div class="topbar-user-info">
          <div class="topbar-user-name" id="topbarUserName"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></div>
          <div class="topbar-user-role"><?= ucfirst($user['role']) ?></div>
        </div>
      </div>
    </div>
  </header>

  <main class="page-content" style="max-width: 1200px; margin: 0 auto; padding-top: 100px;">
    
    <div class="chat-layout">
      <!-- Colonne Gauche -->
      <div class="chat-sidebar">
        <div class="cs-header">
          <h3 style="margin-bottom:0;">Membres</h3>
        </div>
        <div style="padding:12px 20px; border-bottom:1px solid #eef0f6;">
          <div class="cs-search">
            <span class="material-icons">search</span>
            <input type="text" id="searchConv" placeholder="Chercher quelqu'un..." onkeyup="filterConversations()">
          </div>
        </div>
        <div class="cs-list" id="convList">
          <?php if(empty($contactsList)): ?>
            <div style="padding:20px;text-align:center;color:var(--muted);font-size:.85rem">Aucun contact trouvé</div>
          <?php else: foreach($contactsList as $c): 
             $rc = $roleColors[$c['role']] ?? '#94a3b8';
             $isMe = ($c['last_msg_exp'] == $uid);
             $unread = ($c['unread_count'] > 0);
          ?>
          <div class="conv-item" data-id="<?= $c['contact_id'] ?>" data-name="<?= strtolower($c['prenom'].' '.$c['nom'].' '.$c['role']) ?>" onclick="openChat(<?= $c['contact_id'] ?>, '<?= htmlspecialchars($c['prenom'].' '.$c['nom'], ENT_QUOTES) ?>', '<?= $c['role'] ?>', '<?= $rc ?>')">
            <div class="conv-avatar" style="background:<?= $rc ?>20;color:<?= $rc ?>">
              <?= strtoupper($c['prenom'][0].$c['nom'][0]) ?>
            </div>
            <div class="conv-content">
              <div class="conv-top">
                <div class="conv-name"><?= htmlspecialchars($c['prenom'].' '.$c['nom']) ?></div>
                <div class="conv-time"><?= $c['last_msg_time'] ? date('d/m', strtotime($c['last_msg_time'])) : '' ?></div>
              </div>
              <div class="conv-bottom">
                <div class="conv-msg <?= $unread ? 'unread' : '' ?>">
                  <?php if($c['last_msg']): ?>
                    <?= $isMe ? 'Vous: ' : '' ?><?= htmlspecialchars($c['last_msg']) ?>
                  <?php else: ?>
                    <span style="color:#cbd5e1;font-style:italic">Nouvelle discussion</span>
                  <?php endif; ?>
                </div>
                <?php if($unread): ?>
                  <div class="unread-badge"><?= $c['unread_count'] ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Colonne Droite -->
      <div class="chat-main" id="chatMain">
        <div class="empty-chat">
          <span class="material-icons">chat</span>
          <h3>Sélectionnez une conversation</h3>
          <p>Ou commencez une nouvelle discussion</p>
        </div>
      </div>
    </div>
  </main>
</div>



<script src="../../assets/js/klinik.js"></script>
<script>
let currentContactId = null;
let chatInterval = null;

function filterConversations() {
  const q = document.getElementById('searchConv').value.toLowerCase();
  document.querySelectorAll('.conv-item').forEach(el => {
    if (el.dataset.name.includes(q)) el.style.display = 'flex';
    else el.style.display = 'none';
  });
}

function openChat(contactId, name, role, color) {
  currentContactId = contactId;
  document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
  const activeEl = document.querySelector(`.conv-item[data-id="${contactId}"]`);
  if(activeEl) {
    activeEl.classList.add('active');
    const badge = activeEl.querySelector('.unread-badge');
    if(badge) badge.remove();
    const msg = activeEl.querySelector('.conv-msg');
    if(msg) msg.classList.remove('unread');
  }

  const chatMain = document.getElementById('chatMain');
  chatMain.innerHTML = `
    <div class="cm-header">
      <div class="conv-avatar" style="width:38px;height:38px;font-size:.8rem">${name.charAt(0).toUpperCase()}
        <div class="conv-role-dot" style="background:${color};width:10px;height:10px"></div>
      </div>
      <div>
        <div class="cm-name">${name}</div>
        <div class="cm-role">${role}</div>
      </div>
    </div>
    <div class="cm-body" id="chatMessages">
      <div style="text-align:center;color:#9ca3af;font-size:.85rem;margin:auto">Chargement...</div>
    </div>
    <div class="cm-footer">
      <input type="text" id="msgInput" class="cm-input" placeholder="Tapez un message..." onkeypress="if(event.key==='Enter') sendMessage()">
      <button class="cm-send" onclick="sendMessage()"><span class="material-icons">send</span></button>
    </div>
  `;

  loadMessages();
  if(chatInterval) clearInterval(chatInterval);
  chatInterval = setInterval(loadMessages, 3000); // Polling rapide pour le chat actif
}

async function loadMessages() {
  if(!currentContactId) return;
  try {
    const res = await fetch('../../api/messages.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'thread', contact_id: currentContactId})
    });
    const d = await res.json();
    if(d.success) {
      const container = document.getElementById('chatMessages');
      if(!container) return;
      
      let html = '';
      d.messages.reverse().forEach(m => {
        const isMe = (m.expediteur_id != currentContactId);
        const time = new Date(m.created_at).toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
        html += `
          <div class="msg-bubble ${isMe ? 'msg-sent' : 'msg-received'}">
            ${m.contenu.replace(/\n/g, '<br>')}
            <div class="msg-time">${time}</div>
          </div>
        `;
      });
      
      const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
      container.innerHTML = html || '<div style="text-align:center;color:#9ca3af;font-size:.85rem;margin:auto">Aucun message</div>';
      
      // Marquer comme lu
      await fetch('../../api/messages.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'markThreadRead', contact_id: currentContactId})
      });

      if(wasAtBottom) container.scrollTop = container.scrollHeight;
    }
  } catch(e) {}
}

async function sendMessage() {
  const input = document.getElementById('msgInput');
  const text = input.value.trim();
  if(!text || !currentContactId) return;
  
  input.value = ''; // clear immediately
  try {
    await fetch('../../api/messages.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'send', destinataire_id: currentContactId, contenu: text})
    });
    // Rafraichir le chat actif
    loadMessages();
  } catch(e) {}
}
</script>
</body>
</html>
