<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['medecin']);
$user = getUser(); $pdo = getDB();
$medecinId = $user['medecin_id'] ?? null;

$filter = sanitize($_GET['statut'] ?? '');
$page   = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;

$where  = "WHERE r.medecin_id=?"; $params = [$medecinId];
if ($filter) { $where .= " AND r.statut=?"; $params[] = $filter; }

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous r $where");
$stmtC->execute($params); $total=(int)$stmtC->fetchColumn(); $totalPages=ceil($total/$perPage);

$stmt = $pdo->prepare("
    SELECT r.*,u.nom,u.prenom,p.groupe_sanguin
    FROM rendez_vous r
    JOIN patients pt ON r.patient_id=pt.id
    JOIN utilisateurs u ON pt.utilisateur_id=u.id
    LEFT JOIN patients p ON pt.id=p.id
    $where ORDER BY r.date_rdv DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $rdvs = $stmt->fetchAll();

$s=$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id=? AND DATE(date_rdv)=CURDATE()");$s->execute([$medecinId]);$nbAuj=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id=? AND statut='en_attente'");$s->execute([$medecinId]);$nbAtt=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id=? AND statut='confirme'");$s->execute([$medecinId]);$nbConf=(int)$s->fetchColumn();

$statutColors=['en_attente'=>'status-pending','confirme'=>'status-active','termine'=>'status-done','annule'=>'status-inactive'];
$statutLabels=['en_attente'=>'En attente','confirme'=>'Confirmé','termine'=>'Terminé','annule'=>'Annulé'];

// Patients pour modal consultation rapide
$pts=$pdo->query("SELECT p.id,u.nom,u.prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id ORDER BY u.nom")->fetchAll();
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
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .action-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;border:none;cursor:pointer;transition:all .18s}
    .action-btn .material-icons{font-size:15px}
    .btn-confirm{background:#d1fae5;color:#065f46}.btn-confirm:hover{background:#065f46;color:#fff}
    .btn-cancel{background:#fee2e2;color:#991b1b}.btn-cancel:hover{background:#991b1b;color:#fff}
    .btn-consult{background:#dbeafe;color:#1d4ed8}.btn-consult:hover{background:#1d4ed8;color:#fff}
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted)}
    .page-btns{display:flex;gap:5px}.page-btn{padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}.page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:900px){.stats-row{grid-template-columns:1fr 1fr}}
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
    <a class="nav-item active" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span> Mes rendez-vous</a>
    <a class="nav-item" href="dossiers.php"><span class="material-icons">folder_shared</span> Dossiers patients</a>
    <a class="nav-item" href="examens.php"><span class="material-icons">science</span> Examens</a>
    <a class="nav-item" href="ordonnances.php"><span class="material-icons">description</span> Ordonnances</a>
  </nav>
  <div class="sidebar-footer"><a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a></div>
