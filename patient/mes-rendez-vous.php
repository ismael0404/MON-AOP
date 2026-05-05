<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['patient']);
$user = getUser(); $pdo = getDB();
$patientId = $user['patient_id'] ?? null;

$filter = sanitize($_GET['statut'] ?? '');
$page   = max(1,(int)($_GET['page']??1)); $perPage=10; $offset=($page-1)*$perPage;

$where  = "WHERE r.patient_id=?"; $params = [$patientId];
if ($filter) { $where .= " AND r.statut=?"; $params[] = $filter; }

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous r $where");
$stmtC->execute($params); $total=(int)$stmtC->fetchColumn(); $totalPages=ceil($total/$perPage);

$stmt = $pdo->prepare("
    SELECT r.*, u.nom as med_nom, u.prenom as med_prenom, m.specialite
    FROM rendez_vous r
    JOIN medecins m ON r.medecin_id=m.id
    JOIN utilisateurs u ON m.utilisateur_id=u.id
    $where ORDER BY r.date_rdv DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $rdvs = $stmt->fetchAll();

$s=$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id=? AND statut='confirme'");$s->execute([$patientId]);$nbConf=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id=? AND statut='en_attente'");$s->execute([$patientId]);$nbAtt=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id=? AND DATE(date_rdv)>=CURDATE() AND statut IN('en_attente','confirme')");$s->execute([$patientId]);$nbAvenir=(int)$s->fetchColumn();

$nbImpayees = (int)$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'")->execute([$patientId]) && ($si=$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'")) && $si->execute([$patientId]) ? $si->fetchColumn() : 0;
$si=$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'");$si->execute([$patientId]);$nbImpayees=(int)$si->fetchColumn();

$meds = $pdo->query("SELECT m.id,u.nom,u.prenom,m.specialite FROM medecins m JOIN utilisateurs u ON m.utilisateur_id=u.id WHERE u.actif=1 ORDER BY u.nom")->fetchAll();
$statutColors=['en_attente'=>'status-pending','en_attente_paiement'=>'status-warning','confirme'=>'status-active','termine'=>'status-done','annule'=>'status-inactive'];
$statutLabels=['en_attente'=>'En attente','en_attente_paiement'=>'En attente paiement','confirme'=>'Confirmé','termine'=>'Terminé','annule'=>'Annulé'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Mes Rendez-vous</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);transition:transform .2s;animation:fadeUp .5s ease both;opacity:0}
    .stat-card:hover{transform:translateY(-2px)}.stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
    .filter-btn{padding:7px 16px;border-radius:20px;border:1.5px solid #eef0f6;background:#fff;font-size:.82rem;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text)}
    .filter-btn:hover,.filter-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .rdv-card{background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:16px;margin-bottom:12px;display:flex;align-items:center;gap:16px;box-shadow:0 2px 8px rgba(26,58,110,.04);transition:transform .2s,box-shadow .2s;animation:fadeUp .5s ease both;opacity:0}
    .rdv-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,58,110,.1)}
    .rdv-date-box{background:var(--blue-light);border-radius:10px;padding:10px 14px;text-align:center;min-width:56px;flex-shrink:0}
    .rdv-day{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--blue);line-height:1}
    .rdv-month{font-size:.65rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-top:2px}
    .rdv-time{font-size:.72rem;color:var(--blue-bright);font-weight:600;margin-top:3px}
    .med-avatar{width:38px;height:38px;border-radius:50%;background:#cffafe;display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:.82rem;font-weight:700;color:#0e7490;flex-shrink:0}
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted)}
    .page-btns{display:flex;gap:5px}.page-btn{padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}.page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:700px){.stats-row{grid-template-columns:1fr 1fr}.rdv-card{flex-wrap:wrap}}
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
    <a class="nav-item active" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span> Mes rendez-vous</a>
    <a class="nav-item" href="mon-dossier.php"><span class="material-icons">folder_shared</span> Mon dossier médical</a>
    <a class="nav-item" href="mes-examens.php"><span class="material-icons">science</span> Mes examens</a>
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
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbImpayees>0):?><span class="notif-badge"><?=$nbImpayees?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">PT</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Patient</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div><h1>Mes rendez-vous</h1><p>Historique et suivi de vos rendez-vous médicaux</p></div>
      <button class="btn-primary" onclick="openModal('modalRDV')"><span class="material-icons">add</span> Prendre un RDV</button>
    </div>
    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">upcoming</span></div><div><div class="stat-value"><?=$nbAvenir?></div><div class="stat-label">À venir</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">event_available</span></div><div><div class="stat-value"><?=$nbConf?></div><div class="stat-label">Confirmés</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending</span></div><div><div class="stat-value"><?=$nbAtt?></div><div class="stat-label">En attente</div></div></div>
    </div>
    <div class="filter-bar">
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="mes-rendez-vous.php">Tous</a>
      <a class="filter-btn <?= $filter==='confirme'?'active':'' ?>" href="?statut=confirme">Confirmés</a>
      <a class="filter-btn <?= $filter==='en_attente'?'active':'' ?>" href="?statut=en_attente">En attente</a>
      <a class="filter-btn <?= $filter==='termine'?'active':'' ?>" href="?statut=termine">Terminés</a>
      <a class="filter-btn <?= $filter==='annule'?'active':'' ?>" href="?statut=annule">Annulés</a>
    </div>

    <?php if(empty($rdvs)):?>
    <div style="background:#fff;border-radius:14px;padding:48px;text-align:center;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05)">
      <span class="material-icons" style="font-size:48px;color:var(--border)">event_busy</span>
      <p style="color:var(--muted);margin-top:12px;font-size:.9rem">Aucun rendez-vous trouvé</p>
      <button class="btn-primary" onclick="openModal('modalRDV')" style="margin-top:16px"><span class="material-icons">add</span> Prendre un rendez-vous</button>
    </div>
    <?php else: foreach($rdvs as $i=>$r):?>
    <div class="rdv-card" style="animation-delay:<?=$i*0.06?>s">
      <div class="rdv-date-box">
        <div class="rdv-day"><?=date('d',strtotime($r['date_rdv']))?></div>
        <div class="rdv-month"><?=date('M',strtotime($r['date_rdv']))?></div>
        <div class="rdv-time"><?=date('H:i',strtotime($r['date_rdv']))?></div>
      </div>
      <div class="med-avatar"><?=strtoupper(substr($r['med_prenom'],0,1).substr($r['med_nom'],0,1))?></div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:.95rem">Dr. <?=htmlspecialchars($r['med_prenom'].' '.$r['med_nom'])?></div>
        <div style="font-size:.78rem;color:var(--muted);margin-top:2px"><?=htmlspecialchars($r['specialite']??'Médecine générale')?></div>
        <?php if($r['motif']):?><div style="font-size:.78rem;color:#0891b2;margin-top:5px;display:flex;align-items:center;gap:4px"><span class="material-icons" style="font-size:14px">chat_bubble_outline</span><?=htmlspecialchars($r['motif'])?></div><?php endif;?>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0">
        <span class="status-badge <?=$statutColors[$r['statut']]?>"><?=$statutLabels[$r['statut']]?></span>
        <?php if($r['statut']==='en_attente'):?>
        <button class="btn-outline" style="padding:4px 10px;font-size:.74rem;color:var(--danger);border-color:var(--danger)" onclick="annulerRdv(<?=$r['id']?>)">
          <span class="material-icons" style="font-size:13px">close</span> Annuler
        </button>
        <?php endif;?>
      </div>
    </div>
    <?php endforeach;endif;?>

    <?php if($totalPages > 1):?>
    <div class="pagination" style="background:#fff;border-radius:12px;border:1.5px solid #eef0f6;margin-top:4px">
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

