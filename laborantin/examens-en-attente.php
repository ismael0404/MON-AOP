<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['laborantin']);
$user = getUser(); $pdo = getDB();

$filter = sanitize($_GET['priorite'] ?? '');
$where  = "WHERE e.statut IN('en_attente','en_cours')"; $params=[];
if($filter === 'urgente') { $where .= " AND e.priorite='urgente'"; }
elseif($filter === 'normale') { $where .= " AND e.priorite='normale'"; }

$stmt = $pdo->prepare("
    SELECT e.*,
           u.nom as p_nom,u.prenom as p_prenom,
           um.nom as med_nom,um.prenom as med_prenom,
           m.specialite
    FROM examens e
    JOIN consultations c ON e.consultation_id=c.id
    JOIN patients p ON c.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id
    JOIN medecins m ON c.medecin_id=m.id JOIN utilisateurs um ON m.utilisateur_id=um.id
    $where ORDER BY e.priorite DESC, e.date_demande ASC
");
$stmt->execute($params); $examens = $stmt->fetchAll();

$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut='en_attente'");$nbAtt=(int)$s->fetchColumn();
$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut='en_cours'");$nbEC=(int)$s->fetchColumn();
$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut='en_attente' AND priorite='urgente'");$nbUrg=(int)$s->fetchColumn();
$nbUrgents = $nbUrg;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Examens en attente</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .urgence-banner{background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:12px;padding:14px 20px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:20px;animation:fadeUp .4s ease both}
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
    .filter-btn{padding:7px 16px;border-radius:20px;border:1.5px solid #eef0f6;background:#fff;font-size:.82rem;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text)}
    .filter-btn:hover,.filter-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .filter-btn.urgent-btn{border-color:#fca5a5;color:#dc2626;background:#fff5f5}
    .filter-btn.urgent-btn.active{background:#dc2626;border-color:#dc2626;color:#fff}
    .examen-card{background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:18px;margin-bottom:12px;display:flex;align-items:flex-start;gap:16px;box-shadow:0 2px 8px rgba(26,58,110,.04);transition:transform .2s,box-shadow .2s;animation:fadeUp .5s ease both;opacity:0}
    .examen-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,58,110,.1)}
    .examen-card.urgente{border-color:#fca5a5;background:#fff8f8}
    .ex-num{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:'Oswald',sans-serif;font-size:.95rem;font-weight:700;color:#fff}
    .badge-urgente{background:#fee2e2;color:#991b1b;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:700}
    .badge-normale{background:#f0f4fa;color:#6b7280;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:700}
    .badge-en-cours{background:#dbeafe;color:#1d4ed8;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:600}
    .action-row{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:700px){.stats-row{grid-template-columns:1fr 1fr}.examen-card{flex-wrap:wrap}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-laborantin">Laborantin</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Laboratoire</div>
    <a class="nav-item active" href="examens-en-attente.php"><span class="material-icons">pending_actions</span> Examens en attente</a>
    <a class="nav-item" href="saisir-resultats.php"><span class="material-icons">science</span> Saisir résultats</a>
    <a class="nav-item" href="resultat_transmis.php"><span class="material-icons">check_circle</span> Résultats transmis</a>
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
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">LB</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Laborantin</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Examens en attente</h1><p>File d'attente du laboratoire — <?=count($examens)?> examen(s)</p></div>

    <?php if($nbUrg > 0): ?>
    <div class="urgence-banner">
      <span class="material-icons" style="font-size:28px;flex-shrink:0">warning</span>
      <div>
        <strong style="font-family:'Oswald',sans-serif;font-size:1rem"><?=$nbUrg?> examen(s) URGENT(S) en attente de traitement</strong>
        <div style="font-size:.78rem;opacity:.8;margin-top:2px">Traitez ces examens en priorité absolue.</div>
      </div>
    </div>
    <?php endif; ?>

    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?=$nbAtt?></div><div class="stat-label">En attente</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#0891b2"><span class="material-icons">labs</span></div><div><div class="stat-value"><?=$nbEC?></div><div class="stat-label">En cours d'analyse</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--danger)"><span class="material-icons">priority_high</span></div><div><div class="stat-value"><?=$nbUrg?></div><div class="stat-label">Urgents</div></div></div>
    </div>

    <div class="filter-bar">
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="examens-en-attente.php">Tous (<?=$nbAtt+$nbEC?>)</a>
      <a class="filter-btn urgent-btn <?= $filter==='urgente'?'active':'' ?>" href="?priorite=urgente">🔴 Urgents (<?=$nbUrg?>)</a>
      <a class="filter-btn <?= $filter==='normale'?'active':'' ?>" href="?priorite=normale">Normaux</a>
    </div>

    <?php if(empty($examens)): ?>
    <div style="background:#fff;border-radius:14px;padding:48px;text-align:center;border:1.5px solid #eef0f6">
      <span class="material-icons" style="font-size:48px;color:var(--success)">check_circle</span>
      <p style="color:var(--muted);margin-top:12px;font-size:.9rem">Aucun examen en attente — Laboratoire à jour ! 🎉</p>
    </div>
    <?php else: foreach($examens as $i=>$e):
      $bg = $e['priorite']==='urgente' ? '#dc2626' : ($e['statut']==='en_cours' ? '#0891b2' : 'var(--blue-bright)');
    ?>
    <div class="examen-card <?=$e['priorite']==='urgente'?'urgente':''?>" style="animation-delay:<?=$i*0.07?>s">
      <div class="ex-num" style="background:<?=$bg?>"><?=str_pad($i+1,2,'0',STR_PAD_LEFT)?></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
          <strong style="font-size:.95rem"><?=htmlspecialchars($e['type_examen'])?></strong>
          <span class="<?=$e['priorite']==='urgente'?'badge-urgente':'badge-normale'?>"><?=ucfirst($e['priorite'])?></span>
          <?php if($e['statut']==='en_cours'):?><span class="badge-en-cours">En cours</span><?php endif;?>
        </div>
        <div style="font-size:.8rem;color:var(--muted);margin-bottom:3px">
          <span class="material-icons" style="font-size:14px;vertical-align:middle">person</span>
          <strong style="color:var(--text)"><?=htmlspecialchars($e['p_prenom'].' '.$e['p_nom'])?></strong>
          &nbsp;·&nbsp;
          <span class="material-icons" style="font-size:14px;vertical-align:middle">stethoscope</span>
          Dr. <?=htmlspecialchars($e['med_prenom'].' '.$e['med_nom'])?>
          <?php if($e['specialite']):?> — <?=htmlspecialchars($e['specialite'])?><?php endif;?>
        </div>
        <div style="font-size:.76rem;color:var(--muted)">
          <span class="material-icons" style="font-size:13px;vertical-align:middle">schedule</span>
          Demandé le <?=date('d/m/Y à H:i',strtotime($e['date_demande']))?>
        </div>
        <div class="action-row">
          <?php if($e['statut']==='en_attente'): ?>
          <button class="btn-outline" style="padding:6px 14px;font-size:.8rem;display:flex;align-items:center;gap:5px" onclick="prendreEnCharge(<?=$e['id']?>)">
            <span class="material-icons" style="font-size:16px">play_arrow</span> Prendre en charge
          </button>
          <?php endif; ?>
          <button class="btn-primary" style="padding:6px 14px;font-size:.8rem;display:flex;align-items:center;gap:5px"
            onclick="openSaisie(<?=$e['id']?>,'<?=htmlspecialchars($e['type_examen'],ENT_QUOTES)?>','<?=htmlspecialchars($e['p_prenom'].' '.$e['p_nom'],ENT_QUOTES)?>')">
            <span class="material-icons" style="font-size:16px">edit_note</span> Saisir résultat
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </main>
</div>

<!-- Modal saisie résultat -->
<div class="modal-overlay" id="modalSaisie">
  <div class="modal">
    <div class="modal-header"><h3>Saisir le résultat</h3><button class="modal-close" onclick="closeModal('modalSaisie')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="sAlert"></div>
    <div id="sInfo" style="background:#f0f7ff;border-radius:8px;padding:12px;margin-bottom:16px;font-size:.84rem;color:var(--blue)"></div>
    <input type="hidden" id="sId">
    <div class="form-group">
      <label>Résultat de l'examen *</label>
      <textarea id="sResultat" class="form-control" rows="7" placeholder="Saisissez les résultats détaillés de l'examen...&#10;&#10;Ex:&#10;- Globules rouges : 4.8 M/μL (normale)&#10;- Hémoglobine : 14.2 g/dL (normale)&#10;- Leucocytes : 7200/μL (normale)&#10;&#10;Conclusion : Résultats dans les normes."></textarea>
    </div>
    <div style="display:flex;gap:12px;margin-top:14px">
      <button class="btn-outline" onclick="closeModal('modalSaisie')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="transmettre()" style="flex:2" id="btnS">
        <span class="material-icons">send</span> Transmettre au médecin
      </button>
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

function prendreEnCharge(id){
  fetch('../api/examens.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'prendreEnCharge',examen_id:id})})
  .then(r=>r.json()).then(d=>{if(d.success)location.reload();});
}

function openSaisie(id,type,patient){
  document.getElementById('sId').value=id;
  document.getElementById('sInfo').innerHTML='<strong>'+type+'</strong> &nbsp;·&nbsp; Patient : '+patient;
  document.getElementById('sResultat').value='';
  document.getElementById('sAlert').className='alert-msg';
  openModal('modalSaisie');
}

function transmettre(){
  const alertEl=document.getElementById('sAlert');alertEl.className='alert-msg';
  const res=document.getElementById('sResultat').value.trim();
  if(!res){alertEl.textContent='Veuillez saisir le résultat de l\'examen.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnS');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span> Transmission...';
  fetch('../api/examens.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'transmettre',examen_id:document.getElementById('sId').value,resultat:res})})
  .then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Résultat transmis avec succès au médecin !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalSaisie');location.reload()},1400);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">send</span> Transmettre au médecin';}
  });
}
</script>
</body></html>
