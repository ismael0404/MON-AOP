<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(); // All roles allowed
$user = getUser();
$pdo = getDB();

$uid = (int)$user['id'];
$search = sanitize($_GET['search'] ?? '');
$typeFilter = sanitize($_GET['type'] ?? '');
$statusFilter = sanitize($_GET['statut'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = "WHERE utilisateur_id = ?";
$params = [$uid];

if ($search) {
    $where .= " AND (titre LIKE ? OR message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($typeFilter) {
    $where .= " AND type = ?";
    $params[] = $typeFilter;
}
if ($statusFilter !== '') {
    $where .= " AND lue = ?";
    $params[] = $statusFilter === 'lue' ? 1 : 0;
}

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM notifications $where");
$stmtC->execute($params);
$total = (int)$stmtC->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Stats
$nbNonLues = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE utilisateur_id=$uid AND lue=0")->fetchColumn();
$nbTotal = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE utilisateur_id=$uid")->fetchColumn();
$nbAujourdhui = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE utilisateur_id=$uid AND DATE(created_at)=CURDATE()")->fetchColumn();

// Type Icons
$typeIcons = ['info'=>['info','#2563eb'],'success'=>['check_circle','#059669'],'warning'=>['warning','#d97706'],'danger'=>['error','#dc2626']];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KLINIK — Notifications</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .card { background:#fff;border-radius:14px;padding:22px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:20px; }
    .stats-row { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:22px; }
    .stat-card { background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);transition:transform .2s; }
    .stat-card:hover { transform:translateY(-2px); }
    .stat-icon { width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .stat-icon .material-icons { font-size:21px;color:#fff; }
    .stat-value { font-family:"Oswald",sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1; }
    .stat-label { font-size:.7rem;color:var(--muted);margin-top:3px; }
    
    .table-card { background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:20px; }
    .table-header { padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between; }
    .table-header h3 { font-family:"Oswald",sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase; }
    
    .notif-row { display:flex;gap:16px;padding:16px 20px;border-bottom:1px solid #eef0f6;transition:background .2s;align-items:center; }
    .notif-row:hover { background:#f8faff; }
    .notif-row.unread { background:#f0f5ff; }
    .notif-row:last-child { border-bottom:none; }
    
    .n-icon { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .n-content { flex:1;min-width:0; }
    .n-title { font-size:.9rem;font-weight:600;color:var(--text);display:flex;align-items:center;gap:8px; }
    .n-badge { font-size:.65rem;padding:2px 6px;border-radius:4px;font-weight:700;text-transform:uppercase; }
    .n-msg { font-size:.82rem;color:var(--muted);margin-top:4px; }
    .n-time { font-size:.75rem;color:var(--muted);white-space:nowrap; }
    .n-actions { display:flex;gap:8px; }
    
    .action-btn { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:none;cursor:pointer;transition:all .18s; }
    .action-btn .material-icons { font-size:18px; }
    .btn-mark { background:#dbeafe;color:#1d4ed8; } .btn-mark:hover { background:#1d4ed8;color:#fff; }
    .btn-del { background:#fee2e2;color:#991b1b; } .btn-del:hover { background:#991b1b;color:#fff; }
    
    .filter-bar { display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:12px 16px;align-items:center; }
    
    .pagination { display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted); }
    .page-btns { display:flex;gap:5px; }
    .page-btn { padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s; }
    .page-btn:hover { border-color:var(--blue-bright);color:var(--blue-bright); }
    .page-btn.active { background:var(--blue-bright);border-color:var(--blue-bright);color:#fff; }
  </style>
</head>
<body>

<div class="main-wrapper" style="margin-left: 0; padding: 0;">
  <header class="topbar">
    <a class="topbar-logo" href="../<?= $user['role'] ?>/dashboard.php">
      <img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'">
      <span class="logo-name">KLINIK</span>
    </a>
    <div class="topbar-left">
      <a href="../<?= $user['role'] ?>/dashboard.php" class="btn-outline" style="padding:6px 12px;display:flex;align-items:center;gap:6px">
        <span class="material-icons" style="font-size:16px">arrow_back</span> Retour
      </a>
    </div>
    <div class="topbar-search">
      <span class="material-icons">search</span>
      <input type="text" placeholder="Rechercher...">
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

  <main class="page-content" style="max-width: 1000px; margin: 0 auto; padding-top: 30px;">
    <div class="page-header" style="margin-bottom:24px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <h1>Notifications</h1>
        <p>Gérez vos alertes et activités récentes</p>
      </div>
      <div>
        <button class="btn-primary" onclick="KlinikNotifications._markAllRead();setTimeout(()=>location.reload(),500)">
          <span class="material-icons">done_all</span> Tout marquer lu
        </button>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">notifications_active</span></div><div><div class="stat-value"><?= $nbNonLues ?></div><div class="stat-label">Non lues</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">today</span></div><div><div class="stat-value"><?= $nbAujourdhui ?></div><div class="stat-label">Aujourd'hui</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--muted)"><span class="material-icons">history</span></div><div><div class="stat-value"><?= $nbTotal ?></div><div class="stat-label">Total historique</div></div></div>
    </div>

    <div class="filter-bar">
      <div style="position:relative;flex:1;min-width:180px">
        <span class="material-icons" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:17px;color:#9ca3af">search</span>
        <input type="text" id="searchInput" class="form-control" style="padding-left:32px" placeholder="Rechercher une notification..." value="<?= htmlspecialchars($search) ?>" onkeyup="debounceSearch()">
      </div>
      <select id="typeSelect" class="form-control" style="width:160px" onchange="applyFilters()">
        <option value="">Tous les types</option>
        <option value="info" <?= $typeFilter==='info'?'selected':'' ?>>Info</option>
        <option value="success" <?= $typeFilter==='success'?'selected':'' ?>>Succès</option>
        <option value="warning" <?= $typeFilter==='warning'?'selected':'' ?>>Avertissement</option>
        <option value="danger" <?= $typeFilter==='danger'?'selected':'' ?>>Important</option>
      </select>
      <select id="statutSelect" class="form-control" style="width:160px" onchange="applyFilters()">
        <option value="">Tous statuts</option>
        <option value="non_lue" <?= $statusFilter==='non_lue'?'selected':'' ?>>Non lues</option>
        <option value="lue" <?= $statusFilter==='lue'?'selected':'' ?>>Lues</option>
      </select>
      <?php if($search || $typeFilter || $statusFilter !== ''): ?>
        <a href="index.php" class="btn-outline" style="padding:8px 14px;font-size:.82rem;"><span class="material-icons" style="font-size:14px">close</span> Effacer</a>
      <?php endif; ?>
    </div>

    <div class="table-card">
      <div class="table-header">
        <h3>Historique des notifications</h3>
        <span style="font-size:.8rem;color:var(--muted)"><?= $total ?> notification(s)</span>
      </div>
      <div>
        <?php if(empty($notifications)): ?>
          <div style="text-align:center;padding:40px;color:var(--muted)">Aucune notification trouvée</div>
        <?php else: foreach($notifications as $n): 
            $ico = $typeIcons[$n['type']] ?? $typeIcons['info'];
        ?>
          <div class="notif-row <?= $n['lue'] ? '' : 'unread' ?>" id="notif-<?= $n['id'] ?>">
            <div class="n-icon" style="background:<?= $ico[1] ?>15;color:<?= $ico[1] ?>">
              <span class="material-icons"><?= $ico[0] ?></span>
            </div>
            <div class="n-content">
              <div class="n-title">
                <?= htmlspecialchars($n['titre']) ?>
                <?php if(!$n['lue']): ?><span class="n-badge" style="background:var(--danger);color:#fff">Nouveau</span><?php endif; ?>
              </div>
              <div class="n-msg"><?= htmlspecialchars($n['message']) ?></div>
            </div>
            <div class="n-time">
              <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?>
            </div>
            <div class="n-actions">
              <?php if(!$n['lue']): ?>
              <button class="action-btn btn-mark" title="Marquer lu" onclick="markLue(<?= $n['id'] ?>)">
                <span class="material-icons">check</span>
              </button>
              <?php endif; ?>
              <button class="action-btn btn-del" title="Supprimer" onclick="deleteNotif(<?= $n['id'] ?>)">
                <span class="material-icons">delete</span>
              </button>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <div class="pagination">
        <span>Affichage <?= min($offset+1, $total) ?>–<?= min($offset+$perPage, $total) ?> sur <?= $total ?></span>
        <div class="page-btns">
          <?php if($page > 1): ?><a class="page-btn" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>&statut=<?= urlencode($statusFilter) ?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif; ?>
          <?php for($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
            <a class="page-btn <?= $p===$page?'active':'' ?>" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>&statut=<?= urlencode($statusFilter) ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if($page < $totalPages): ?><a class="page-btn" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>&statut=<?= urlencode($statusFilter) ?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/klinik.js"></script>
<script>
let st; function debounceSearch(){ clearTimeout(st); st=setTimeout(applyFilters, 400); }
function applyFilters(){
  window.location.href='index.php?search='+encodeURIComponent(document.getElementById('searchInput').value)+
    '&type='+encodeURIComponent(document.getElementById('typeSelect').value)+
    '&statut='+encodeURIComponent(document.getElementById('statutSelect').value)+'&page=1';
}

function markLue(id) {
  fetch('../api/notifications.php', {
    method: 'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'markRead', id:id})
  }).then(r=>r.json()).then(d=>{
    if(d.success){
      const row = document.getElementById('notif-'+id);
      row.classList.remove('unread');
      const btn = row.querySelector('.btn-mark');
      if(btn) btn.remove();
      const badge = row.querySelector('.n-badge');
      if(badge) badge.remove();
    }
  });
}

function deleteNotif(id) {
  if(!confirm("Supprimer cette notification ?")) return;
  // We need to add deleteAction to api/notifications.php
  fetch('../api/notifications.php', {
    method: 'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'delete', id:id})
  }).then(r=>r.json()).then(d=>{
    if(d.success){ document.getElementById('notif-'+id).remove(); }
  });
}
</script>
</body>
</html>
