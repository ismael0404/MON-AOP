<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['caissier']);
$user = getUser(); $pdo = getDB();

$recAuj   = (float)$pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE DATE(date_paiement)=CURDATE()")->fetchColumn();
$recMois  = (float)$pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE MONTH(date_paiement)=MONTH(NOW()) AND YEAR(date_paiement)=YEAR(NOW())")->fetchColumn();
$nbImpayees=(int)$pdo->query("SELECT COUNT(*) FROM factures WHERE statut='impayee'")->fetchColumn();
$soldeImp=(float)$pdo->query("SELECT COALESCE(SUM(f.montant_total-COALESCE(p2.tot,0)),0) FROM factures f LEFT JOIN (SELECT facture_id,SUM(montant_paye) as tot FROM paiements GROUP BY facture_id) p2 ON f.id=p2.facture_id WHERE f.statut IN('impayee','partielle')")->fetchColumn();

// Recettes 7 jours
$joursL=[]; $joursV=[];
for($i=6;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-$i days"));
    $joursL[]=date('D d/m',strtotime($d));
    $s=$pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE DATE(date_paiement)=?");
    $s->execute([$d]); $joursV[]=(float)$s->fetchColumn();
}
// Recettes 6 mois
$moisL=[]; $moisV=[];
for($i=5;$i>=0;$i--){
    $m=date('Y-m',strtotime("-$i months"));
    $moisL[]=date('M y',strtotime("-$i months"));
    $s=$pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE DATE_FORMAT(date_paiement,'%Y-%m')=?");
    $s->execute([$m]); $moisV[]=(float)$s->fetchColumn();
}
// Répartition modes paiement
$nbEsp=(int)$pdo->query("SELECT COUNT(*) FROM paiements WHERE mode_paiement='especes'")->fetchColumn();
$nbCarte=(int)$pdo->query("SELECT COUNT(*) FROM paiements WHERE mode_paiement='carte'")->fetchColumn();
$nbMob=(int)$pdo->query("SELECT COUNT(*) FROM paiements WHERE mode_paiement='mobile_money'")->fetchColumn();
$nbCheq=(int)$pdo->query("SELECT COUNT(*) FROM paiements WHERE mode_paiement='cheque'")->fetchColumn();
// Résumé mois
$nbFacturesMois=(int)$pdo->query("SELECT COUNT(*) FROM factures WHERE MONTH(date_facture)=MONTH(NOW()) AND YEAR(date_facture)=YEAR(NOW())")->fetchColumn();
$nbPayeeMois=(int)$pdo->query("SELECT COUNT(*) FROM factures WHERE statut='payee' AND MONTH(date_facture)=MONTH(NOW())")->fetchColumn();
$nbPaiementsMois=(int)$pdo->query("SELECT COUNT(*) FROM paiements WHERE MONTH(date_paiement)=MONTH(NOW())")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Rapport Financier</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.05s}.stat-card:nth-child(2){animation-delay:.10s}.stat-card:nth-child(3){animation-delay:.15s}.stat-card:nth-child(4){animation-delay:.20s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.4rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
    .card{background:#fff;border-radius:14px;padding:20px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase}
    .resume-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f4fa;font-size:.86rem}
    .resume-row:last-child{border:none}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:1000px){.stats-grid{grid-template-columns:repeat(2,1fr)}.charts-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-caissier">Caissier</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Facturation</div>
    <a class="nav-item" href="nouvelle_facture.php"><span class="material-icons">add_circle</span> Nouvelle facture</a>
    <a class="nav-item" href="toutes_les_factures.php"><span class="material-icons">receipt_long</span> Toutes les factures</a>
    <a class="nav-item" href="impayes.php"><span class="material-icons">pending_actions</span> Impayés</a>
    <a class="nav-item" href="paiements_recus.php"><span class="material-icons">check_circle</span> Paiements reçus</a>
    <div class="nav-section-title">Rapports</div>
    <a class="nav-item active" href="rapports.php"><span class="material-icons">bar_chart</span> Rapport</a>
  
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
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher facture, patient..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbImpayees>0):?><span class="notif-badge"><?=$nbImpayees?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">CA</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Caissier</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Rapport financier</h1><p>Analyse de l'activité financière — <?=date('d F Y')?></p></div>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">today</span></div><div><div class="stat-value"><?=number_format($recAuj/1000,1)?>K</div><div class="stat-label">Recette aujourd'hui (FCFA)</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">calendar_month</span></div><div><div class="stat-value"><?=number_format($recMois/1000,0)?>K</div><div class="stat-label">Recette du mois (FCFA)</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--danger)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?=$nbImpayees?></div><div class="stat-label">Factures impayées</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">money_off</span></div><div><div class="stat-value"><?=number_format($soldeImp/1000,0)?>K</div><div class="stat-label">Solde impayé (FCFA)</div></div></div>
    </div>

    <div class="charts-grid">
      <div class="card"><div class="card-header"><h3>Recettes — 7 derniers jours</h3></div><canvas id="chartJ" height="140"></canvas></div>
      <div class="card"><div class="card-header"><h3>Recettes mensuelles</h3></div><canvas id="chartM" height="140"></canvas></div>
    </div>

    <div class="charts-grid">
      <div class="card"><div class="card-header"><h3>Modes de paiement</h3></div><canvas id="chartP" height="180"></canvas></div>
      <div class="card">
        <div class="card-header"><h3>Résumé du mois — <?=date('F Y')?></h3></div>
        <div class="resume-row"><span style="color:var(--muted)">Recette totale</span><strong><?=number_format($recMois,0,',',' ')?> FCFA</strong></div>
        <div class="resume-row"><span style="color:var(--muted)">Nombre de paiements</span><strong><?=$nbPaiementsMois?></strong></div>
        <div class="resume-row"><span style="color:var(--muted)">Factures créées</span><strong><?=$nbFacturesMois?></strong></div>
        <div class="resume-row"><span style="color:var(--muted)">Factures soldées</span><strong style="color:var(--success)"><?=$nbPayeeMois?></strong></div>
        <div class="resume-row"><span style="color:var(--muted)">Solde total impayé</span><strong style="color:var(--danger)"><?=number_format($soldeImp,0,',',' ')?> FCFA</strong></div>
        <div class="resume-row"><span style="color:var(--muted)">Taux recouvrement</span>
          <?php $taux=$nbFacturesMois>0?round(($nbPayeeMois/$nbFacturesMois)*100):0; ?>
          <strong style="color:<?=$taux>=70?'var(--success)':($taux>=40?'var(--warning)':'var(--danger)')?>"><?=$taux?>%</strong>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/klinik.js"></script>
