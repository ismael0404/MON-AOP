<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['laborantin']);
$user=getUser();$pdo=getDB();$uid=$user['id'];

function q($pdo,$sql,$p=[]){$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchColumn();}

$nbAttente =q($pdo,"SELECT COUNT(*) FROM examens WHERE statut='en_attente'");
$nbEnCours =q($pdo,"SELECT COUNT(*) FROM examens WHERE statut='en_cours'");
$nbTransmis=q($pdo,"SELECT COUNT(*) FROM examens WHERE statut='transmis' AND DATE(date_resultat)=CURDATE()");
$nbUrgents =q($pdo,"SELECT COUNT(*) FROM examens WHERE statut='en_attente' AND priorite='urgente'");
$nbMesTransmis=q($pdo,"SELECT COUNT(*) FROM examens WHERE laborantin_id=?",[$uid]);

// Examens à traiter
$examens=$pdo->query("
    SELECT e.*,u.nom,u.prenom,um.nom as med_nom,um.prenom as med_prenom
    FROM examens e
    JOIN consultations c ON e.consultation_id=c.id
    JOIN patients p ON c.patient_id=p.id
    JOIN utilisateurs u ON p.utilisateur_id=u.id
    JOIN medecins m ON c.medecin_id=m.id
    JOIN utilisateurs um ON m.utilisateur_id=um.id
    WHERE e.statut!='transmis'
    ORDER BY e.priorite DESC,e.date_demande ASC LIMIT 12
")->fetchAll();

// Mes derniers résultats transmis
$mesResultats=$pdo->prepare("
    SELECT e.*,u.nom,u.prenom
    FROM examens e
    JOIN consultations c ON e.consultation_id=c.id
    JOIN patients p ON c.patient_id=p.id
    JOIN utilisateurs u ON p.utilisateur_id=u.id
    WHERE e.laborantin_id=? AND e.statut='transmis'
    ORDER BY e.date_resultat DESC LIMIT 5
");
$mesResultats->execute([$uid]);$mesResultats=$mesResultats->fetchAll();

// Activité 7 jours
$jours=[];$valsTransmis=[];$valsTotal=[];
for($i=6;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-$i days"));
    $jours[]=date('d/m',strtotime($d));
    $valsTransmis[]=q($pdo,"SELECT COUNT(*) FROM examens WHERE statut='transmis' AND DATE(date_resultat)=?",[$d]);
    $valsTotal[]=q($pdo,"SELECT COUNT(*) FROM examens WHERE DATE(date_demande)=?",[$d]);
}

// Répartition types examens
$types=$pdo->query("SELECT type_examen,COUNT(*) as nb FROM examens GROUP BY type_examen ORDER BY nb DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Espace Laborantin</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    .dash-grid{display:grid;grid-template-columns:1fr 1fr 280px;gap:20px;align-items:start}
    .col-left,.col-mid,.col-right{display:flex;flex-direction:column;gap:20px}
    .stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:10px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);transition:transform .25s,box-shadow .25s;animation:fadeUp .5s ease both;opacity:0}
    .stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(26,58,110,.1)}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}.stat-card:nth-child(4){animation-delay:.24s}.stat-card:nth-child(5){animation-delay:.30s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .stat-icon .material-icons{font-size:20px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--blue);line-height:1}
    .stat-label{font-size:.68rem;color:var(--muted);margin-top:3px}
    .card{background:#fff;border-radius:14px;padding:20px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase;letter-spacing:.5px}
    .priority-urgente{background:#fee2e2;color:#991b1b;display:inline-flex;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700}
    .priority-normale{background:#f0f4fa;color:var(--muted);display:inline-flex;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700}
    .examen-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f0f4fa}
    .examen-row:last-child{border-bottom:none}
    .examen-avatar{width:34px;height:34px;border-radius:8px;background:var(--blue-light);display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .examen-avatar .material-icons{font-size:17px;color:var(--blue-bright)}
    .progress-bar{height:8px;background:#eef0f6;border-radius:4px;overflow:hidden;margin-top:6px}
    .progress-fill{height:100%;border-radius:4px;transition:width .6s ease}
    .qa-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;border:1.5px solid #eef0f6;background:#fff;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text);margin-bottom:8px}
    .qa-btn:hover{border-color:var(--blue-bright);background:var(--blue-light);transform:translateX(3px)}
    .qa-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .qa-icon .material-icons{font-size:16px;color:#fff}
    .qa-label{font-size:.83rem;font-weight:600;color:var(--blue)}
    .qa-sub{font-size:.7rem;color:var(--muted)}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;display:none}
    .alert-msg.show{display:block}
    .alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
    .alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    @media(max-width:1200px){.dash-grid{grid-template-columns:1fr 280px}.col-mid{display:none}.stats-row{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:900px){.dash-grid{grid-template-columns:1fr}.stats-row{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-laborantin">Laborantin</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item active" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Laboratoire</div>
    <a class="nav-item" href="examens-en-attente.php"><span class="material-icons">pending_actions</span> Examens en attente</a>
    <a class="nav-item" href="saisir-resultats.php"><span class="material-icons">science</span> Saisir résultats</a>
    <a class="nav-item" href="resultat_transmis.php"><span class="material-icons">check_circle</span> Résultats transmis</a>
    <a class="nav-item" href="#"><span class="material-icons">inventory</span> Stock réactifs</a>
  </nav>
  <div class="sidebar-footer"><a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a></div>
</aside>
<div class="main-wrapper">
  <header class="topbar">
    <a class="topbar-logo" href="dashboard.php"><img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'"><span class="logo-name">KLINIK</span></a>
    <div class="topbar-left"><button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button></div>
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher examen, patient..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbUrgents>0):?><span class="notif-badge"><?=$nbUrgents?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user">
        <div class="topbar-avatar" id="topbarAvatar">LB</div>
        <div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Laborantin</div></div>
      </div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px">
      <h1>Espace <span style="color:var(--blue-bright)">Laborantin</span></h1>
      <p>Gestion des examens biologiques — <?=date('d F Y')?></p>
    </div>
    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?=$nbAttente?></div><div class="stat-label">En attente</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#0891b2"><span class="material-icons">science</span></div><div><div class="stat-value"><?=$nbEnCours?></div><div class="stat-label">En cours</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">check_circle</span></div><div><div class="stat-value"><?=$nbTransmis?></div><div class="stat-label">Transmis aujourd'hui</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--danger)"><span class="material-icons">priority_high</span></div><div><div class="stat-value"><?=$nbUrgents?></div><div class="stat-label">Urgents</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#7c3aed"><span class="material-icons">biotech</span></div><div><div class="stat-value"><?=$nbMesTransmis?></div><div class="stat-label">Mes transmissions</div></div></div>
    </div>
    <!-- Grille -->
    <div class="dash-grid">
      <!-- Gauche : examens à traiter -->
      <div class="col-left">
        <div class="card">
          <div class="card-header"><h3>Examens à traiter</h3><span style="font-size:.75rem;color:var(--muted)"><?=count($examens)?> examen(s)</span></div>
          <?php if(empty($examens)):?><p style="color:var(--muted);text-align:center;padding:24px 0;font-size:.85rem">Aucun examen en attente 🎉</p>
          <?php else: foreach($examens as $ex):?>
          <div class="examen-row">
            <div class="examen-avatar"><span class="material-icons">biotech</span></div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px">
                <strong style="font-size:.86rem"><?=htmlspecialchars($ex['type_examen'])?></strong>
                <span class="priority-<?=$ex['priorite']?>"><?=ucfirst($ex['priorite'])?></span>
              </div>
              <div style="font-size:.74rem;color:var(--muted)"><?=htmlspecialchars($ex['prenom'].' '.$ex['nom'])?> · Dr. <?=htmlspecialchars($ex['med_prenom'].' '.$ex['med_nom'])?></div>
            </div>
            <button class="btn-primary" style="padding:5px 10px;font-size:.75rem;flex-shrink:0" onclick="openSaisie(<?=$ex['id']?>,'<?=htmlspecialchars($ex['type_examen'])?>','<?=htmlspecialchars($ex['prenom'].' '.$ex['nom'])?>')">
              <span class="material-icons" style="font-size:14px">edit</span> Saisir
            </button>
          </div>
          <?php endforeach;endif;?>
        </div>
        <!-- Mes derniers résultats -->
        <div class="card">
          <div class="card-header"><h3>Mes derniers résultats</h3></div>
          <?php if(empty($mesResultats)):?><p style="color:var(--muted);text-align:center;padding:16px 0;font-size:.83rem">Aucun résultat transmis</p>
          <?php else: foreach($mesResultats as $r):?>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f0f4fa">
            <div style="width:34px;height:34px;border-radius:8px;background:#d1fae5;display:flex;align-items:center;justify-content:center;flex-shrink:0"><span class="material-icons" style="font-size:17px;color:#059669">check_circle</span></div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:.86rem"><?=htmlspecialchars($r['type_examen'])?></div>
              <div style="font-size:.74rem;color:var(--muted)"><?=htmlspecialchars($r['prenom'].' '.$r['nom'])?> · <?=date('d/m/Y',strtotime($r['date_resultat']))?></div>
            </div>
            <span class="status-badge status-active" style="font-size:.68rem">Transmis</span>
          </div>
          <?php endforeach;endif;?>
        </div>
      </div>
      <!-- Milieu : graphique -->
      <div class="col-mid">
        <div class="card">
          <div class="card-header"><h3>Activité 7 jours</h3></div>
          <canvas id="chartActivite" height="160"></canvas>
        </div>
        <div class="card">
          <div class="card-header"><h3>Taux de complétion</h3></div>
          <?php
          $total=($nbAttente+$nbEnCours+$nbTransmis);
          $pct=$total>0?round(($nbMesTransmis/$total)*100):0;
          ?>
          <div style="text-align:center;padding:10px 0">
            <div style="font-family:'Oswald',sans-serif;font-size:2.2rem;font-weight:700;color:var(--blue)"><?=$pct?>%</div>
            <div style="font-size:.78rem;color:var(--muted);margin-bottom:14px">des examens traités</div>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?=$pct?>%;background:<?=$pct>75?'var(--success)':($pct>40?'var(--warning)':'var(--danger)')?>"></div></div>
          <div style="display:flex;justify-content:space-between;margin-top:12px;font-size:.78rem;color:var(--muted)">
            <span>En attente: <strong style="color:var(--text)"><?=$nbAttente?></strong></span>
            <span>Transmis: <strong style="color:var(--text)"><?=$nbMesTransmis?></strong></span>
          </div>
        </div>
        <!-- Répartition types -->
        <div class="card">
          <div class="card-header"><h3>Types d'examens</h3></div>
          <canvas id="chartTypes" height="160"></canvas>
        </div>
      </div>
      <!-- Droite -->
      <div class="col-right">
        <!-- Urgences -->
        <?php if($nbUrgents>0):?>
        <div style="background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:14px;padding:18px;color:#fff">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <span class="material-icons" style="font-size:22px">warning</span>
            <span style="font-family:'Oswald',sans-serif;font-size:1rem;font-weight:700">EXAMENS URGENTS</span>
          </div>
          <div style="font-size:2rem;font-weight:700;font-family:'Oswald',sans-serif;margin-bottom:4px"><?=$nbUrgents?></div>
          <div style="font-size:.8rem;opacity:.85">examen(s) à traiter en priorité</div>
        </div>
        <?php endif;?>
        <!-- Actions -->
        <div class="card">
          <div class="card-header"><h3>Actions rapides</h3></div>
          <a class="qa-btn" href="#"><div class="qa-icon" style="background:var(--blue-bright)"><span class="material-icons">pending_actions</span></div><div><div class="qa-label">Voir tous les examens</div><div class="qa-sub">File d'attente</div></div></a>
          <a class="qa-btn" href="#"><div class="qa-icon" style="background:#059669"><span class="material-icons">check_circle</span></div><div><div class="qa-label">Résultats transmis</div><div class="qa-sub">Historique</div></div></a>
          <a class="qa-btn" href="#"><div class="qa-icon" style="background:#7c3aed"><span class="material-icons">inventory</span></div><div><div class="qa-label">Stock réactifs</div><div class="qa-sub">Inventaire</div></div></a>
          <a class="qa-btn" href="#"><div class="qa-icon" style="background:var(--warning)"><span class="material-icons">bar_chart</span></div><div><div class="qa-label">Rapport</div><div class="qa-sub">Statistiques</div></div></a>
        </div>
      </div>
    </div>
  </main>
</div>
<!-- Modal saisie -->
<div class="modal-overlay" id="modalSaisie">
  <div class="modal">
    <div class="modal-header"><h3>Saisir résultat</h3><button class="modal-close" onclick="closeModal('modalSaisie')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="saisieAlert"></div>
    <p id="saisieInfo" style="font-size:.83rem;color:var(--muted);margin-bottom:14px;background:#f8faff;padding:10px;border-radius:8px"></p>
    <input type="hidden" id="saisieId">
    <div class="form-group"><label>Résultat *</label><textarea id="saisieResultat" class="form-control" rows="5" placeholder="Saisissez le résultat détaillé..."></textarea></div>
    <div style="display:flex;gap:12px;margin-top:16px">
      <button class="btn-outline" onclick="closeModal('modalSaisie')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="transmettre()" style="flex:1" id="btnSaisie"><span class="material-icons">send</span> Transmettre</button>
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
function openSaisie(id,type,patient){
  document.getElementById('saisieId').value=id;
  document.getElementById('saisieInfo').textContent='Examen : '+type+' · Patient : '+patient;
  document.getElementById('saisieResultat').value='';
  openModal('modalSaisie');
}
function transmettre(){
  const alertEl=document.getElementById('saisieAlert');
  const res=document.getElementById('saisieResultat').value.trim();
  alertEl.className='alert-msg';
  if(!res){alertEl.textContent='Veuillez saisir le résultat.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnSaisie');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/examens.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'transmettre',examen_id:document.getElementById('saisieId').value,resultat:res})})
  .then(r=>r.json()).then(data=>{
    if(data.success){alertEl.textContent='Résultat transmis !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalSaisie');location.reload()},1200);}
    else{alertEl.textContent=data.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">send</span> Transmettre';}
  });
}
// Graphique activité
new Chart(document.getElementById('chartActivite').getContext('2d'),{
  type:'bar',data:{labels:<?=json_encode($jours)?>,datasets:[
    {label:'Transmis',data:<?=json_encode($valsTransmis)?>,backgroundColor:'rgba(5,150,105,.2)',borderColor:'#059669',borderWidth:2,borderRadius:4},
    {label:'Demandés',data:<?=json_encode($valsTotal)?>,backgroundColor:'rgba(37,99,235,.1)',borderColor:'#2563eb',borderWidth:2,borderRadius:4}
  ]},
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:10},padding:8}}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'#f0f4ff'}},x:{grid:{display:false}}}}
});
// Graphique types
const typesData=<?=json_encode($types)?>;
if(typesData.length>0){
  new Chart(document.getElementById('chartTypes').getContext('2d'),{
    type:'doughnut',data:{labels:typesData.map(t=>t.type_examen),datasets:[{data:typesData.map(t=>t.nb),backgroundColor:['#2563eb','#0891b2','#059669','#d97706','#7c3aed'],borderWidth:0}]},
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:10},padding:8}}},cutout:'55%'}
  });
}

// ── Init Notifications & Messages ──
KlinikNotifications.init();
KlinikMessages.init();
</script>
</body>
</html>
