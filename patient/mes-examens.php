<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['patient']);
$user = getUser(); $pdo = getDB();
$patientId = $user['patient_id'] ?? null;

$filter = sanitize($_GET['statut'] ?? '');
$where  = "WHERE c.patient_id=?"; $params=[$patientId];
if($filter){$where.=" AND e.statut=?";$params[]=$filter;}

$stmt = $pdo->prepare("
    SELECT e.*,u.nom as med_nom,u.prenom as med_prenom,m.specialite
    FROM examens e
    JOIN consultations c ON e.consultation_id=c.id
    JOIN medecins m ON c.medecin_id=m.id
    JOIN utilisateurs u ON m.utilisateur_id=u.id
    $where ORDER BY e.date_demande DESC
");
$stmt->execute($params); $examens=$stmt->fetchAll();

$s=$pdo->prepare("SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.patient_id=? AND e.statut='transmis'");$s->execute([$patientId]);$nbRec=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.patient_id=? AND e.statut='en_attente'");$s->execute([$patientId]);$nbAtt=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.patient_id=? AND e.statut='transmis' AND DATE(e.date_resultat)=CURDATE()");$s->execute([$patientId]);$nbNouv=(int)$s->fetchColumn();

$nbImpayees=(int)$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'")->execute([$patientId])&&($si=$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'"))&&$si->execute([$patientId])?$si->fetchColumn():0;
$si=$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'");$si->execute([$patientId]);$nbImpayees=(int)$si->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Mes Examens</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
    .filter-btn{padding:7px 16px;border-radius:20px;border:1.5px solid #eef0f6;background:#fff;font-size:.82rem;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text)}
    .filter-btn:hover,.filter-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .examen-card{background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:18px;margin-bottom:12px;box-shadow:0 2px 8px rgba(26,58,110,.04);transition:transform .2s,box-shadow .2s;animation:fadeUp .5s ease both;opacity:0}
    .examen-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,58,110,.1)}
    .examen-card.nouveau{border-color:#86efac;background:#f0fdf4}
    .ex-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
    .ex-type{font-family:'Oswald',sans-serif;font-size:1rem;font-weight:700;color:var(--blue);display:flex;align-items:center;gap:8px}
    .ex-type .material-icons{font-size:20px;color:var(--blue-bright)}
    .resultat-box{background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:14px;margin-top:12px}
    .resultat-title{font-size:.72rem;font-weight:700;text-transform:uppercase;color:#0369a1;margin-bottom:6px;display:flex;align-items:center;gap:5px}
    .resultat-content{font-size:.86rem;color:#0c4a6e;line-height:1.6;white-space:pre-wrap}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:700px){.stats-row{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-patient">Patient</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Mon espace</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <a class="nav-item" href="#" onclick="openModal('modalRDV');return false;"><span class="material-icons">add_circle</span> Prendre un RDV</a>
    <a class="nav-item" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span> Mes rendez-vous</a>
    <a class="nav-item" href="mon-dossier.php"><span class="material-icons">folder_shared</span> Mon dossier médical</a>
    <a class="nav-item active" href="mes-examens.php"><span class="material-icons">science</span> Mes examens</a>
    <a class="nav-item" href="mes-factures.php"><span class="material-icons">receipt_long</span> Mes factures</a>
  
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
  </nav>
  <div class="sidebar-footer"><a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a></div>
</aside>
<div class="main-wrapper">
  <header class="topbar">
    <a class="topbar-logo" href="dashboard.php"><img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'"><span class="logo-name">KLINIK</span></a>
    <div class="topbar-left"><button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button></div>
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher médecin, rendez-vous..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbNouv>0):?><span class="notif-badge"><?=$nbNouv?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">PT</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Patient</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Mes examens</h1><p>Résultats de vos analyses et examens médicaux</p></div>
    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">check_circle</span></div><div><div class="stat-value"><?=$nbRec?></div><div class="stat-label">Résultats reçus</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?=$nbAtt?></div><div class="stat-label">En attente</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">new_releases</span></div><div><div class="stat-value"><?=$nbNouv?></div><div class="stat-label">Nouveaux aujourd'hui</div></div></div>
    </div>
    <div class="filter-bar">
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="mes-examens.php">Tous</a>
      <a class="filter-btn <?= $filter==='transmis'?'active':'' ?>" href="?statut=transmis">Résultats reçus</a>
      <a class="filter-btn <?= $filter==='en_cours'?'active':'' ?>" href="?statut=en_cours">En cours</a>
      <a class="filter-btn <?= $filter==='en_attente'?'active':'' ?>" href="?statut=en_attente">En attente</a>
    </div>

    <?php if(empty($examens)):?>
    <div style="background:#fff;border-radius:14px;padding:48px;text-align:center;border:1.5px solid #eef0f6">
      <span class="material-icons" style="font-size:48px;color:var(--border)">science</span>
      <p style="color:var(--muted);margin-top:12px">Aucun examen enregistré</p>
    </div>
    <?php else: foreach($examens as $i=>$e):
      $isNouveau = $e['statut']==='transmis' && $e['date_resultat'] && date('Y-m-d',strtotime($e['date_resultat']))===date('Y-m-d');
    ?>
    <div class="examen-card <?=$isNouveau?'nouveau':''?>" style="animation-delay:<?=$i*0.06?>s">
      <div class="ex-header">
        <div class="ex-type">
          <span class="material-icons">biotech</span>
          <?=htmlspecialchars($e['type_examen'])?>
          <?php if($isNouveau):?><span style="background:#16a34a;color:#fff;font-size:.65rem;padding:2px 8px;border-radius:20px;font-family:inherit;font-weight:600">NOUVEAU</span><?php endif;?>
          <?php if($e['priorite']==='urgente'):?><span style="background:#fee2e2;color:#991b1b;font-size:.65rem;padding:2px 8px;border-radius:20px;font-family:inherit;font-weight:600">URGENT</span><?php endif;?>
        </div>
        <span class="status-badge <?=$e['statut']==='transmis'?'status-active':($e['statut']==='en_cours'?'status-done':'status-pending')?>"><?=$e['statut']==='transmis'?'Résultat reçu':($e['statut']==='en_cours'?'En cours':'En attente')?></span>
      </div>
      <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:.8rem;color:var(--muted)">
        <span><span class="material-icons" style="font-size:14px;vertical-align:middle">stethoscope</span> Dr. <?=htmlspecialchars($e['med_prenom'].' '.$e['med_nom'])?></span>
        <span><span class="material-icons" style="font-size:14px;vertical-align:middle">schedule</span> Demandé le <?=date('d/m/Y',strtotime($e['date_demande']))?></span>
        <?php if($e['date_resultat']):?><span><span class="material-icons" style="font-size:14px;vertical-align:middle">check</span> Reçu le <?=date('d/m/Y',strtotime($e['date_resultat']))?></span><?php endif;?>
      </div>
      <?php if($e['statut']==='transmis'&&$e['resultat']):?>
      <div class="resultat-box">
        <div class="resultat-title"><span class="material-icons" style="font-size:15px">description</span> Résultat de l'examen</div>
        <div class="resultat-content"><?=htmlspecialchars($e['resultat'])?></div>
      </div>
      <?php elseif($e['statut']==='en_attente'):?>
      <div style="background:#fef9ec;border:1px solid #fcd34d;border-radius:8px;padding:10px 12px;margin-top:10px;font-size:.8rem;color:#92400e">
        <span class="material-icons" style="font-size:14px;vertical-align:middle">info</span> Cet examen est en attente de traitement par le laboratoire.
      </div>
      <?php elseif($e['statut']==='en_cours'):?>
      <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;padding:10px 12px;margin-top:10px;font-size:.8rem;color:#1e40af">
        <span class="material-icons" style="font-size:14px;vertical-align:middle">labs</span> Cet examen est en cours d'analyse au laboratoire.
      </div>
      <?php endif;?>
    </div>
    <?php endforeach;endif;?>
  </main>
</div>
<script src="../assets/js/klinik.js"></script>
<script>
const user={nom:'<?=htmlspecialchars($user["nom"])?>',prenom:'<?=htmlspecialchars($user["prenom"])?>',email:'<?=htmlspecialchars($user["email"])?>',role:'<?=$user["role"]?>'};
KlinikUI.fillUserInfo(user);KlinikUI.initSidebar();
document.getElementById('topbarUserName').textContent=user.prenom+' '+user.nom;
document.getElementById('topbarAvatar').textContent=(user.prenom[0]+user.nom[0]).toUpperCase();
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
</script>
</body></html>
