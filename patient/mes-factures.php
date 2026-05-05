<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['patient']);
$user = getUser(); $pdo = getDB();
$patientId = $user['patient_id'] ?? null;

$filter = sanitize($_GET['statut'] ?? '');
$page   = max(1,(int)($_GET['page']??1)); $perPage=10; $offset=($page-1)*$perPage;

$where  = "WHERE f.patient_id=?"; $params=[$patientId];
if($filter){$where.=" AND f.statut=?";$params[]=$filter;}

$stmtC=$pdo->prepare("SELECT COUNT(*) FROM factures f $where");$stmtC->execute($params);$total=(int)$stmtC->fetchColumn();$totalPages=ceil($total/$perPage);
$stmt=$pdo->prepare("SELECT f.*,COALESCE(SUM(p.montant_paye),0) as total_paye FROM factures f LEFT JOIN paiements p ON f.id=p.facture_id $where GROUP BY f.id ORDER BY f.date_facture DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);$factures=$stmt->fetchAll();

$s=$pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiements p JOIN factures f ON p.facture_id=f.id WHERE f.patient_id=?");$s->execute([$patientId]);$totalPaye=(float)$s->fetchColumn();
$s=$pdo->prepare("SELECT COALESCE(SUM(montant_total),0) FROM factures WHERE patient_id=? AND statut='impayee'");$s->execute([$patientId]);$totalImp=(float)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'");$s->execute([$patientId]);$nbImpayees=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=?");$s->execute([$patientId]);$nbTotal=(int)$s->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Mes Factures</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}.stat-card:nth-child(4){animation-delay:.24s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.4rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
    .filter-btn{padding:7px 16px;border-radius:20px;border:1.5px solid #eef0f6;background:#fff;font-size:.82rem;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text)}
    .filter-btn:hover,.filter-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    <?php if($nbImpayees>0): ?>
    .alerte-impayee{background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:12px;padding:16px 20px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:20px;animation:fadeUp .5s ease both}
    .alerte-impayee .material-icons{font-size:28px;flex-shrink:0}
    <?php endif; ?>
    .facture-card{background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:18px;margin-bottom:12px;display:flex;align-items:center;gap:16px;box-shadow:0 2px 8px rgba(26,58,110,.04);transition:transform .2s;animation:fadeUp .5s ease both;opacity:0}
    .facture-card:hover{transform:translateY(-2px)}
    .facture-card.impayee{border-color:#fca5a5}
    .facture-num{font-family:'Oswald',sans-serif;font-size:1.1rem;font-weight:700;color:var(--blue-bright);min-width:60px}
    .progress-bar-wrap{flex:1;min-width:80px}
    .progress-bar{background:#eef0f6;border-radius:20px;height:6px;overflow:hidden;margin-top:4px}
    .progress-fill{height:100%;border-radius:20px;transition:width .6s ease}
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted);background:#fff;border-radius:12px;border:1.5px solid #eef0f6;margin-top:4px}
    .page-btns{display:flex;gap:5px}.page-btn{padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}.page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:500px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .detail-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0f4fa;font-size:.88rem}
    .detail-row:last-child{border:none}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}.facture-card{flex-wrap:wrap}}
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
    <a class="nav-item" href="mes-examens.php"><span class="material-icons">science</span> Mes examens</a>
    <a class="nav-item active" href="mes-factures.php"><span class="material-icons">receipt_long</span> Mes factures</a>
  
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
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbImpayees>0):?><span class="notif-badge"><?=$nbImpayees?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">PT</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Patient</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Mes factures</h1><p>Suivi de vos paiements et factures médicales</p></div>

    <?php if($nbImpayees>0):?>
    <div class="alerte-impayee">
      <span class="material-icons">warning</span>
      <div>
        <strong style="font-family:'Oswald',sans-serif;font-size:1rem"><?=$nbImpayees?> facture(s) impayée(s)</strong>
        <div style="font-size:.8rem;opacity:.85;margin-top:2px">Solde restant : <strong><?=number_format($totalImp,0,',',' ')?> FCFA</strong> — Veuillez vous rapprocher de la caisse.</div>
      </div>
    </div>
    <?php endif;?>

    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">payments</span></div><div><div class="stat-value"><?=number_format($totalPaye/1000,0)?>K</div><div class="stat-label">Total payé (FCFA)</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--danger)"><span class="material-icons">money_off</span></div><div><div class="stat-value"><?=number_format($totalImp/1000,0)?>K</div><div class="stat-label">Solde impayé (FCFA)</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--danger)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?=$nbImpayees?></div><div class="stat-label">Factures impayées</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">receipt_long</span></div><div><div class="stat-value"><?=$nbTotal?></div><div class="stat-label">Total factures</div></div></div>
    </div>

    <div class="filter-bar">
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="mes-factures.php">Toutes</a>
      <a class="filter-btn <?= $filter==='impayee'?'active':'' ?>" href="?statut=impayee">Impayées</a>
      <a class="filter-btn <?= $filter==='partielle'?'active':'' ?>" href="?statut=partielle">Partielles</a>
      <a class="filter-btn <?= $filter==='payee'?'active':'' ?>" href="?statut=payee">Payées</a>
    </div>

    <?php if(empty($factures)):?>
    <div style="background:#fff;border-radius:14px;padding:48px;text-align:center;border:1.5px solid #eef0f6">
      <span class="material-icons" style="font-size:48px;color:var(--border)">receipt_long</span>
      <p style="color:var(--muted);margin-top:12px">Aucune facture trouvée</p>
    </div>
    <?php else: foreach($factures as $i=>$f):
      $pct = $f['montant_total']>0 ? min(100,round(($f['total_paye']/$f['montant_total'])*100)) : 0;
      $colorBar = $f['statut']==='payee'?'var(--success)':($f['statut']==='partielle'?'var(--warning)':'var(--danger)');
    ?>
    <div class="facture-card <?=$f['statut']==='impayee'?'impayee':''?>" style="animation-delay:<?=$i*0.06?>s">
      <div>
        <div class="facture-num">#<?=str_pad($f['id'],4,'0',STR_PAD_LEFT)?></div>
        <div style="font-size:.72rem;color:var(--muted);margin-top:2px"><?=date('d/m/Y',strtotime($f['date_facture']))?></div>
      </div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
          <strong style="font-family:'Oswald',sans-serif;font-size:1.1rem"><?=number_format($f['montant_total'],0,',',' ')?> FCFA</strong>
          <span class="status-badge <?=$f['statut']==='payee'?'status-active':($f['statut']==='partielle'?'status-pending':'status-inactive')?>"><?=$f['statut']==='payee'?'Payée':($f['statut']==='partielle'?'Partielle':'Impayée')?></span>
        </div>
        <div class="progress-bar-wrap">
          <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--muted)">
            <span>Payé : <?=number_format($f['total_paye'],0,',',' ')?> FCFA</span>
            <span><?=$pct?>%</span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?=$pct?>%;background:<?=$colorBar?>"></div></div>
        </div>
        <?php if($f['statut']!=='payee'):?>
        <div style="font-size:.75rem;color:var(--danger);margin-top:4px">Restant : <?=number_format($f['montant_total']-$f['total_paye'],0,',',' ')?> FCFA</div>
        <?php endif;?>
      </div>
      <button class="btn-outline" style="padding:6px 12px;font-size:.78rem;flex-shrink:0" onclick="voirDetail(<?=json_encode($f)?>)">
        <span class="material-icons" style="font-size:14px">visibility</span> Détail
      </button>
    </div>
    <?php endforeach;endif;?>

    <?php if($totalPages>1):?>
    <div class="pagination">
      <span>Affichage <?=min($offset+1,$total)?>–<?=min($offset+$perPage,$total)?> sur <?=$total?></span>
      <div class="page-btns">
        <?php if($page>1):?><a class="page-btn" href="?page=<?=$page-1?>&statut=<?=urlencode($filter)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif;?>
        <?php for($pg=max(1,$page-2);$pg<=min($totalPages,$page+2);$pg++):?><a class="page-btn <?=$pg===$page?'active':''?>" href="?page=<?=$pg?>&statut=<?=urlencode($filter)?>"><?=$pg?></a><?php endfor;?>
        <?php if($page<$totalPages):?><a class="page-btn" href="?page=<?=$page+1?>&statut=<?=urlencode($filter)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif;?>
      </div>
    </div>
    <?php endif;?>
  </main>