<script>
const user={nom:'<?=htmlspecialchars($user["nom"])?>',prenom:'<?=htmlspecialchars($user["prenom"])?>',email:'<?=htmlspecialchars($user["email"])?>',role:'<?=$user["role"]?>'};
KlinikUI.fillUserInfo(user);KlinikUI.initSidebar();
document.getElementById('topbarUserName').textContent=user.prenom+' '+user.nom;
document.getElementById('topbarAvatar').textContent=(user.prenom[0]+user.nom[0]).toUpperCase();

new Chart(document.getElementById('chartJ').getContext('2d'),{
  type:'bar',data:{labels:<?=json_encode($joursL)?>,datasets:[{data:<?=json_encode($joursV)?>,backgroundColor:'rgba(5,150,105,.15)',borderColor:'#059669',borderWidth:2,borderRadius:5}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>v>=1000?v/1000+'K':v},grid:{color:'#f0f4ff'}},x:{grid:{display:false}}}}
});
new Chart(document.getElementById('chartM').getContext('2d'),{
  type:'line',data:{labels:<?=json_encode($moisL)?>,datasets:[{data:<?=json_encode($moisV)?>,fill:true,backgroundColor:'rgba(37,99,235,.08)',borderColor:'#2563eb',borderWidth:2,tension:.4,pointRadius:4,pointBackgroundColor:'#2563eb'}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>v>=1000?v/1000+'K':v},grid:{color:'#f0f4ff'}},x:{grid:{display:false}}}}
});
new Chart(document.getElementById('chartP').getContext('2d'),{
  type:'doughnut',data:{labels:['Espèces','Mobile Money','Carte','Chèque'],datasets:[{data:[<?=$nbEsp?>,<?=$nbMob?>,<?=$nbCarte?>,<?=$nbCheq?>],backgroundColor:['#2563eb','#059669','#0891b2','#d97706'],borderWidth:0}]},
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:12}}},cutout:'58%'}
});
</script>
</body></html>
