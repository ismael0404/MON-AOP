<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['medecin']);
$user = getUser(); $pdo = getDB();
$medecinId = $user['medecin_id'] ?? null;

// Ordonnances = consultations avec prescription non vide
$filter = sanitize($_GET['periode'] ?? '');
$page   = max(1,(int)($_GET['page']??1)); $perPage=10; $offset=($page-1)*$perPage;

$where  = "WHERE c.medecin_id=? AND (c.prescription IS NOT NULL AND c.prescription != '')";
$params = [$medecinId];
if ($filter === 'aujourd') { $where .= " AND DATE(c.date_consult)=CURDATE()"; }
elseif ($filter === 'semaine') { $where .= " AND c.date_consult >= DATE_SUB(NOW(),INTERVAL 7 DAY)"; }
elseif ($filter === 'mois') { $where .= " AND MONTH(c.date_consult)=MONTH(NOW()) AND YEAR(c.date_consult)=YEAR(NOW())"; }

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM consultations c $where");
$stmtC->execute($params); $total=(int)$stmtC->fetchColumn(); $totalPages=ceil($total/$perPage);

$stmt = $pdo->prepare("
    SELECT c.*,u.nom,u.prenom,p.date_naissance,p.groupe_sanguin
    FROM consultations c
    JOIN patients pt ON c.patient_id=pt.id
    JOIN utilisateurs u ON pt.utilisateur_id=u.id
    LEFT JOIN patients p ON pt.id=p.id
    $where ORDER BY c.date_consult DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $ordonnances = $stmt->fetchAll();

// Stats
$s=$pdo->prepare("SELECT COUNT(*) FROM consultations WHERE medecin_id=? AND prescription IS NOT NULL AND prescription!='' AND DATE(date_consult)=CURDATE()");$s->execute([$medecinId]);$nbAuj=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM consultations WHERE medecin_id=? AND prescription IS NOT NULL AND prescription!='' AND MONTH(date_consult)=MONTH(NOW())");$s->execute([$medecinId]);$nbMois=(int)$s->fetchColumn();

$pts = $pdo->query("SELECT p.id,u.nom,u.prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id ORDER BY u.nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Ordonnances</title>
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
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted)}
    .page-btns{display:flex;gap:5px}.page-btn{padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}.page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    /* Modal ordonnance imprimable */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;padding:0;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #eef0f6}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    /* Zone ordonnance (style papier) */
    .ordonnance-paper{padding:28px 32px}
    .ordo-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid var(--blue)}
    .ordo-logo{font-family:'Oswald',sans-serif;font-size:1.4rem;font-weight:700;color:var(--blue)}
    .ordo-subtitle{font-size:.75rem;color:var(--muted);margin-top:2px}
    .ordo-date{text-align:right;font-size:.82rem;color:var(--muted)}
    .ordo-patient{background:#f0f7ff;border-radius:8px;padding:12px 16px;margin-bottom:18px}
    .ordo-patient-name{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);font-weight:700}
    .ordo-section-title{font-family:'Oswald',sans-serif;font-size:.82rem;text-transform:uppercase;color:var(--muted);letter-spacing:.5px;margin-bottom:8px}
    .ordo-prescription{border:1.5px solid #eef0f6;border-radius:8px;padding:14px;font-size:.88rem;line-height:1.7;white-space:pre-wrap;min-height:80px;margin-bottom:16px;background:#fafbff}
    .ordo-diagnostic{font-size:.86rem;color:var(--muted);font-style:italic;margin-bottom:16px;padding:10px;background:#f8f9fc;border-radius:8px}
    .ordo-signature{margin-top:20px;padding-top:14px;border-top:1px solid #eef0f6;display:flex;justify-content:flex-end}
    .ordo-sig-box{text-align:center;padding:10px 20px}
    .ordo-sig-name{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);font-weight:700}
    .ordo-sig-sub{font-size:.72rem;color:var(--muted)}
    /* Nouvelle ordonnance modal */
    .modal2{background:#fff;border-radius:14px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media print{.sidebar,.topbar,.modal-header .modal-close,.btn-print-bar,.pagination,.filter-bar,.stats-row,.page-header,.table-card{display:none!important}.modal-overlay{position:static;background:none}.modal{box-shadow:none;border-radius:0;max-height:none}.ordonnance-paper{padding:10px}}
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
    <a class="nav-item" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span> Mes rendez-vous</a>
    <a class="nav-item" href="dossiers.php"><span class="material-icons">folder_shared</span> Dossiers patients</a>
    <a class="nav-item" href="examens.php"><span class="material-icons">science</span> Examens</a>
    <a class="nav-item active" href="ordonnances.php"><span class="material-icons">description</span> Ordonnances</a>
  
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
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">MD</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Médecin</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
      <div><h1>Ordonnances</h1><p>Historique des prescriptions médicales</p></div>
      <button class="btn-primary" onclick="openModal('modalNewOrdo')"><span class="material-icons">add</span> Nouvelle ordonnance</button>
    </div>

    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">description</span></div><div><div class="stat-value"><?=$total?></div><div class="stat-label">Total ordonnances</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">today</span></div><div><div class="stat-value"><?=$nbAuj?></div><div class="stat-label">Aujourd'hui</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#7c3aed"><span class="material-icons">calendar_month</span></div><div><div class="stat-value"><?=$nbMois?></div><div class="stat-label">Ce mois</div></div></div>
    </div>

    <div class="filter-bar">
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="ordonnances.php">Toutes</a>
      <a class="filter-btn <?= $filter==='aujourd'?'active':'' ?>" href="?periode=aujourd">Aujourd'hui</a>
      <a class="filter-btn <?= $filter==='semaine'?'active':'' ?>" href="?periode=semaine">Cette semaine</a>
      <a class="filter-btn <?= $filter==='mois'?'active':'' ?>" href="?periode=mois">Ce mois</a>
    </div>

    <div class="table-card">
      <div class="table-header"><h3>Liste des ordonnances</h3><span style="font-size:.8rem;color:var(--muted)"><?=$total?> ordonnance(s)</span></div>
      <div style="overflow-x:auto"><table class="klinik-table">
        <thead><tr><th>#</th><th>Patient</th><th>Date</th><th>Diagnostic</th><th>Prescription</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if(empty($ordonnances)):?>
          <tr><td colspan="6" style="text-align:center;padding:28px;color:var(--muted)">Aucune ordonnance trouvée</td></tr>
          <?php else: foreach($ordonnances as $i=>$o):?>
          <tr>
            <td style="color:var(--muted);font-size:.78rem"><?=str_pad($offset+$i+1,3,'0',STR_PAD_LEFT)?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:30px;height:30px;border-radius:50%;background:var(--blue-light);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:.75rem;font-weight:700;color:var(--blue);flex-shrink:0"><?=strtoupper(substr($o['prenom'],0,1).substr($o['nom'],0,1))?></div>
                <strong style="font-size:.88rem"><?=htmlspecialchars($o['prenom'].' '.$o['nom'])?></strong>
              </div>
            </td>
            <td style="font-size:.82rem;color:var(--muted)"><?=date('d/m/Y',strtotime($o['date_consult']))?></td>
            <td style="font-size:.82rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars(substr($o['diagnostic']??'—',0,45))?></td>
            <td style="font-size:.82rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(substr($o['prescription'],0,50))?></td>
            <td>
              <div style="display:flex;gap:5px">
                <button class="btn-outline" style="padding:5px 10px;font-size:.76rem;display:flex;align-items:center;gap:4px"
                  onclick="voirOrdonnance(
                    '<?=htmlspecialchars($o['prenom'].' '.$o['nom'],ENT_QUOTES)?>',
                    '<?=$o['date_naissance']?date('d/m/Y',strtotime($o['date_naissance'])):""?>',
                    '<?=htmlspecialchars(addslashes($o['diagnostic']??''),ENT_QUOTES)?>',
                    '<?=htmlspecialchars(addslashes($o['prescription']),ENT_QUOTES)?>',
                    '<?=date('d/m/Y',strtotime($o['date_consult']))?>'
                  )">
                  <span class="material-icons" style="font-size:14px">visibility</span> Voir
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach;endif;?>
        </tbody>
      </table></div>
      <div class="pagination">
        <span>Affichage <?=min($offset+1,$total)?>–<?=min($offset+$perPage,$total)?> sur <?=$total?></span>
        <div class="page-btns">
          <?php if($page>1):?><a class="page-btn" href="?page=<?=$page-1?>&periode=<?=urlencode($filter)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif;?>
          <?php for($pg=max(1,$page-2);$pg<=min($totalPages,$page+2);$pg++):?><a class="page-btn <?=$pg===$page?'active':''?>" href="?page=<?=$pg?>&periode=<?=urlencode($filter)?>"><?=$pg?></a><?php endfor;?>
          <?php if($page<$totalPages):?><a class="page-btn" href="?page=<?=$page+1?>&periode=<?=urlencode($filter)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif;?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal visualisation ordonnance -->
<div class="modal-overlay" id="modalOrdo">
  <div class="modal">
    <div class="modal-header">
      <h3>Ordonnance médicale</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn-outline" style="padding:6px 12px;font-size:.8rem" onclick="window.print()"><span class="material-icons" style="font-size:14px">print</span> Imprimer</button>
        <button class="modal-close" onclick="closeModal('modalOrdo')"><span class="material-icons">close</span></button>
      </div>
    </div>
    <div class="ordonnance-paper" id="ordoPaper">
      <div class="ordo-header">
        <div><div class="ordo-logo">KLINIK</div><div class="ordo-subtitle">Clinique · Abidjan, Côte d'Ivoire</div></div>
        <div class="ordo-date" id="ordoDate"></div>
      </div>
      <div class="ordo-patient">
        <div class="ordo-patient-name" id="ordoPatientName"></div>
        <div style="font-size:.78rem;color:var(--muted);margin-top:3px" id="ordoPatientInfo"></div>
      </div>
      <div class="ordo-section-title">Diagnostic</div>
      <div class="ordo-diagnostic" id="ordoDiag"></div>
      <div class="ordo-section-title">Prescription médicale</div>
      <div class="ordo-prescription" id="ordoPrescription"></div>
      <div class="ordo-signature">
        <div class="ordo-sig-box">
          <div style="height:40px;border-bottom:1.5px solid var(--blue);margin-bottom:6px;width:140px"></div>
          <div class="ordo-sig-name" id="ordoMedNom"></div>
          <div class="ordo-sig-sub">Médecin · KLINIK</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal nouvelle ordonnance -->
<div class="modal-overlay" id="modalNewOrdo">
  <div class="modal2 modal">
    <div class="modal-header"><h3>Nouvelle ordonnance</h3><button class="modal-close" onclick="closeModal('modalNewOrdo')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="noAlert"></div>
    <p style="font-size:.83rem;color:var(--muted);margin-bottom:14px">Créez une ordonnance en enregistrant une consultation avec prescription.</p>
    <div class="form-group"><label>Patient *</label>
      <select id="noPatient" class="form-control">
        <option value="">Sélectionner...</option>
        <?php foreach($pts as $pt) echo "<option value='{$pt['id']}'>{$pt['prenom']} {$pt['nom']}</option>"; ?>
      </select>
    </div>
    <div class="form-group"><label>Diagnostic</label><textarea id="noDiag" class="form-control" rows="3" placeholder="Diagnostic..."></textarea></div>
    <div class="form-group"><label>Prescription *</label><textarea id="noPrescription" class="form-control" rows="5" placeholder="Ex : Paracétamol 1g — 3x/jour pendant 5 jours&#10;Ibuprofène 400mg — 2x/jour avec repas..."></textarea></div>
    <div class="form-group"><label>Observations</label><textarea id="noObs" class="form-control" rows="2" placeholder="Observations..."></textarea></div>
    <div style="display:flex;gap:12px;margin-top:14px">
      <button class="btn-outline" onclick="closeModal('modalNewOrdo')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveOrdo()" style="flex:1" id="btnNo"><span class="material-icons">save</span> Enregistrer</button>
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

function voirOrdonnance(nom,naiss,diag,prescription,date){
  document.getElementById('ordoDate').innerHTML='Date : <strong>'+date+'</strong>';
  document.getElementById('ordoPatientName').textContent=nom;
  document.getElementById('ordoPatientInfo').textContent=naiss?'Né(e) le '+naiss:'';
  document.getElementById('ordoDiag').textContent=diag||'—';
  document.getElementById('ordoPrescription').textContent=prescription;
  document.getElementById('ordoMedNom').textContent='Dr. '+user.prenom+' '+user.nom;
  openModal('modalOrdo');
}
function saveOrdo(){
  const alertEl=document.getElementById('noAlert');alertEl.className='alert-msg';
  const patient=document.getElementById('noPatient').value;
  const prescription=document.getElementById('noPrescription').value.trim();
  if(!patient||!prescription){alertEl.textContent='Patient et prescription requis.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnNo');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/consultations.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
    action:'create',patient_id:patient,rendez_vous_id:null,
    diagnostic:document.getElementById('noDiag').value,
    prescription:prescription,
    observations:document.getElementById('noObs').value
  })}).then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Ordonnance enregistrée !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalNewOrdo');location.reload()},1200);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">save</span> Enregistrer';}
  });
}
</script>
</body></html>