</div>

<!-- Modal détail facture -->
<div class="modal-overlay" id="modalDetail">
  <div class="modal">
    <div class="modal-header"><h3 id="detailRef">Facture</h3><button class="modal-close" onclick="closeModal('modalDetail')"><span class="material-icons">close</span></button></div>
    <div id="detailContent"></div>
    <button class="btn-outline" onclick="closeModal('modalDetail')" style="width:100%;margin-top:16px">Fermer</button>
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
function voirDetail(f){
  document.getElementById('detailRef').textContent='Facture #'+String(f.id).padStart(4,'0');
  const pct=f.montant_total>0?Math.min(100,Math.round((f.total_paye/f.montant_total)*100)):0;
  const statuts={payee:'Payée',partielle:'Partielle',impayee:'Impayée'};
  const colors={payee:'var(--success)',partielle:'var(--warning)',impayee:'var(--danger)'};
  document.getElementById('detailContent').innerHTML=`
    <div style="background:var(--blue-light);border-radius:10px;padding:14px;margin-bottom:16px;text-align:center">
      <div style="font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue)">${Number(f.montant_total).toLocaleString('fr-FR')} FCFA</div>
      <span class="status-badge" style="margin-top:6px;display:inline-block;background:${colors[f.statut]}20;color:${colors[f.statut]};border:1px solid ${colors[f.statut]}">${statuts[f.statut]}</span>
    </div>
    <div class="detail-row"><span style="color:var(--muted)">Référence</span><strong>#${String(f.id).padStart(4,'0')}</strong></div>
    <div class="detail-row"><span style="color:var(--muted)">Date</span><span>${new Date(f.date_facture).toLocaleDateString('fr-FR')}</span></div>
    <div class="detail-row"><span style="color:var(--muted)">Montant total</span><strong>${Number(f.montant_total).toLocaleString('fr-FR')} FCFA</strong></div>
    <div class="detail-row"><span style="color:var(--muted)">Montant payé</span><span style="color:var(--success);font-weight:600">${Number(f.total_paye).toLocaleString('fr-FR')} FCFA</span></div>
    <div class="detail-row"><span style="color:var(--muted)">Reste à payer</span><span style="color:var(--danger);font-weight:600">${Number(f.montant_total-f.total_paye).toLocaleString('fr-FR')} FCFA</span></div>
    <div style="margin-top:14px">
      <div style="display:flex;justify-content:space-between;font-size:.75rem;color:var(--muted);margin-bottom:4px"><span>Progression du paiement</span><span>${pct}%</span></div>
      <div style="background:#eef0f6;border-radius:20px;height:10px;overflow:hidden"><div style="height:100%;width:${pct}%;background:${colors[f.statut]};border-radius:20px;transition:width .6s"></div></div>
    </div>
    ${f.statut!=='payee'?`
    <div style="background:#fef9ec;border:1px solid #fcd34d;border-radius:8px;padding:10px 14px;margin-top:14px;font-size:.82rem;color:#92400e;display:flex;flex-direction:column;gap:10px">
      <div><span class="material-icons" style="font-size:14px;vertical-align:middle">info</span> Vous pouvez régler cette facture en ligne ou à la caisse.</div>
      <a href="../modules/paiements/index.php" class="btn-primary" style="text-align:center;text-decoration:none;font-size:.78rem;padding:8px">Payer via Mobile Money (Wave, OM...)</a>
    </div>`:''}
  `;
  openModal('modalDetail');
}
</script>
</body></html>
