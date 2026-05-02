<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['medecin']);
$user = getUser(); $pdo = getDB();
$medecinId = $user['medecin_id'] ?? null;

$pts = $pdo->query("SELECT p.id,u.nom,u.prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id ORDER BY u.nom")->fetchAll();

$rdvsAuj = [];
if ($medecinId) {
    $s=$pdo->prepare("SELECT r.*,u.nom,u.prenom FROM rendez_vous r JOIN patients pt ON r.patient_id=pt.id JOIN utilisateurs u ON pt.utilisateur_id=u.id WHERE r.medecin_id=? AND DATE(r.date_rdv)=CURDATE() AND r.statut IN('en_attente','confirme') ORDER BY r.date_rdv ASC");
    $s->execute([$medecinId]); $rdvsAuj=$s->fetchAll();
}

$dernieres = [];
if ($medecinId) {
    $s=$pdo->prepare("SELECT c.*,u.nom,u.prenom FROM consultations c JOIN patients p ON c.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id WHERE c.medecin_id=? ORDER BY c.date_consult DESC LIMIT 8");
    $s->execute([$medecinId]); $dernieres=$s->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Nouvelle Consultation</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .two-col{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start}
    .card{background:#fff;border-radius:14px;padding:22px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:16px}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .rdv-auj{display:flex;align-items:center;gap:10px;padding:10px;border-radius:8px;cursor:pointer;transition:background .15s;margin-bottom:6px;border:1.5px solid #eef0f6}
    .rdv-auj:hover{background:#f0f7ff;border-color:var(--blue-bright)}
    .rdv-auj.selected{background:var(--blue-light);border-color:var(--blue-bright)}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
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
    <a class="nav-item active" href="consultation.php"><span class="material-icons">add_circle</span> Nouvelle consultation</a>
    <a class="nav-item" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span> Mes rendez-vous</a>
    <a class="nav-item" href="dossiers.php"><span class="material-icons">folder_shared</span> Dossiers patients</a>
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
    <div class="page-header" style="margin-bottom:20px"><h1>Nouvelle consultation</h1><p>Enregistrer une consultation médicale</p></div>
    <div class="two-col">
      <!-- Formulaire -->
      <div class="card">
        <div class="card-header"><h3>Formulaire de consultation</h3></div>
        <div class="alert-msg" id="cAlert"></div>
        <?php if(!empty($rdvsAuj)):?>
        <div style="background:#f0f7ff;border-radius:10px;padding:14px;margin-bottom:16px">
          <div style="font-size:.78rem;font-weight:700;color:var(--blue);text-transform:uppercase;margin-bottom:10px">RDV du jour — cliquer pour lier</div>
          <?php foreach($rdvsAuj as $r):?>
          <div class="rdv-auj" id="rdv-<?=$r['id']?>" onclick="selectRdv(<?=$r['id']?>,<?=$r['patient_id']?>,'<?=htmlspecialchars($r['prenom'].' '.$r['nom'],ENT_QUOTES)?>')">
            <span class="material-icons" style="color:var(--blue-bright);font-size:18px">event</span>
            <div><strong style="font-size:.86rem"><?=htmlspecialchars($r['prenom'].' '.$r['nom'])?></strong><div style="font-size:.74rem;color:var(--muted)"><?=date('H:i',strtotime($r['date_rdv']))?> · <?=htmlspecialchars($r['motif']??'—')?></div></div>
          </div>
          <?php endforeach;?>
        </div>
        <?php endif;?>
        <input type="hidden" id="cRdvId">
        <div class="form-group"><label>Patient *</label>
          <select id="cPatient" class="form-control">
            <option value="">Sélectionner un patient...</option>
            <?php foreach($pts as $pt) echo "<option value='{$pt['id']}'>{$pt['prenom']} {$pt['nom']}</option>"; ?>
          </select>
        </div>
        <div class="form-group"><label>Diagnostic</label><textarea id="cDiag" class="form-control" rows="4" placeholder="Entrez le diagnostic..."></textarea></div>
        <div class="form-group"><label>Prescription / Traitement</label><textarea id="cPrescription" class="form-control" rows="4" placeholder="Médicaments, posologie, durée..."></textarea></div>
        <div class="form-group"><label>Observations</label><textarea id="cObs" class="form-control" rows="3" placeholder="Observations complémentaires..."></textarea></div>
        <div style="display:flex;gap:12px;margin-top:8px">
          <button class="btn-outline" onclick="resetForm()" style="flex:1"><span class="material-icons">refresh</span> Réinitialiser</button>
          <button class="btn-primary" onclick="saveConsult()" style="flex:1" id="btnSave"><span class="material-icons">save</span> Enregistrer</button>
        </div>
      </div>
      <!-- Historique récent -->
      <div class="card">
        <div class="card-header"><h3>Consultations récentes</h3></div>
        <?php if(empty($dernieres)):?>
        <p style="color:var(--muted);text-align:center;padding:20px 0;font-size:.85rem">Aucune consultation</p>
        <?php else: foreach($dernieres as $c):?>
        <div style="padding:10px 0;border-bottom:1px solid #f0f4fa">
          <div style="display:flex;justify-content:space-between;margin-bottom:3px">
            <strong style="font-size:.86rem"><?=htmlspecialchars($c['prenom'].' '.$c['nom'])?></strong>
            <span style="font-size:.72rem;color:var(--muted)"><?=date('d/m/Y',strtotime($c['date_consult']))?></span>
          </div>
          <div style="font-size:.76rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(substr($c['diagnostic']??'—',0,60))?></div>
        </div>
        <?php endforeach;endif;?>
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
function selectRdv(rdvId,patientId,nom){
  document.querySelectorAll('.rdv-auj').forEach(el=>el.classList.remove('selected'));
  document.getElementById('rdv-'+rdvId).classList.add('selected');
  document.getElementById('cRdvId').value=rdvId;
  document.getElementById('cPatient').value=patientId;
}
function resetForm(){
  document.getElementById('cRdvId').value='';
  document.getElementById('cPatient').value='';
  ['cDiag','cPrescription','cObs'].forEach(id=>document.getElementById(id).value='');
  document.querySelectorAll('.rdv-auj').forEach(el=>el.classList.remove('selected'));
}
function saveConsult(){
  const alertEl=document.getElementById('cAlert');alertEl.className='alert-msg';
  const patient=document.getElementById('cPatient').value;
  if(!patient){alertEl.textContent='Veuillez sélectionner un patient.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnSave');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span> Enregistrement...';
  fetch('../api/consultations.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
    action:'create',patient_id:patient,rendez_vous_id:document.getElementById('cRdvId').value||null,
    diagnostic:document.getElementById('cDiag').value,prescription:document.getElementById('cPrescription').value,observations:document.getElementById('cObs').value
  })}).then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Consultation enregistrée avec succès !';alertEl.classList.add('show','alert-success');resetForm();btn.disabled=false;btn.innerHTML='<span class="material-icons">save</span> Enregistrer';}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">save</span> Enregistrer';}
  });
}
</script>
</body></html>
