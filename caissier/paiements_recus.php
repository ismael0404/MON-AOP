<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['caissier']);
$user = getUser(); $pdo = getDB();

$page   = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;
$total  = (int)$pdo->query("SELECT COUNT(*) FROM paiements")->fetchColumn();
$totalPages = ceil($total/$perPage);

$stmt=$pdo->prepare("
    SELECT p.*,u.nom,u.prenom,f.montant_total,uc.nom as caiss_nom,uc.prenom as caiss_prenom
    FROM paiements p
    JOIN factures f ON p.facture_id=f.id
    JOIN patients pt ON f.patient_id=pt.id JOIN utilisateurs u ON pt.utilisateur_id=u.id
    LEFT JOIN utilisateurs uc ON p.caissier_id=uc.id
    ORDER BY p.date_paiement DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute();$paiements=$stmt->fetchAll();

$s=$pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE DATE(date_paiement)=CURDATE()");$recAuj=(float)$s->fetchColumn();
$s=$pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE MONTH(date_paiement)=MONTH(NOW()) AND YEAR(date_paiement)=YEAR(NOW())");$recMois=(float)$s->fetchColumn();
$s=$pdo->query("SELECT COUNT(*) FROM paiements WHERE DATE(date_paiement)=CURDATE()");$nbAuj=(int)$s->fetchColumn();
$nbImpayees=(int)$pdo->query("SELECT COUNT(*) FROM factures WHERE statut='impayee'")->fetchColumn();

$modeIcons=['especes'=>'💵','carte'=>'💳','mobile_money'=>'📱','cheque'=>'📄'];
$modeLabels=['especes'=>'Espèces','carte'=>'Carte','mobile_money'=>'Mobile Money','cheque'=>'Chèque'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Paiements Reçus</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .mode-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.76rem;font-weight:600;background:#f0f4fa;color:var(--text)}
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted)}
    .page-btns{display:flex;gap:5px}.page-btn{padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}.page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:700px){.stats-row{grid-template-columns:1fr 1fr}}
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
    <a class="nav-item active" href="paiements_recus.php"><span class="material-icons">check_circle</span> Paiements reçus</a>
    <div class="nav-section-title">Rapports</div>
    <a class="nav-item" href="rapports.php"><span class="material-icons">bar_chart</span> Rapport</a>
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
    <div class="page-header" style="margin-bottom:20px"><h1>Paiements reçus</h1><p>Historique de tous les encaissements</p></div>
    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">payments</span></div><div><div class="stat-value"><?=number_format($recAuj/1000,0)?>K</div><div class="stat-label">Encaissé aujourd'hui (FCFA)</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">trending_up</span></div><div><div class="stat-value"><?=number_format($recMois/1000,0)?>K</div><div class="stat-label">Ce mois (FCFA)</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#7c3aed"><span class="material-icons">receipt_long</span></div><div><div class="stat-value"><?=$nbAuj?></div><div class="stat-label">Paiements aujourd'hui</div></div></div>
    </div>
    <div class="table-card">
      <div class="table-header"><h3>Historique des paiements</h3><span style="font-size:.8rem;color:var(--muted)"><?=$total?> paiement(s)</span></div>
      <div style="overflow-x:auto"><table class="klinik-table">
        <thead><tr><th>#</th><th>Patient</th><th>Montant payé</th><th>Facture</th><th>Mode</th><th>Référence</th><th>Caissier</th><th>Date</th></tr></thead>
        <tbody>
          <?php if(empty($paiements)):?><tr><td colspan="8" style="text-align:center;padding:28px;color:var(--muted)">Aucun paiement enregistré</td></tr>
          <?php else: foreach($paiements as $p): ?>
          <tr>
            <td style="color:var(--muted);font-size:.78rem"><?=str_pad($p['id'],5,'0',STR_PAD_LEFT)?></td>
            <td><strong><?=htmlspecialchars($p['prenom'].' '.$p['nom'])?></strong></td>
            <td><strong style="color:var(--success);font-family:'Oswald',sans-serif;font-size:1rem"><?=number_format($p['montant_paye'],0,',',' ')?> FCFA</strong></td>
            <td style="color:var(--muted);font-size:.82rem">#<?=str_pad($p['facture_id'],4,'0',STR_PAD_LEFT)?></td>
            <td><span class="mode-badge"><?=($modeIcons[$p['mode_paiement']]??'').' '.($modeLabels[$p['mode_paiement']]??$p['mode_paiement'])?></span></td>
            <td style="font-size:.8rem;color:var(--muted)"><?=htmlspecialchars($p['reference']??'—')?></td>
            <td style="font-size:.8rem;color:var(--muted)"><?=$p['caiss_nom']?htmlspecialchars($p['caiss_prenom'].' '.$p['caiss_nom']):'—'?></td>
            <td style="font-size:.8rem;color:var(--muted);white-space:nowrap"><?=date('d/m/Y H:i',strtotime($p['date_paiement']))?></td>
          </tr>
          <?php endforeach;endif; ?>
        </tbody>
      </table></div>
      <div class="pagination">
        <span>Affichage <?=min($offset+1,$total)?>–<?=min($offset+$perPage,$total)?> sur <?=$total?></span>
        <div class="page-btns">
          <?php if($page>1):?><a class="page-btn" href="?page=<?=$page-1?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif;?>
          <?php for($pg=max(1,$page-2);$pg<=min($totalPages,$page+2);$pg++):?><a class="page-btn <?=$pg===$page?'active':''?>" href="?page=<?=$pg?>"><?=$pg?></a><?php endfor;?>
          <?php if($page<$totalPages):?><a class="page-btn" href="?page=<?=$page+1?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif;?>
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
</script>
</body></html>
