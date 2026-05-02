<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['admin']);
$user = getUser();
$pdo  = getDB();

$search     = sanitize($_GET['search']  ?? '');
$statFilter = sanitize($_GET['statut']  ?? '');
$dateFilter = sanitize($_GET['date']    ?? '');
$page = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;
$where="WHERE 1=1"; $params=[];
if($search){$where.=" AND (up.nom LIKE ? OR up.prenom LIKE ? OR um.nom LIKE ? OR um.prenom LIKE ?)";$params[]="%$search%";$params[]="%$search%";$params[]="%$search%";$params[]="%$search%";}
if($statFilter){$where.=" AND r.statut=?";$params[]=$statFilter;}
if($dateFilter){$where.=" AND DATE(r.date_rdv)=?";$params[]=$dateFilter;}
$stmtC=$pdo->prepare("SELECT COUNT(*) FROM rendez_vous r JOIN patients pt ON r.patient_id=pt.id JOIN utilisateurs up ON pt.utilisateur_id=up.id JOIN medecins m ON r.medecin_id=m.id JOIN utilisateurs um ON m.utilisateur_id=um.id $where");
$stmtC->execute($params); $total=(int)$stmtC->fetchColumn(); $totalPages=ceil($total/$perPage);
$stmt=$pdo->prepare("SELECT r.*,up.nom as p_nom,up.prenom as p_prenom,um.nom as m_nom,um.prenom as m_prenom,m2.specialite FROM rendez_vous r JOIN patients pt ON r.patient_id=pt.id JOIN utilisateurs up ON pt.utilisateur_id=up.id JOIN medecins m ON r.medecin_id=m.id JOIN utilisateurs um ON m.utilisateur_id=um.id LEFT JOIN medecins m2 ON r.medecin_id=m2.id $where ORDER BY r.date_rdv DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $rdvs=$stmt->fetchAll();
$nbAuj=(int)$pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_rdv)=CURDATE()")->fetchColumn();
$nbAtt=(int)$pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut='en_attente'")->fetchColumn();
$nbConf=(int)$pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut='confirme'")->fetchColumn();
$nbAnn=(int)$pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut='annule'")->fetchColumn();
$statutColors=['en_attente'=>'status-pending','confirme'=>'status-active','termine'=>'status-done','annule'=>'status-inactive'];
$statutLabels=['en_attente'=>'En attente','confirme'=>'Confirmé','termine'=>'Terminé','annule'=>'Annulé'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KLINIK</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  
  <style>
    .card{background:#fff;border-radius:14px;padding:22px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:20px}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
    .card-header h3{font-family:"Oswald",sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase;letter-spacing:.5px}
    .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);transition:transform .2s;animation:fadeUp .5s ease both;opacity:0}
    .stat-card:hover{transform:translateY(-2px)}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}.stat-card:nth-child(4){animation-delay:.24s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:"Oswald",sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1}
    .stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
    .filter-btn{padding:6px 14px;border-radius:20px;border:1.5px solid #eef0f6;background:#fff;font-size:.8rem;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text)}
    .filter-btn:hover,.filter-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:20px}
    .table-header{padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:"Oswald",sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .action-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;border:none;cursor:pointer;transition:all .18s}
    .action-btn .material-icons{font-size:15px}
    .btn-view{background:#dbeafe;color:#1d4ed8}.btn-view:hover{background:#1d4ed8;color:#fff}
    .btn-confirm{background:#d1fae5;color:#065f46}.btn-confirm:hover{background:#065f46;color:#fff}
    .btn-cancel{background:#fee2e2;color:#991b1b}.btn-cancel:hover{background:#991b1b;color:#fff}
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted)}
    .page-btns{display:flex;gap:5px}
    .page-btn{padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}
    .page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:"Oswald",sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}
    .alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
    .alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    .info-block{background:#f8f9fc;border-radius:8px;padding:12px;margin-bottom:8px}
    .ib-label{font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--muted)}
    .ib-value{font-size:.88rem;color:var(--text);margin-top:3px;font-weight:600}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-admin">Administrateur</div>
  <div class="sidebar-user">
    <div class="user-name" id="sidebarUserName">—</div>
    <div class="user-email" id="sidebarUserEmail">—</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item" href="dashboard.php">
      <span class="material-icons">dashboard</span> Tableau de bord
    </a>
    <div class="nav-section-title">Gestion</div>
    <a class="nav-item" href="utilisateurs.php">
      <span class="material-icons">manage_accounts</span> Utilisateurs
    </a>
    <a class="nav-item" href="historique.php" class="nav-item active">
      <span class="material-icons">calendar_today</span> Historique des Rendez-vous
    </a>
    <a class="nav-item" href="dossiers_medicaux.php">
      <span class="material-icons">folder_shared</span> Dossiers médicaux
    </a>
    <a class="nav-item" href="facturation.php">
      <span class="material-icons">receipt_long</span> Facturation
    </a>
    
    <div class="nav-section-title">Communication & Finances</div>
    <a class="nav-item" href="../notifications/index.php">
      <span class="material-icons">notifications</span> Notifications
    </a>
    <a class="nav-item" href="../modules/messages/index.php">
      <span class="material-icons">chat</span> Messagerie
    </a>
    <a class="nav-item" href="../modules/paiements/index.php">
      <span class="material-icons">payments</span> Paiements
    </a>
    <div class="nav-section-title">Système</div>
    <a class="nav-item" href="rapports.php">
      <span class="material-icons">bar_chart</span> Rapports
    </a>
    <a class="nav-item" href="parametres.php">
      <span class="material-icons">settings</span> Paramètres
    </a>
  </nav>
  <div class="sidebar-footer">
    <a class="nav-item" href="../auth/logout.php">
      <span class="material-icons">logout</span> Déconnexion
    </a>
  </div>