<!-- Modal prendre RDV -->
<div class="modal-overlay" id="modalRDV">
  <div class="modal">
    <div class="modal-header"><h3>Prendre un rendez-vous</h3><button class="modal-close" onclick="closeModal('modalRDV')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="rdvAlert"></div>
    <div class="form-group"><label>Médecin *</label>
      <select id="rdvMed" class="form-control">
        <option value="">Sélectionner un médecin...</option>
        <?php foreach($meds as $m): ?>
        <option value="<?=$m['id']?>">Dr. <?=htmlspecialchars($m['prenom'].' '.$m['nom'])?> — <?=htmlspecialchars($m['specialite']??'Médecine générale')?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Date et heure *</label><input type="datetime-local" id="rdvDate" class="form-control" min="<?=date('Y-m-d\TH:i')?>"></div>
    <div class="form-group"><label>Motif de la consultation</label><textarea id="rdvMotif" class="form-control" rows="3" placeholder="Décrivez brièvement votre motif..."></textarea></div>
    <div style="display:flex;gap:12px;margin-top:14px">
      <button class="btn-outline" onclick="closeModal('modalRDV')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveRDV()" style="flex:1" id="btnRDV"><span class="material-icons">event</span> Confirmer</button>
    </div>
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
function saveRDV(){
  const alertEl=document.getElementById('rdvAlert');alertEl.className='alert-msg';
  const med=document.getElementById('rdvMed').value,date=document.getElementById('rdvDate').value;
  if(!med||!date){alertEl.textContent='Médecin et date sont requis.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnRDV');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/rendez-vous.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create',medecin_id:med,date_rdv:date,motif:document.getElementById('rdvMotif').value})})
  .then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Rendez-vous enregistré !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalRDV');location.reload()},1200);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">event</span> Confirmer';}
  });
}
function annulerRdv(id){
  if(!confirm('Annuler ce rendez-vous ?'))return;
  fetch('../api/rendez-vous.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'updateStatut',id,statut:'annule'})})
  .then(r=>r.json()).then(d=>{if(d.success)location.reload();});
}
</script>
</body></html>
