<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['medecin']);
$user = getUser(); $pdo = getDB();
$medecinId = $user['medecin_id'] ?? null;
$search = sanitize($_GET['search'] ?? '');
$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : null;

$where = "WHERE 1=1"; $params = [$medecinId];
if ($search) { $where .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }

$stmt = $pdo->prepare("
    SELECT p.*,u.nom,u.prenom,u.email,
           COUNT(DISTINCT c.id) as nb_consult,
           MAX(c.date_consult) as derniere_consult
    FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id
    LEFT JOIN consultations c ON p.id=c.patient_id AND c.medecin_id=?
    $where GROUP BY p.id
    HAVING nb_consult > 0 OR ? IS NULL
    ORDER BY u.nom ASC
");
// Requête simplifiée : tous les patients du médecin
$stmt2 = $pdo->prepare("
    SELECT p.*,u.nom,u.prenom,u.email,
           COUNT(DISTINCT c.id) as nb_consult,
           MAX(c.date_consult) as derniere_consult
    FROM consultations c
    JOIN patients p ON c.patient_id=p.id
    JOIN utilisateurs u ON p.utilisateur_id=u.id
    WHERE c.medecin_id=?
    " . ($search ? " AND (u.nom LIKE ? OR u.prenom LIKE ?)" : "") . "
    GROUP BY p.id ORDER BY u.nom ASC
");
$params2 = [$medecinId];
if ($search) { $params2[]="%$search%"; $params2[]="%$search%"; }
$stmt2->execute($params2); $patients = $stmt2->fetchAll();

// Dossier détail
$patientDetail = null; $consults = []; $examens = [];
if ($pid) {
    $s=$pdo->prepare("SELECT p.*,u.nom,u.prenom,u.email,u.telephone FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id WHERE p.id=?");
    $s->execute([$pid]); $patientDetail=$s->fetch();
    $cs=$pdo->prepare("SELECT c.* FROM consultations c WHERE c.patient_id=? AND c.medecin_id=? ORDER BY c.date_consult DESC");
    $cs->execute([$pid,$medecinId]); $consults=$cs->fetchAll();
    $ex=$pdo->prepare("SELECT e.* FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.patient_id=? AND c.medecin_id=? ORDER BY e.date_demande DESC LIMIT 5");
    $ex->execute([$pid,$medecinId]); $examens=$ex->fetchAll();
}
$allConsults = $pdo->query("SELECT p.id,u.nom,u.prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id ORDER BY u.nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Dossiers Patients</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .two-col{display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start}
    .card{background:#fff;border-radius:14px;padding:20px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:16px}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase}
    .search-wrap{position:relative;margin-bottom:12px}
    .search-wrap .material-icons{position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:17px;color:#9ca3af}
    .search-wrap input{width:100%;padding:9px 12px 9px 32px;border:1.5px solid #eef0f6;border-radius:8px;font-size:.86rem;outline:none;background:#f8f9fc}
    .search-wrap input:focus{border-color:var(--blue-bright);background:#fff}
    .patient-item{display:flex;align-items:center;gap:10px;padding:10px;border-radius:10px;cursor:pointer;transition:background .15s;text-decoration:none;color:var(--text);margin-bottom:4px}
    .patient-item:hover{background:#f0f7ff}.patient-item.active{background:var(--blue-light);border:1.5px solid var(--blue-bright)}
    .patient-avatar{width:34px;height:34px;border-radius:50%;background:var(--blue-light);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:.8rem;font-weight:700;color:var(--blue);flex-shrink:0}
    .dossier-header{background:linear-gradient(135deg,var(--blue),#2563eb);border-radius:12px;padding:18px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:16px}
    .dossier-avatar{width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:1.1rem;font-weight:700;flex-shrink:0}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
    .info-block{background:#f8f9fc;border-radius:8px;padding:10px}
    .ib-label{font-size:.67rem;font-weight:700;text-transform:uppercase;color:var(--muted)}
    .ib-value{font-size:.86rem;color:var(--text);margin-top:2px;font-weight:600}
    .consult-item{padding:10px 0;border-bottom:1px solid #f0f4fa}.consult-item:last-child{border:none}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:500px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:1000px){.two-col{grid-template-columns:1fr}}
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
    <a class="nav-item active" href="dossiers.php"><span class="material-icons">folder_shared</span> Dossiers patients</a>
    <a class="nav-item" href="examens.php"><span class="material-icons">science</span> Examens</a>
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
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">MD</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Médecin</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Dossiers patients</h1><p><?=count($patients)?> patient(s) dans votre registre</p></div>
    <div class="two-col">
      <!-- Liste -->
      <div class="card">
        <div class="search-wrap">
          <span class="material-icons">search</span>
          <input type="text" id="searchInput" placeholder="Rechercher..." value="<?=htmlspecialchars($search)?>" onkeyup="debounceSearch()">
        </div>
        <?php if(empty($patients)):?>
        <p style="color:var(--muted);text-align:center;padding:20px 0;font-size:.85rem">Aucun patient trouvé</p>
        <?php else: foreach($patients as $p):?>
        <a class="patient-item <?= $pid==$p['id']?'active':'' ?>" href="dossiers.php?pid=<?=$p['id']?>&search=<?=urlencode($search)?>">
          <div class="patient-avatar"><?=strtoupper(substr($p['prenom'],0,1).substr($p['nom'],0,1))?></div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:.87rem"><?=htmlspecialchars($p['prenom'].' '.$p['nom'])?></div>
            <div style="font-size:.73rem;color:var(--muted)"><?=$p['nb_consult']?> consult. · <?=$p['derniere_consult']?date('d/m/Y',strtotime($p['derniere_consult'])):'—'?></div>
          </div>
        </a>
        <?php endforeach;endif;?>
      </div>
      <!-- Dossier -->
      <div>
        <?php if($patientDetail):?>
        <div class="card">
          <div class="dossier-header">
            <div class="dossier-avatar"><?=strtoupper(substr($patientDetail['prenom'],0,1).substr($patientDetail['nom'],0,1))?></div>
            <div>
              <div style="font-family:'Oswald',sans-serif;font-size:1rem;font-weight:700"><?=htmlspecialchars($patientDetail['prenom'].' '.$patientDetail['nom'])?></div>
              <div style="font-size:.72rem;opacity:.7">#PT<?=str_pad($patientDetail['id'],8,'0',STR_PAD_LEFT)?></div>
            </div>
          </div>
          <div class="info-grid">
            <div class="info-block"><div class="ib-label">Naissance</div><div class="ib-value"><?=$patientDetail['date_naissance']?date('d/m/Y',strtotime($patientDetail['date_naissance'])):'—'?></div></div>
            <div class="info-block"><div class="ib-label">Groupe sanguin</div><div class="ib-value"><?=htmlspecialchars($patientDetail['groupe_sanguin']??'—')?></div></div>
            <div class="info-block"><div class="ib-label">Sexe</div><div class="ib-value"><?=$patientDetail['sexe']==='M'?'Masculin':($patientDetail['sexe']==='F'?'Féminin':'—')?></div></div>
            <div class="info-block"><div class="ib-label">Ville</div><div class="ib-value"><?=htmlspecialchars($patientDetail['ville']??'—')?></div></div>
          </div>
          <div style="display:flex;gap:10px;margin-bottom:16px">
            <button class="btn-primary" style="flex:1" onclick="openDemandeExamen(<?=$patientDetail['id']?>)"><span class="material-icons">science</span> Demander examen</button>
            <a href="consultation.php" class="btn-outline" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none"><span class="material-icons">add_circle</span> Consultation</a>
          </div>
          <div class="card-header" style="margin-bottom:10px"><h3>Historique consultations</h3></div>
          <?php if(empty($consults)):?><p style="color:var(--muted);font-size:.83rem;text-align:center;padding:12px">Aucune consultation</p>
          <?php else: foreach($consults as $c):?>
          <div class="consult-item">
            <div style="display:flex;justify-content:space-between;margin-bottom:3px">
              <strong style="font-size:.84rem"><?=date('d/m/Y',strtotime($c['date_consult']))?></strong>
            </div>
            <div style="font-size:.77rem;color:var(--muted)"><?=htmlspecialchars(substr($c['diagnostic']??'—',0,70))?></div>
            <?php if($c['prescription']):?><div style="font-size:.74rem;color:#0891b2;margin-top:2px">💊 <?=htmlspecialchars(substr($c['prescription'],0,55))?></div><?php endif;?>
          </div>
          <?php endforeach;endif;?>
        </div>
        <?php else:?>
        <div class="card" style="text-align:center;padding:40px 20px">
          <span class="material-icons" style="font-size:42px;color:var(--border)">folder_shared</span>
          <p style="color:var(--muted);margin-top:10px;font-size:.88rem">Sélectionnez un patient pour voir son dossier</p>
        </div>
        <?php endif;?>
      </div>
    </div>
  </main>
</div>

<!-- Modal demande examen -->
<div class="modal-overlay" id="modalExamen">
  <div class="modal">
    <div class="modal-header"><h3>Demander un examen</h3><button class="modal-close" onclick="closeModal('modalExamen')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="exAlert"></div>
    <input type="hidden" id="exPatientId">
    <div class="form-group"><label>Consultation associée *</label>
      <select id="exConsult" class="form-control">
        <option value="">Sélectionner une consultation...</option>
        <?php foreach($consults as $c): ?>
        <option value="<?=$c['id']?>"><?=date('d/m/Y',strtotime($c['date_consult']))?> — <?=htmlspecialchars(substr($c['diagnostic']??'—',0,40))?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Type d'examen *</label><input type="text" id="exType" class="form-control" placeholder="Ex: Analyse de sang, Radiographie..."></div>
    <div class="form-group"><label>Priorité</label>
      <select id="exPrio" class="form-control"><option value="normale">Normale</option><option value="urgente">Urgente</option></select>
    </div>
    <div style="display:flex;gap:12px;margin-top:14px">
      <button class="btn-outline" onclick="closeModal('modalExamen')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveExamen()" style="flex:1" id="btnEx"><span class="material-icons">send</span> Envoyer au labo</button>
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
let st;function debounceSearch(){clearTimeout(st);st=setTimeout(()=>window.location.href='dossiers.php?search='+encodeURIComponent(document.getElementById('searchInput').value)<?=$pid?'+"&pid='.$pid.'"':''?>,400);}
function openDemandeExamen(pid){document.getElementById('exPatientId').value=pid;document.getElementById('exType').value='';document.getElementById('exAlert').className='alert-msg';openModal('modalExamen');}
function saveExamen(){
  const alertEl=document.getElementById('exAlert');alertEl.className='alert-msg';
  const type=document.getElementById('exType').value.trim(),consult=document.getElementById('exConsult').value;
  if(!type||!consult){alertEl.textContent='Type d\'examen et consultation requis.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnEx');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/examens.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'demander',consultation_id:consult,type_examen:type,priorite:document.getElementById('exPrio').value})})
  .then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Examen envoyé au laboratoire !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalExamen');location.reload()},1500);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">send</span> Envoyer au labo';}
  });
}
</script>
</body></html>