</aside>

<div class="main-wrapper">
<header class="topbar">

    <!-- Logo -->
    <a class="topbar-logo" href="dashboard.php">
      <img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'">
      <span class="logo-name">KLINIK</span>
    </a>

    <!-- Hamburger -->
    <div class="topbar-left">
      <button class="sidebar-toggle" id="sidebarToggle">
        <span class="material-icons">menu</span>
      </button>
    </div>

    <!-- Barre de recherche centrée -->
    <div class="topbar-search">
      <span class="material-icons">search</span>
      <input type="text" placeholder="Rechercher patient, médecin, rendez-vous...">
    </div>

    <!-- Droite -->
    <div class="topbar-right">
      <!-- Notifications -->
      <div class="topbar-icon-btn">
        <span class="material-icons">notifications</span>
        <span class="notif-badge">3</span>
      </div>
      <!-- Messagerie -->
      <div class="topbar-icon-btn">
        <span class="material-icons">mail_outline</span>
      </div>
      <!-- Profil -->
      <div class="topbar-user">
        <div class="topbar-avatar" id="topbarAvatar">AD</div>
        <div class="topbar-user-info">
          <div class="topbar-user-name" id="topbarUserName">—</div>
          <div class="topbar-user-role">Administrateur</div>
        </div>
      </div>
    </div>
  </header>

<main class="page-content">

<div class="page-header" style="margin-bottom:20px">
  <h1>Historique des rendez-vous</h1>
  <p>Gestion de tous les rendez-vous de la clinique</p>
</div>
<div class="stats-row">
  <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">today</span></div><div><div class="stat-value"><?=$nbAuj?></div><div class="stat-label">Aujourd'hui</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending</span></div><div><div class="stat-value"><?=$nbAtt?></div><div class="stat-label">En attente</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">event_available</span></div><div><div class="stat-value"><?=$nbConf?></div><div class="stat-label">Confirmés</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:var(--danger)"><span class="material-icons">event_busy</span></div><div><div class="stat-value"><?=$nbAnn?></div><div class="stat-label">Annulés</div></div></div>
</div>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:12px 16px;align-items:center">
  <div style="position:relative;flex:1;min-width:180px"><span class="material-icons" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:17px;color:#9ca3af">search</span><input type="text" id="searchInput" class="form-control" style="padding-left:32px" placeholder="Patient, médecin..." value="<?=htmlspecialchars($search)?>" onkeyup="debounceSearch()"></div>
  <input type="date" id="dateInput" class="form-control" style="width:160px" value="<?=htmlspecialchars($dateFilter)?>" onchange="applyFilters()">
  <select id="statSelect" class="form-control" style="width:160px" onchange="applyFilters()">
    <option value="">Tous statuts</option>
    <option value="en_attente" <?=$statFilter==='en_attente'?'selected':''?>>En attente</option>
    <option value="confirme"   <?=$statFilter==='confirme'?'selected':''?>>Confirmé</option>
    <option value="termine"    <?=$statFilter==='termine'?'selected':''?>>Terminé</option>
    <option value="annule"     <?=$statFilter==='annule'?'selected':''?>>Annulé</option>
  </select>
  <?php if($search||$statFilter||$dateFilter):?><a href="historique.php" class="btn-outline" style="padding:8px 14px;font-size:.82rem;white-space:nowrap"><span class="material-icons" style="font-size:14px">close</span> Effacer</a><?php endif;?>
