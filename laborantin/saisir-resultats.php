<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['laborantin']);
$user = getUser(); $pdo = getDB();

// Tous les examens non transmis
$examens = $pdo->query("
    SELECT e.*,
           u.nom as p_nom,u.prenom as p_prenom,
           um.nom as med_nom,um.prenom as med_prenom
    FROM examens e
    JOIN consultations c ON e.consultation_id=c.id
    JOIN patients p ON c.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id
    JOIN medecins m ON c.medecin_id=m.id JOIN utilisateurs um ON m.utilisateur_id=um.id
    WHERE e.statut IN('en_attente','en_cours')
    ORDER BY e.priorite DESC, e.date_demande ASC
")->fetchAll();

// Transmissions récentes de CE laborantin
$recents = $pdo->prepare("
    SELECT e.*,u.nom as p_nom,u.prenom as p_prenom
    FROM examens e
    JOIN consultations c ON e.consultation_id=c.id
    JOIN patients p ON c.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id
    WHERE e.laborantin_id=? AND e.statut='transmis'
    ORDER BY e.date_resultat DESC LIMIT 8
");
$recents->execute([$user['id']]); $recents=$recents->fetchAll();

$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut IN('en_attente','en_cours')");$nbEnCours=(int)$s->fetchColumn();
$s=$pdo->query("SELECT COUNT(*) FROM examens WHERE statut='en_attente' AND priorite='urgente'");$nbUrgents=(int)$s->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Saisir Résultats</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .two-col{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start}
    .card{background:#fff;border-radius:14px;padding:20px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:16px}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase}
    .examen-select-item{display:flex;align-items:center;gap:10px;padding:11px;border-radius:10px;cursor:pointer;transition:all .15s;border:1.5px solid #eef0f6;margin-bottom:8px}
    .examen-select-item:hover{background:#f0f7ff;border-color:var(--blue-bright)}
    .examen-select-item.selected{background:var(--blue-light);border-color:var(--blue-bright)}
    .examen-select-item.urgente{border-color:#fca5a5;background:#fff8f8}
    .examen-select-item.urgente.selected{background:#fee2e2;border-color:#dc2626}
    .ex-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .saisie-zone{background:#f0f7ff;border:1.5px solid var(--blue-bright);border-radius:12px;padding:18px;margin-top:12px;animation:fadeUp .3s ease}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    .recent-item{padding:10px 0;border-bottom:1px solid #f0f4fa}.recent-item:last-child{border:none}
    .check-badge{display:inline-flex;align-items:center;gap:4px;background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:.7rem;font-weight:600;margin-top:4px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:1000px){.two-col{grid-template-columns:1fr}}
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
    <a class="nav-item" href="examens-en-attente.php"><span class="material-icons">pending_actions</span> Examens en attente</a>
    <a class="nav-item active" href="saisir-resultats.php"><span class="material-icons">science</span> Saisir résultats</a>
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
    <div class="page-header" style="margin-bottom:20px">
      <h1>Saisir résultats</h1>
      <p>Sélectionnez un examen, saisissez le résultat et transmettez au médecin</p>
    </div>
    <div class="two-col">
      <!-- Liste + zone saisie -->
      <div>
        <div class="card">
          <div class="card-header">
            <h3>Examens à traiter</h3>
            <span style="background:var(--blue-light);color:var(--blue);padding:3px 10px;border-radius:20px;font-size:.76rem;font-weight:700"><?=$nbEnCours?></span>
          </div>
          <?php if(empty($examens)): ?>
          <div style="text-align:center;padding:30px 0">
            <span class="material-icons" style="font-size:40px;color:var(--success)">check_circle</span>
            <p style="color:var(--muted);margin-top:8px;font-size:.85rem">Tous les examens ont été traités 🎉</p>
          </div>
          <?php else: foreach($examens as $e): ?>
          <div class="examen-select-item <?=$e['priorite']==='urgente'?'urgente':''?>" id="item-<?=$e['id']?>"
            onclick="selectExamen(<?=$e['id']?>,'<?=htmlspecialchars($e['type_examen'],ENT_QUOTES)?>','<?=htmlspecialchars($e['p_prenom'].' '.$e['p_nom'],ENT_QUOTES)?>')">
            <div class="ex-dot" style="background:<?=$e['priorite']==='urgente'?'#dc2626':($e['statut']==='en_cours'?'#0891b2':'#9ca3af')?>"></div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:.88rem;display:flex;align-items:center;gap:6px">
                <?=htmlspecialchars($e['type_examen'])?>
                <?php if($e['priorite']==='urgente'):?><span style="background:#fee2e2;color:#dc2626;font-size:.62rem;padding:1px 6px;border-radius:10px;font-weight:700">URGENT</span><?php endif;?>
              </div>
              <div style="font-size:.74rem;color:var(--muted);margin-top:1px"><?=htmlspecialchars($e['p_prenom'].' '.$e['p_nom'])?></div>
            </div>
            <span class="status-badge <?=$e['statut']==='en_cours'?'status-done':'status-pending'?>" style="font-size:.65rem;flex-shrink:0"><?=$e['statut']==='en_cours'?'En cours':'En attente'?></span>
          </div>
          <?php endforeach; endif; ?>

          <!-- Zone saisie dynamique -->
          <div class="saisie-zone" id="saisiZone" style="display:none">
            <div class="alert-msg" id="sAlert"></div>
            <div id="sZoneInfo" style="font-size:.84rem;font-weight:700;color:var(--blue);margin-bottom:12px"></div>
            <input type="hidden" id="sExamenId">
            <div class="form-group" style="margin-bottom:12px">
              <label style="font-size:.82rem">Résultat de l'examen *</label>
              <textarea id="sResultat" class="form-control" rows="6" placeholder="Saisissez les résultats détaillés..."></textarea>
            </div>
            <div style="display:flex;gap:10px">
              <button class="btn-outline" onclick="cancelSaisie()" style="flex:1">Annuler</button>
              <button class="btn-primary" onclick="transmettre()" style="flex:2" id="btnT">
                <span class="material-icons">send</span> Transmettre au médecin
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Mes transmissions récentes -->
      <div class="card">
        <div class="card-header"><h3>Mes transmissions récentes</h3></div>
        <?php if(empty($recents)): ?>
        <p style="color:var(--muted);text-align:center;padding:16px 0;font-size:.83rem">Aucune transmission</p>
        <?php else: foreach($recents as $r): ?>
        <div class="recent-item">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:3px">
            <strong style="font-size:.86rem"><?=htmlspecialchars($r['type_examen'])?></strong>
            <span style="font-size:.72rem;color:var(--muted);flex-shrink:0;margin-left:6px"><?=$r['date_resultat']?date('d/m/Y',strtotime($r['date_resultat'])):'—'?></span>
          </div>
          <div style="font-size:.76rem;color:var(--muted)"><?=htmlspecialchars($r['p_prenom'].' '.$r['p_nom'])?></div>
          <div class="check-badge"><span class="material-icons" style="font-size:13px">check</span> Transmis</div>
        </div>
        <?php endforeach; endif; ?>
        <a href="resultat_transmis.php" class="btn-outline" style="width:100%;margin-top:14px;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;font-size:.82rem">
          <span class="material-icons" style="font-size:16px">history</span> Voir tout l'historique
        </a>
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

function selectExamen(id,type,patient){
  document.querySelectorAll('.examen-select-item').forEach(el=>el.classList.remove('selected'));
  document.getElementById('item-'+id).classList.add('selected');
  document.getElementById('sExamenId').value=id;
  document.getElementById('sZoneInfo').textContent=type+' — '+patient;
  document.getElementById('sResultat').value='';
  document.getElementById('sAlert').className='alert-msg';
  const zone=document.getElementById('saisiZone');
  zone.style.display='block';
  zone.scrollIntoView({behavior:'smooth',block:'nearest'});
}
function cancelSaisie(){
  document.querySelectorAll('.examen-select-item').forEach(el=>el.classList.remove('selected'));
  document.getElementById('saisiZone').style.display='none';
}
function transmettre(){
  const alertEl=document.getElementById('sAlert');alertEl.className='alert-msg';
  const res=document.getElementById('sResultat').value.trim();
  if(!res){alertEl.textContent='Veuillez saisir le résultat.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnT');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/examens.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'transmettre',examen_id:document.getElementById('sExamenId').value,resultat:res})})
  .then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Résultat transmis avec succès !';alertEl.classList.add('show','alert-success');setTimeout(()=>location.reload(),1400);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">send</span> Transmettre au médecin';}
  });
}
</script>
</body></html>
