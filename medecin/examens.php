<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['medecin']);
$user=getUser();$pdo=getDB();$medecinId=$user['medecin_id']??null;
$filter=$_GET['statut']??'';
$where="WHERE c.medecin_id=?";$params=[$medecinId];
if($filter){$where.=" AND e.statut=?";$params[]=$filter;}
$stmt=$pdo->prepare("SELECT e.*,u.nom,u.prenom FROM examens e JOIN consultations c ON e.consultation_id=c.id JOIN patients p ON c.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id $where ORDER BY e.date_demande DESC LIMIT 30");
$stmt->execute($params);$examens=$stmt->fetchAll();
$s=$pdo->prepare("SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.medecin_id=? AND e.statut='en_attente'");$s->execute([$medecinId]);$nbAtt=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.medecin_id=? AND e.statut='transmis'");$s->execute([$medecinId]);$nbTrans=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.medecin_id=? AND e.statut='transmis' AND DATE(e.date_resultat)=CURDATE()");$s->execute([$medecinId]);$nbNouv=(int)$s->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Examens</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css"><link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
    .filter-btn{padding:7px 16px;border-radius:20px;border:1.5px solid #eef0f6;background:#fff;font-size:.82rem;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text)}
    .filter-btn:hover,.filter-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .resultat-box{background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 12px;font-size:.82rem;color:#0369a1}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:500px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-medecin">Médecin</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Médical</div>
    <a class="nav-item" href="consultation.php"><span class="material-icons">add_circle</span> Nouvelle consultation</a>
    <a class="nav-item" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span> Mes rendez-vous</a>
    <a class="nav-item" href="dossiers.php"><span class="material-icons">folder_shared</span> Dossiers patients</a>
    <a class="nav-item active" href="examens.php"><span class="material-icons">science</span> Examens</a>
    <a class="nav-item" href="ordonnances.php"><span class="material-icons">description</span> Ordonnances</a>
  
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
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher patient, diagnostic..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbNouv>0):?><span class="notif-badge"><?=$nbNouv?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">MD</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Médecin</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Examens & Résultats</h1><p>Suivi des examens demandés</p></div>
    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?=$nbAtt?></div><div class="stat-label">En attente</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">check_circle</span></div><div><div class="stat-value"><?=$nbTrans?></div><div class="stat-label">Résultats reçus</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">new_releases</span></div><div><div class="stat-value"><?=$nbNouv?></div><div class="stat-label">Nouveaux aujourd'hui</div></div></div>
    </div>
    <div class="filter-bar">
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="examens.php">Tous</a>
      <a class="filter-btn <?= $filter==='en_attente'?'active':'' ?>" href="?statut=en_attente">En attente</a>
      <a class="filter-btn <?= $filter==='en_cours'?'active':'' ?>" href="?statut=en_cours">En cours</a>
      <a class="filter-btn <?= $filter==='transmis'?'active':'' ?>" href="?statut=transmis">Résultats reçus</a>
    </div>
    <div class="table-card">
      <div class="table-header"><h3>Liste des examens</h3><span style="font-size:.8rem;color:var(--muted)"><?=count($examens)?> examen(s)</span></div>
      <div style="overflow-x:auto"><table class="klinik-table">
        <thead><tr><th>Type d'examen</th><th>Patient</th><th>Priorité</th><th>Demandé le</th><th>Statut</th><th>Résultat</th></tr></thead>
        <tbody>
          <?php if(empty($examens)):?><tr><td colspan="6" style="text-align:center;padding:28px;color:var(--muted)">Aucun examen</td></tr>
          <?php else: foreach($examens as $e):?>
          <tr>
            <td><strong><?=htmlspecialchars($e['type_examen'])?></strong></td>
            <td><?=htmlspecialchars($e['prenom'].' '.$e['nom'])?></td>
            <td><span style="padding:2px 8px;border-radius:10px;font-size:.7rem;font-weight:700;background:<?=$e['priorite']==='urgente'?'#fee2e2':'#f0f4fa'?>;color:<?=$e['priorite']==='urgente'?'#991b1b':'#6b7280'?>"><?=ucfirst($e['priorite'])?></span></td>
            <td style="font-size:.82rem;color:var(--muted)"><?=date('d/m/Y',strtotime($e['date_demande']))?></td>
            <td><span class="status-badge <?=$e['statut']==='transmis'?'status-active':($e['statut']==='en_cours'?'status-done':'status-pending')?>"><?=$e['statut']==='transmis'?'Reçu':($e['statut']==='en_cours'?'En cours':'En attente')?></span></td>
            <td>
              <?php if($e['statut']==='transmis'&&$e['resultat']):?>
              <button class="btn-outline" style="padding:4px 10px;font-size:.76rem" onclick="voirResultat('<?=htmlspecialchars($e['type_examen'],ENT_QUOTES)?>','<?=htmlspecialchars(addslashes($e['resultat']),ENT_QUOTES)?>')">
                <span class="material-icons" style="font-size:14px">visibility</span> Voir
              </button>
              <?php else:?><span style="color:var(--muted);font-size:.8rem">—</span><?php endif;?>
            </td>
          </tr>
          <?php endforeach;endif;?>
        </tbody>
      </table></div>
    </div>
  </main>
</div>
<!-- Modal résultat -->
<div class="modal-overlay" id="modalRes">
  <div class="modal">
    <div class="modal-header"><h3 id="resTitle">Résultat</h3><button class="modal-close" onclick="closeModal('modalRes')"><span class="material-icons">close</span></button></div>
    <div class="resultat-box" id="resContent" style="white-space:pre-wrap"></div>
    <button class="btn-outline" onclick="closeModal('modalRes')" style="width:100%;margin-top:16px">Fermer</button>
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
function voirResultat(type,res){document.getElementById('resTitle').textContent=type;document.getElementById('resContent').textContent=res;openModal('modalRes');}
</script>
</body></html>
