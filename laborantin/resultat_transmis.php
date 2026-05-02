<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['laborantin']);
$user = getUser(); $pdo = getDB();

$search = sanitize($_GET['search'] ?? '');
$page   = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;

$where  = "WHERE e.statut='transmis'"; $params=[];
if($search){ $where.=" AND (e.type_examen LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)"; $params[]="%$search%";$params[]="%$search%";$params[]="%$search%"; }

$stmtC=$pdo->prepare("SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id JOIN patients p ON c.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id $where");
$stmtC->execute($params); $total=(int)$stmtC->fetchColumn(); $totalPages=ceil($total/$perPage);

$stmt=$pdo->prepare("
    SELECT e.*,
           u.nom as p_nom,u.prenom as p_prenom,
           um.nom as med_nom,um.prenom as med_prenom,
           ul.nom as lab_nom,ul.prenom as lab_prenom
    FROM examens e
    JOIN consultations c ON e.consultation_id=c.id
    JOIN patients p ON c.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id
    JOIN medecins m ON c.medecin_id=m.id JOIN utilisateurs um ON m.utilisateur_id=um.id
    LEFT JOIN utilisateurs ul ON e.laborantin_id=ul.id
    $where ORDER BY e.date_resultat DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $resultats=$stmt->fetchAll();

$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut='transmis'");$nbTotal=(int)$s->fetchColumn();
$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut='transmis' AND DATE(date_resultat)=CURDATE()");$nbAuj=(int)$s->fetchColumn();
$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut='transmis' AND MONTH(date_resultat)=MONTH(NOW())");$nbMois=(int)$s->fetchColumn();
$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut IN('en_attente','en_cours')");$nbRestants=(int)$s->fetchColumn();
$nbUrgents=(int)$pdo->query("SELECT COUNT(*) FROM examens WHERE statut='en_attente' AND priorite='urgente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Résultats Transmis</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}.stat-card:nth-child(4){animation-delay:.24s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .search-wrap{position:relative;margin-bottom:16px}
    .search-wrap .material-icons{position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:17px;color:#9ca3af}
    .search-wrap input{width:100%;padding:9px 12px 9px 32px;border:1.5px solid #eef0f6;border-radius:8px;font-size:.86rem;outline:none;background:#f8f9fc}
    .search-wrap input:focus{border-color:var(--blue-bright);background:#fff}
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted)}
    .page-btns{display:flex;gap:5px}.page-btn{padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}.page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .resultat-box{background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:14px;font-size:.86rem;color:#0c4a6e;line-height:1.7;white-space:pre-wrap;margin:12px 0}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
    .info-block{background:#f8f9fc;border-radius:8px;padding:10px}
    .ib-label{font-size:.67rem;font-weight:700;text-transform:uppercase;color:var(--muted)}
    .ib-value{font-size:.86rem;color:var(--text);margin-top:2px;font-weight:600}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-laborantin">Laborantin</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Laboratoire</div>
    <a class="nav-item" href="examens-en-attente.php"><span class="material-icons">pending_actions</span> Examens en attente</a>
    <a class="nav-item" href="saisir-resultats.php"><span class="material-icons">science</span> Saisir résultats</a>
    <a class="nav-item active" href="resultat_transmis.php"><span class="material-icons">check_circle</span> Résultats transmis</a>
  
    <div class="nav-section-title">Communication</div>
    <a class="nav-item" href="../notifications/index.php">
      <span class="material-icons">notifications</span> Notifications
    </a>
    <a class="nav-item" href="../modules/messages/index.php">
      <span class="material-icons">chat</span> Messagerie
    </a>
  </nav>
  <div class="sidebar-footer"><a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a></div>
</aside>
<div class="main-wrapper">
  <header class="topbar">
    <a class="topbar-logo" href="dashboard.php"><img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'"><span class="logo-name">KLINIK</span></a>
    <div class="topbar-left"><button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button></div>
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher examen, patient..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbUrgents>0):?><span class="notif-badge"><?=$nbUrgents?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">LB</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Laborantin</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div><h1>Résultats transmis</h1><p>Historique des résultats envoyés aux médecins</p></div>
      <?php if($nbRestants>0): ?>
      <a href="examens-en-attente.php" class="btn-primary">
        <span class="material-icons">pending_actions</span> <?=$nbRestants?> en attente
      </a>
      <?php endif; ?>
    </div>

    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">check_circle</span></div><div><div class="stat-value"><?=$nbTotal?></div><div class="stat-label">Total transmis</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">today</span></div><div><div class="stat-value"><?=$nbAuj?></div><div class="stat-label">Aujourd'hui</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#7c3aed"><span class="material-icons">calendar_month</span></div><div><div class="stat-value"><?=$nbMois?></div><div class="stat-label">Ce mois</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?=$nbRestants?></div><div class="stat-label">Restants à traiter</div></div></div>
    </div>

    <div class="search-wrap">
      <span class="material-icons">search</span>
      <input type="text" id="searchInput" placeholder="Rechercher par examen ou patient..." value="<?=htmlspecialchars($search)?>" onkeyup="debounceSearch()">
    </div>

    <div class="table-card">
      <div class="table-header"><h3>Résultats transmis</h3><span style="font-size:.8rem;color:var(--muted)"><?=$total?> résultat(s)</span></div>
      <div style="overflow-x:auto"><table class="klinik-table">
        <thead><tr><th>Type d'examen</th><th>Patient</th><th>Médecin</th><th>Priorité</th><th>Demandé</th><th>Transmis</th><th>Laborantin</th><th>Action</th></tr></thead>
        <tbody>
          <?php if(empty($resultats)): ?>
          <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--muted)">Aucun résultat trouvé</td></tr>
          <?php else: foreach($resultats as $r): ?>
          <tr>
            <td><strong><?=htmlspecialchars($r['type_examen'])?></strong></td>
            <td><?=htmlspecialchars($r['p_prenom'].' '.$r['p_nom'])?></td>
            <td style="font-size:.82rem">Dr. <?=htmlspecialchars($r['med_prenom'].' '.$r['med_nom'])?></td>
            <td><span style="padding:2px 8px;border-radius:10px;font-size:.7rem;font-weight:700;background:<?=$r['priorite']==='urgente'?'#fee2e2':'#f0f4fa'?>;color:<?=$r['priorite']==='urgente'?'#991b1b':'#6b7280'?>"><?=ucfirst($r['priorite'])?></span></td>
            <td style="font-size:.8rem;color:var(--muted)"><?=date('d/m/Y',strtotime($r['date_demande']))?></td>
            <td style="font-size:.8rem;color:var(--success);font-weight:600"><?=$r['date_resultat']?date('d/m/Y H:i',strtotime($r['date_resultat'])):'—'?></td>
            <td style="font-size:.8rem;color:var(--muted)"><?=$r['lab_nom']?htmlspecialchars($r['lab_prenom'].' '.$r['lab_nom']):'—'?></td>
            <td>
              <button class="btn-outline" style="padding:4px 10px;font-size:.76rem" onclick="voirResultat(<?=json_encode($r)?>)">
                <span class="material-icons" style="font-size:14px">visibility</span> Voir
              </button>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table></div>
      <div class="pagination">
        <span>Affichage <?=min($offset+1,$total)?>–<?=min($offset+$perPage,$total)?> sur <?=$total?></span>
        <div class="page-btns">
          <?php if($page>1):?><a class="page-btn" href="?page=<?=$page-1?>&search=<?=urlencode($search)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif;?>
          <?php for($pg=max(1,$page-2);$pg<=min($totalPages,$page+2);$pg++):?><a class="page-btn <?=$pg===$page?'active':''?>" href="?page=<?=$pg?>&search=<?=urlencode($search)?>"><?=$pg?></a><?php endfor;?>
          <?php if($page<$totalPages):?><a class="page-btn" href="?page=<?=$page+1?>&search=<?=urlencode($search)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif;?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal résultat détail -->
<div class="modal-overlay" id="modalRes">
  <div class="modal">
    <div class="modal-header">
      <h3 id="resTitle">Résultat</h3>
      <button class="modal-close" onclick="closeModal('modalRes')"><span class="material-icons">close</span></button>
    </div>
    <div class="info-grid">
      <div class="info-block"><div class="ib-label">Patient</div><div class="ib-value" id="resPatient">—</div></div>
      <div class="info-block"><div class="ib-label">Médecin</div><div class="ib-value" id="resMedecin">—</div></div>
      <div class="info-block"><div class="ib-label">Demandé le</div><div class="ib-value" id="resDemande">—</div></div>
      <div class="info-block"><div class="ib-label">Transmis le</div><div class="ib-value" id="resDate">—</div></div>
    </div>
    <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Résultat de l'examen</div>
    <div class="resultat-box" id="resContenu"></div>
    <button class="btn-outline" onclick="closeModal('modalRes')" style="width:100%;margin-top:8px">Fermer</button>
  </div>
</div>

<script src="../assets/js/klinik.js"></script>
<script>
const user={nom:'<?=htmlspecialchars($user["nom"])?>',prenom:'<?=htmlspecialchars($user["prenom"])?>',email:'<?=htmlspecialchars($user["email"])?>',role:'<?=$user["role"]?>'};
KlinikUI.fillUserInfo(user);KlinikUI.initSidebar();
document.getElementById('topbarUserName').textContent=user.prenom+' '+user.nom;
document.getElementById('topbarAvatar').textContent=(user.prenom[0]+user.nom[0]).toUpperCase();
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')}));
let st;function debounceSearch(){clearTimeout(st);st=setTimeout(()=>window.location.href='resultat_transmis.php?search='+encodeURIComponent(document.getElementById('searchInput').value)+'&page=1',400);}
function voirResultat(r){
  document.getElementById('resTitle').textContent=r.type_examen;
  document.getElementById('resPatient').textContent=r.p_prenom+' '+r.p_nom;
  document.getElementById('resMedecin').textContent='Dr. '+r.med_prenom+' '+r.med_nom;
  document.getElementById('resDemande').textContent=new Date(r.date_demande).toLocaleDateString('fr-FR');
  document.getElementById('resDate').textContent=r.date_resultat?new Date(r.date_resultat).toLocaleString('fr-FR'):'—';
  document.getElementById('resContenu').textContent=r.resultat||'—';
  openModal('modalRes');
}
</script>
</body></html>