</aside>
<div class="main-wrapper">
  <header class="topbar">
    <a class="topbar-logo" href="dashboard.php"><img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'"><span class="logo-name">KLINIK</span></a>
    <div class="topbar-left"><button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button></div>
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher patient, diagnostic..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">MD</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Médecin</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
      <div><h1>Mes rendez-vous</h1><p>Gestion de votre agenda médical</p></div>
      <button class="btn-primary" onclick="openModal('modalConsult')"><span class="material-icons">add</span> Nouvelle consultation</button>
    </div>
    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">today</span></div><div><div class="stat-value"><?=$nbAuj?></div><div class="stat-label">Aujourd'hui</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending</span></div><div><div class="stat-value"><?=$nbAtt?></div><div class="stat-label">En attente</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">event_available</span></div><div><div class="stat-value"><?=$nbConf?></div><div class="stat-label">Confirmés</div></div></div>
    </div>
    <div class="filter-bar">
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="mes-rendez-vous.php">Tous</a>
      <a class="filter-btn <?= $filter==='en_attente'?'active':'' ?>" href="?statut=en_attente">En attente</a>
      <a class="filter-btn <?= $filter==='confirme'?'active':'' ?>" href="?statut=confirme">Confirmés</a>
      <a class="filter-btn <?= $filter==='termine'?'active':'' ?>" href="?statut=termine">Terminés</a>
      <a class="filter-btn <?= $filter==='annule'?'active':'' ?>" href="?statut=annule">Annulés</a>
    </div>
    <div class="table-card">
      <div class="table-header"><h3>Liste des rendez-vous</h3><span style="font-size:.8rem;color:var(--muted)"><?=$total?> RDV</span></div>
      <div style="overflow-x:auto"><table class="klinik-table">
        <thead><tr><th>Date/Heure</th><th>Patient</th><th>Groupe</th><th>Motif</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if(empty($rdvs)):?><tr><td colspan="6" style="text-align:center;padding:28px;color:var(--muted)">Aucun rendez-vous</td></tr>
          <?php else: foreach($rdvs as $r):?>
          <tr>
            <td><div style="font-weight:600;font-size:.88rem"><?=date('d/m/Y',strtotime($r['date_rdv']))?></div><div style="font-size:.75rem;color:var(--muted)"><?=date('H:i',strtotime($r['date_rdv']))?></div></td>
            <td><strong><?=htmlspecialchars($r['prenom'].' '.$r['nom'])?></strong></td>
            <td><?=$r['groupe_sanguin']?'<span style="background:#fee2e2;color:#991b1b;padding:2px 7px;border-radius:10px;font-size:.7rem;font-weight:700">'.htmlspecialchars($r['groupe_sanguin']).'</span>':'—'?></td>
            <td style="font-size:.82rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['motif']??'—')?></td>
            <td><span class="status-badge <?=$statutColors[$r['statut']]?>"><?=$statutLabels[$r['statut']]?></span></td>
            <td><div style="display:flex;gap:4px">
              <?php if($r['statut']==='en_attente'):?>
              <button class="action-btn btn-confirm" onclick="updateRdv(<?=$r['id']?>,'confirme')" title="Confirmer"><span class="material-icons">check</span></button>
              <button class="action-btn btn-cancel"  onclick="updateRdv(<?=$r['id']?>,'annule')"   title="Annuler"><span class="material-icons">close</span></button>
              <?php endif;?>
              <?php if(in_array($r['statut'],['en_attente','confirme'])):?>
              <button class="action-btn btn-consult" onclick="openConsultModal(<?=$r['id']?>,<?=$r['patient_id']?>,'<?=htmlspecialchars($r['prenom'].' '.$r['nom'],ENT_QUOTES)?>')" title="Consultation"><span class="material-icons">medical_services</span></button>
              <?php endif;?>
            </div></td>
          </tr>
          <?php endforeach;endif;?>
        </tbody>
      </table></div>
      <div class="pagination">
        <span>Affichage <?=min($offset+1,$total)?>–<?=min($offset+$perPage,$total)?> sur <?=$total?></span>
        <div class="page-btns">
          <?php if($page>1):?><a class="page-btn" href="?page=<?=$page-1?>&statut=<?=urlencode($filter)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif;?>
          <?php for($pg=max(1,$page-2);$pg<=min($totalPages,$page+2);$pg++):?><a class="page-btn <?=$pg===$page?'active':''?>" href="?page=<?=$pg?>&statut=<?=urlencode($filter)?>"><?=$pg?></a><?php endfor;?>
          <?php if($page<$totalPages):?><a class="page-btn" href="?page=<?=$page+1?>&statut=<?=urlencode($filter)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif;?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal consultation rapide depuis RDV -->
<div class="modal-overlay" id="modalConsult">
  <div class="modal">
    <div class="modal-header"><h3>Nouvelle consultation</h3><button class="modal-close" onclick="closeModal('modalConsult')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="cAlert"></div>
    <div id="cInfo" style="background:#f0f7ff;border-radius:8px;padding:10px;font-size:.83rem;color:var(--blue);margin-bottom:14px"></div>
    <input type="hidden" id="cRdvId"><input type="hidden" id="cPatientId">
    <div class="form-group"><label>Patient *</label>
      <select id="cPatient" class="form-control">
        <option value="">Sélectionner un patient...</option>
        <?php foreach($pts as $pt) echo "<option value='{$pt['id']}'>{$pt['prenom']} {$pt['nom']}</option>"; ?>
      </select>
    </div>
    <div class="form-group"><label>Diagnostic</label><textarea id="cDiag" class="form-control" rows="3" placeholder="Diagnostic..."></textarea></div>
    <div class="form-group"><label>Prescription</label><textarea id="cPrescription" class="form-control" rows="3" placeholder="Médicaments, posologie..."></textarea></div>
    <div class="form-group"><label>Observations</label><textarea id="cObs" class="form-control" rows="2" placeholder="Observations..."></textarea></div>
    <div style="display:flex;gap:12px;margin-top:14px">
      <button class="btn-outline" onclick="closeModal('modalConsult')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveConsult()" style="flex:1" id="btnC"><span class="material-icons">save</span> Enregistrer</button>
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
function updateRdv(id,statut){
  fetch('../api/rendez-vous.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'updateStatut',id,statut})})
  .then(r=>r.json()).then(d=>{if(d.success)location.reload();});
}
function openConsultModal(rdvId,patientId,nom){
  document.getElementById('cRdvId').value=rdvId;
  document.getElementById('cPatientId').value=patientId;
  document.getElementById('cPatient').value=patientId;
  document.getElementById('cInfo').textContent='RDV avec : '+nom;
  ['cDiag','cPrescription','cObs'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('cAlert').className='alert-msg';
  openModal('modalConsult');
}
function saveConsult(){
  const alertEl=document.getElementById('cAlert');alertEl.className='alert-msg';
  const patient=document.getElementById('cPatient').value;
  if(!patient){alertEl.textContent='Veuillez sélectionner un patient.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnC');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/consultations.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
    action:'create',patient_id:patient,rendez_vous_id:document.getElementById('cRdvId').value||null,
    diagnostic:document.getElementById('cDiag').value,prescription:document.getElementById('cPrescription').value,observations:document.getElementById('cObs').value
  })}).then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Consultation enregistrée !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalConsult');location.reload()},1200);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">save</span> Enregistrer';}
  });
}
</script>
</body></html>
