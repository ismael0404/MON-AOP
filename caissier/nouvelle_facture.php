<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['caissier']);
$user = getUser(); $pdo = getDB();

$pts = $pdo->query("SELECT p.id,u.nom,u.prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id WHERE u.actif=1 ORDER BY u.nom")->fetchAll();
$consults = $pdo->query("
    SELECT c.id,c.patient_id,c.date_consult,u.nom,u.prenom,c.diagnostic
    FROM consultations c
    JOIN patients p ON c.patient_id=p.id
    JOIN utilisateurs u ON p.utilisateur_id=u.id
    WHERE c.id NOT IN (SELECT COALESCE(consultation_id,0) FROM factures WHERE consultation_id IS NOT NULL)
    ORDER BY c.date_consult DESC LIMIT 50
")->fetchAll();
$dernieres = $pdo->query("
    SELECT f.*,u.nom,u.prenom FROM factures f
    JOIN patients p ON f.patient_id=p.id
    JOIN utilisateurs u ON p.utilisateur_id=u.id
    ORDER BY f.date_facture DESC LIMIT 6
")->fetchAll();
$nbImpayees = (int)$pdo->query("SELECT COUNT(*) FROM factures WHERE statut='impayee'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Nouvelle Facture</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .two-col{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start}
    .card{background:#fff;border-radius:14px;padding:22px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:16px}
    .card-header{display:flex;align-items:center;gap:10px;margin-bottom:16px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .preview-card{background:linear-gradient(135deg,#1a3a6e,#2563eb);border-radius:12px;padding:20px;color:#fff;margin-bottom:16px}
    .prev-label{font-size:.65rem;text-transform:uppercase;letter-spacing:1px;opacity:.6;margin-bottom:2px}
    .prev-value{font-family:'Oswald',sans-serif;font-size:1rem;font-weight:600}
    .prev-divider{border:none;border-top:1px solid rgba(255,255,255,.15);margin:10px 0}
    .prev-total{font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    .hist-item{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f0f4fa}
    .hist-item:last-child{border:none}
    @media(max-width:1000px){.two-col{grid-template-columns:1fr}}
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
    <a class="nav-item active" href="nouvelle_facture.php"><span class="material-icons">add_circle</span> Nouvelle facture</a>
    <a class="nav-item" href="toutes_les_factures.php"><span class="material-icons">receipt_long</span> Toutes les factures</a>
    <a class="nav-item" href="impayes.php"><span class="material-icons">pending_actions</span> Impayés</a>
    <a class="nav-item" href="paiements_recus.php"><span class="material-icons">check_circle</span> Paiements reçus</a>
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
    <div class="page-header" style="margin-bottom:20px"><h1>Nouvelle facture</h1><p>Créer une facture pour un patient</p></div>
    <div class="two-col">
      <!-- Formulaire -->
      <div class="card">
        <div class="card-header"><span class="material-icons" style="color:var(--blue-bright)">receipt_long</span><h3>Informations de facturation</h3></div>
        <div class="alert-msg" id="fAlert"></div>
        <div class="form-group">
          <label>Patient *</label>
          <select id="fPatient" class="form-control" onchange="updatePreview()">
            <option value="">Sélectionner un patient...</option>
            <?php foreach($pts as $pt): ?>
            <option value="<?=$pt['id']?>" data-nom="<?=htmlspecialchars($pt['prenom'].' '.$pt['nom'],ENT_QUOTES)?>"><?=htmlspecialchars($pt['prenom'].' '.$pt['nom'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Consultation associée <span style="color:var(--muted);font-size:.78rem">(optionnel)</span></label>
          <select id="fConsult" class="form-control" onchange="onConsultChange()">
            <option value="">Aucune consultation liée</option>
            <?php foreach($consults as $c): ?>
            <option value="<?=$c['id']?>" data-patient="<?=$c['patient_id']?>" data-nom="<?=htmlspecialchars($c['prenom'].' '.$c['nom'],ENT_QUOTES)?>">
              <?=date('d/m/Y',strtotime($c['date_consult']))?> — <?=htmlspecialchars($c['prenom'].' '.$c['nom'])?> — <?=htmlspecialchars(substr($c['diagnostic']??'Consultation',0,35))?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Montant total (FCFA) *</label>
          <input type="number" id="fMontant" class="form-control" placeholder="Ex: 25000" min="0" step="500" oninput="updatePreview()">
        </div>
        <div class="form-group">
          <label>Description <span style="color:var(--muted);font-size:.78rem">(optionnel)</span></label>
          <textarea id="fDescription" class="form-control" rows="2" placeholder="Ex: Consultation générale + analyses..."></textarea>
        </div>
        <div style="display:flex;gap:12px;margin-top:6px">
          <button class="btn-outline" onclick="resetForm()" style="flex:1"><span class="material-icons">refresh</span> Réinitialiser</button>
          <button class="btn-primary" onclick="saveFacture()" style="flex:2" id="btnF"><span class="material-icons">receipt_long</span> Créer la facture</button>
        </div>
      </div>

      <!-- Aperçu + historique -->
      <div>
        <div class="preview-card">
          <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:1px;opacity:.65;margin-bottom:12px">Aperçu de la facture</div>
          <div class="prev-label">Patient</div><div class="prev-value" id="prevPat">—</div>
          <hr class="prev-divider">
          <div class="prev-label">Date</div><div class="prev-value"><?=date('d/m/Y')?></div>
          <hr class="prev-divider">
          <div class="prev-label">Statut</div><div class="prev-value">Impayée</div>
          <hr class="prev-divider">
          <div class="prev-label">Montant total</div>
          <div class="prev-total" id="prevMontant">0 FCFA</div>
        </div>

        <div class="card">
          <div class="card-header"><span class="material-icons" style="color:var(--blue-bright)">history</span><h3>Dernières factures</h3></div>
          <?php if(empty($dernieres)):?>
          <p style="color:var(--muted);font-size:.83rem;text-align:center;padding:12px">Aucune facture</p>
          <?php else: foreach($dernieres as $f): ?>
          <div class="hist-item">
            <div style="width:36px;height:36px;border-radius:8px;background:var(--blue-light);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <span class="material-icons" style="font-size:18px;color:var(--blue)">receipt</span>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:.84rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($f['prenom'].' '.$f['nom'])?></div>
              <div style="font-size:.72rem;color:var(--muted)"><?=date('d/m/Y',strtotime($f['date_facture']))?></div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <div style="font-family:'Oswald',sans-serif;font-weight:700;font-size:.9rem"><?=number_format($f['montant_total'],0,',',' ')?></div>
              <span class="status-badge <?=$f['statut']==='payee'?'status-active':($f['statut']==='partielle'?'status-pending':'status-inactive')?>" style="font-size:.62rem"><?=$f['statut']==='payee'?'Payée':($f['statut']==='partielle'?'Partielle':'Impayée')?></span>
            </div>
          </div>
          <?php endforeach;endif; ?>
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
function updatePreview(){
  const sel=document.getElementById('fPatient');
  const nom=sel.options[sel.selectedIndex]?.dataset.nom||'—';
  const m=document.getElementById('fMontant').value;
  document.getElementById('prevPat').textContent=nom;
  document.getElementById('prevMontant').textContent=m?Number(m).toLocaleString('fr-FR')+' FCFA':'0 FCFA';
}
function onConsultChange(){
  const opt=document.getElementById('fConsult').options[document.getElementById('fConsult').selectedIndex];
  if(opt.dataset.patient){
    document.getElementById('fPatient').value=opt.dataset.patient;
    document.getElementById('prevPat').textContent=opt.dataset.nom||'—';
  }
}
function resetForm(){
  ['fPatient','fConsult'].forEach(id=>document.getElementById(id).value='');
  ['fMontant','fDescription'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('prevPat').textContent='—';
  document.getElementById('prevMontant').textContent='0 FCFA';
}
function saveFacture(){
  const alertEl=document.getElementById('fAlert');alertEl.className='alert-msg';
  const p=document.getElementById('fPatient').value,m=document.getElementById('fMontant').value;
  if(!p||!m||Number(m)<=0){alertEl.textContent='Patient et montant requis.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnF');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span> Création...';
  fetch('../api/factures.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'create',patient_id:p,montant_total:m,consultation_id:document.getElementById('fConsult').value||null})})
  .then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Facture créée avec succès !';alertEl.classList.add('show','alert-success');setTimeout(()=>window.location.href='toutes_les_factures.php',1400);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">receipt_long</span> Créer la facture';}
  });
}
</script>
</body></html>