</div>
<div class="table-card">
  <div class="table-header"><h3>Rendez-vous</h3><span style="font-size:.8rem;color:var(--muted)"><?=$total?> résultat(s)</span></div>
  <div style="overflow-x:auto"><table class="klinik-table">
    <thead><tr><th>Date/Heure</th><th>Patient</th><th>Médecin</th><th>Spécialité</th><th>Motif</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if(empty($rdvs)):?><tr><td colspan="7" style="text-align:center;padding:28px;color:var(--muted)">Aucun rendez-vous trouvé</td></tr>
      <?php else: foreach($rdvs as $r):?>
      <tr>
        <td><div style="font-weight:600;font-size:.88rem"><?=date('d/m/Y',strtotime($r['date_rdv']))?></div><div style="font-size:.75rem;color:var(--muted)"><?=date('H:i',strtotime($r['date_rdv']))?></div></td>
        <td><strong><?=htmlspecialchars($r['p_prenom'].' '.$r['p_nom'])?></strong></td>
        <td>Dr. <?=htmlspecialchars($r['m_prenom'].' '.$r['m_nom'])?></td>
        <td style="font-size:.82rem;color:var(--muted)"><?=htmlspecialchars($r['specialite']??'—')?></td>
        <td style="font-size:.82rem;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['motif']??'—')?></td>
        <td><span class="status-badge <?=$statutColors[$r['statut']]?>"><?=$statutLabels[$r['statut']]?></span></td>
        <td><div style="display:flex;gap:4px">
          <?php if($r['statut']==='en_attente'):?>
          <button class="action-btn btn-confirm" onclick="updateRdv(<?=$r['id']?>,'confirme')" title="Confirmer"><span class="material-icons">check</span></button>
          <button class="action-btn btn-cancel"  onclick="updateRdv(<?=$r['id']?>,'annule')"  title="Annuler"><span class="material-icons">close</span></button>
          <?php elseif($r['statut']==='confirme'):?>
          <button class="action-btn btn-cancel"  onclick="updateRdv(<?=$r['id']?>,'annule')"  title="Annuler"><span class="material-icons">close</span></button>
          <?php else:?><span style="font-size:.75rem;color:var(--muted)">—</span><?php endif;?>
        </div></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table></div>
  <div class="pagination">
    <span>Affichage <?=min($offset+1,$total)?>–<?=min($offset+$perPage,$total)?> sur <?=$total?></span>
    <div class="page-btns">
      <?php if($page>1):?><a class="page-btn" href="?page=<?=$page-1?>&search=<?=urlencode($search)?>&statut=<?=urlencode($statFilter)?>&date=<?=urlencode($dateFilter)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif;?>
      <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++):?><a class="page-btn <?=$p===$page?'active':''?>" href="?page=<?=$p?>&search=<?=urlencode($search)?>&statut=<?=urlencode($statFilter)?>&date=<?=urlencode($dateFilter)?>"><?=$p?></a><?php endfor;?>
      <?php if($page<$totalPages):?><a class="page-btn" href="?page=<?=$page+1?>&search=<?=urlencode($search)?>&statut=<?=urlencode($statFilter)?>&date=<?=urlencode($dateFilter)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif;?>
    </div>
  </div>
</div>

</main>
</div>

<script src="../assets/js/klinik.js"></script>
<script>
const user = {
  nom:    '<?= htmlspecialchars($user["nom"]) ?>',
  prenom: '<?= htmlspecialchars($user["prenom"]) ?>',
  email:  '<?= htmlspecialchars($user["email"]) ?>',
  role:   '<?= $user["role"] ?>'
};
KlinikUI.fillUserInfo(user);
KlinikUI.initSidebar();
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); })
);

let st; function debounceSearch(){clearTimeout(st);st=setTimeout(applyFilters,400);}
function applyFilters(){window.location.href='historique.php?search='+encodeURIComponent(document.getElementById('searchInput').value)+'&statut='+encodeURIComponent(document.getElementById('statSelect').value)+'&date='+encodeURIComponent(document.getElementById('dateInput').value)+'&page=1';}
function updateRdv(id,statut){fetch('../api/rendez-vous.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'updateStatut',id,statut})}).then(r=>r.json()).then(d=>{if(d.success)location.reload();});}

</script>
</body>
</html>