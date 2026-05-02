<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['admin']);
$user = getUser(); $pdo = getDB();

$nbPatients  = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='patient' AND actif=1")->fetchColumn();
$nbMedecins  = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='medecin' AND actif=1")->fetchColumn();
$nbConsults  = (int)$pdo->query("SELECT COUNT(*) FROM consultations")->fetchColumn();
$revenuMois  = (float)$pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE MONTH(date_paiement)=MONTH(NOW()) AND YEAR(date_paiement)=YEAR(NOW())")->fetchColumn();
$nbRdvTotal  = (int)$pdo->query("SELECT COUNT(*) FROM rendez_vous")->fetchColumn();
$nbExamens   = (int)$pdo->query("SELECT COUNT(*) FROM examens")->fetchColumn();

// Consultations 30 jours
$consults30=[]; $labels30=[];
for($i=29;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-$i days"));
    $labels30[]=date('d/m',strtotime($d));
    $s=$pdo->prepare("SELECT COUNT(*) FROM consultations WHERE DATE(date_consult)=?");
    $s->execute([$d]); $consults30[]=(int)$s->fetchColumn();
}
// Revenus 6 mois
$revMois=[]; $revLabels=[];
for($i=5;$i>=0;$i--){
    $m=date('Y-m',strtotime("-$i months"));
    $revLabels[]=date('M y',strtotime("-$i months"));
    $s=$pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE DATE_FORMAT(date_paiement,'%Y-%m')=?");
    $s->execute([$m]); $revMois[]=(float)$s->fetchColumn();
}
// Répartition rôles
$rolesNb=[];
foreach(['admin','medecin','patient','laborantin','caissier'] as $r){
    $s=$pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE role=?");$s->execute([$r]);$rolesNb[]=(int)$s->fetchColumn();
}
// Top médecins
$topMeds=$pdo->query("SELECT u.nom,u.prenom,m.specialite,COUNT(c.id) as nb FROM medecins m JOIN utilisateurs u ON m.utilisateur_id=u.id LEFT JOIN consultations c ON m.id=c.medecin_id GROUP BY m.id ORDER BY nb DESC LIMIT 5")->fetchAll();
// Activité récente
$activite=$pdo->query("SELECT c.date_consult,up.nom as p_nom,up.prenom as p_prenom,um.nom as m_nom,um.prenom as m_prenom,c.diagnostic FROM consultations c JOIN patients p ON c.patient_id=p.id JOIN utilisateurs up ON p.utilisateur_id=up.id JOIN medecins m ON c.medecin_id=m.id JOIN utilisateurs um ON m.utilisateur_id=um.id ORDER BY c.date_consult DESC LIMIT 8")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Rapports</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.05s}.stat-card:nth-child(2){animation-delay:.10s}.stat-card:nth-child(3){animation-delay:.15s}.stat-card:nth-child(4){animation-delay:.20s}.stat-card:nth-child(5){animation-delay:.25s}.stat-card:nth-child(6){animation-delay:.30s}
    .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:22px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
    .card{background:#fff;border-radius:14px;padding:20px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase}
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:14px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:1000px){.stats-grid{grid-template-columns:repeat(2,1fr)}.charts-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-admin">Administrateur</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Gestion</div>
    <a class="nav-item" href="utilisateurs.php"><span class="material-icons">manage_accounts</span> Utilisateurs</a>
    <a class="nav-item" href="historique.php"><span class="material-icons">calendar_today</span> Historique des Rendez-vous</a>
    <a class="nav-item" href="dossiers_medicaux.php"><span class="material-icons">folder_shared</span> Dossiers médicaux</a>
    <a class="nav-item" href="facturation.php"><span class="material-icons">receipt_long</span> Facturation</a>
    
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
    <a class="nav-item active" href="rapports.php"><span class="material-icons">bar_chart</span> Rapports</a>
    <a class="nav-item" href="parametres.php"><span class="material-icons">settings</span> Paramètres</a>
  </nav>
  <div class="sidebar-footer"><a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a></div>
</aside>
<div class="main-wrapper">
  <header class="topbar">
    <a class="topbar-logo" href="dashboard.php"><img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'"><span class="logo-name">KLINIK</span></a>
    <div class="topbar-left"><button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button></div>
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher patient, médecin, rendez-vous..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">AD</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Administrateur</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Rapports & Statistiques</h1><p>Vue globale de l'activité — <?=date('d F Y')?></p></div>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">groups</span></div><div><div class="stat-value"><?=$nbPatients?></div><div class="stat-label">Patients</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#0891b2"><span class="material-icons">stethoscope</span></div><div><div class="stat-value"><?=$nbMedecins?></div><div class="stat-label">Médecins actifs</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">medical_services</span></div><div><div class="stat-value"><?=$nbConsults?></div><div class="stat-label">Consultations total</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#7c3aed"><span class="material-icons">payments</span></div><div><div class="stat-value"><?=number_format($revenuMois/1000,0)?>K</div><div class="stat-label">Revenus ce mois (FCFA)</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">calendar_today</span></div><div><div class="stat-value"><?=$nbRdvTotal?></div><div class="stat-label">RDV total</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#0e7490"><span class="material-icons">science</span></div><div><div class="stat-value"><?=$nbExamens?></div><div class="stat-label">Examens total</div></div></div>
    </div>
    <div class="charts-grid">
      <div class="card"><div class="card-header"><h3>Consultations — 30 derniers jours</h3></div><canvas id="chartC" height="120"></canvas></div>
      <div class="card"><div class="card-header"><h3>Revenus mensuels (FCFA)</h3></div><canvas id="chartR" height="120"></canvas></div>
    </div>
    <div class="charts-grid">
      <div class="card"><div class="card-header"><h3>Répartition utilisateurs</h3></div><canvas id="chartU" height="180"></canvas></div>
      <div class="table-card">
        <div class="table-header"><h3>Top médecins</h3></div>
        <table class="klinik-table">
          <thead><tr><th>#</th><th>Médecin</th><th>Spécialité</th><th>Consultations</th></tr></thead>
          <tbody>
            <?php foreach($topMeds as $i=>$m):?>
            <tr><td style="font-weight:700;color:var(--blue)"><?=$i+1?></td><td><strong>Dr. <?=htmlspecialchars($m['prenom'].' '.$m['nom'])?></strong></td><td style="font-size:.82rem;color:var(--muted)"><?=htmlspecialchars($m['specialite']??'—')?></td><td style="text-align:center"><strong><?=$m['nb']?></strong></td></tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="table-card" style="margin-top:18px">
      <div class="table-header"><h3>Activité récente</h3></div>
      <table class="klinik-table">
        <thead><tr><th>Date</th><th>Patient</th><th>Médecin</th><th>Diagnostic</th></tr></thead>
        <tbody>
          <?php foreach($activite as $a):?>
          <tr>
            <td style="font-size:.82rem;color:var(--muted)"><?=date('d/m/Y H:i',strtotime($a['date_consult']))?></td>
            <td><strong><?=htmlspecialchars($a['p_prenom'].' '.$a['p_nom'])?></strong></td>
            <td>Dr. <?=htmlspecialchars($a['m_prenom'].' '.$a['m_nom'])?></td>
            <td style="font-size:.82rem;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(substr($a['diagnostic']??'—',0,60))?></td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
    </div>
  </main>
</div>
<script src="../assets/js/klinik.js"></script>
<script>
const user={nom:'<?=htmlspecialchars($user["nom"])?>',prenom:'<?=htmlspecialchars($user["prenom"])?>',email:'<?=htmlspecialchars($user["email"])?>',role:'<?=$user["role"]?>'};
KlinikUI.fillUserInfo(user);KlinikUI.initSidebar();
document.getElementById('topbarUserName').textContent=user.prenom+' '+user.nom;
document.getElementById('topbarAvatar').textContent=(user.prenom[0]+user.nom[0]).toUpperCase();
new Chart(document.getElementById('chartC').getContext('2d'),{type:'bar',data:{labels:<?=json_encode($labels30)?>,datasets:[{data:<?=json_encode($consults30)?>,backgroundColor:'rgba(37,99,235,.15)',borderColor:'#2563eb',borderWidth:2,borderRadius:4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'#f0f4ff'}},x:{grid:{display:false},ticks:{maxTicksLimit:8}}}}});
new Chart(document.getElementById('chartR').getContext('2d'),{type:'line',data:{labels:<?=json_encode($revLabels)?>,datasets:[{data:<?=json_encode($revMois)?>,fill:true,backgroundColor:'rgba(5,150,105,.08)',borderColor:'#059669',borderWidth:2,tension:.4,pointRadius:4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#f0f4ff'},ticks:{callback:v=>v>=1000?v/1000+'K':v}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('chartU').getContext('2d'),{type:'doughnut',data:{labels:['Admin','Médecins','Patients','Laborantins','Caissiers'],datasets:[{data:<?=json_encode($rolesNb)?>,backgroundColor:['#7c3aed','#0891b2','#2563eb','#059669','#d97706'],borderWidth:0}]},options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:10}}},cutout:'60%'}});
</script>
</body></html>
