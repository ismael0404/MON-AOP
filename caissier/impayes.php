<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['caissier']);
$user = getUser(); $pdo = getDB();

$search = sanitize($_GET['search'] ?? '');
$where  = "WHERE f.statut IN('impayee','partielle')"; $params=[];
if($search){$where.=" AND (u.nom LIKE ? OR u.prenom LIKE ?)";$params[]="%$search%";$params[]="%$search%";}

$stmt=$pdo->prepare("
    SELECT f.*,u.nom,u.prenom,COALESCE(SUM(pm.montant_paye),0) as total_paye
    FROM factures f JOIN patients p ON f.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id
    LEFT JOIN paiements pm ON f.id=pm.facture_id
    $where GROUP BY f.id ORDER BY f.date_facture ASC
");
$stmt->execute($params);$impayes=$stmt->fetchAll();

$nbImpayees=(int)$pdo->query("SELECT COUNT(*) FROM factures WHERE statut='impayee'")->fetchColumn();
$nbPartielles=(int)$pdo->query("SELECT COUNT(*) FROM factures WHERE statut='partielle'")->fetchColumn();
$s=$pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM factures WHERE statut='impayee'");$totalImp=(float)$s->fetchColumn();
$s=$pdo->query("SELECT COALESCE(SUM(montant_total-COALESCE((SELECT SUM(montant_paye) FROM paiements WHERE facture_id=factures.id),0)),0) FROM factures WHERE statut='partielle'");
$stot=$pdo->query("SELECT COALESCE(SUM(f.montant_total - COALESCE(p2.tot,0)),0) FROM factures f LEFT JOIN (SELECT facture_id,SUM(montant_paye) as tot FROM paiements GROUP BY facture_id) p2 ON f.id=p2.facture_id WHERE f.statut IN('impayee','partielle')");
$totalReste=(float)$stot->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Impayés</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .alerte-banner{background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:12px;padding:18px 22px;color:#fff;display:flex;align-items:center;gap:16px;margin-bottom:20px;animation:fadeUp .5s ease both}
    .alerte-banner .material-icons{font-size:32px;flex-shrink:0}
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);animation:fadeUp .5s ease both;opacity:0}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .search-wrap{position:relative;margin-bottom:16px}
    .search-wrap .material-icons{position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:17px;color:#9ca3af}
    .search-wrap input{width:100%;padding:9px 12px 9px 32px;border:1.5px solid #eef0f6;border-radius:8px;font-size:.86rem;outline:none;background:#f8f9fc}
    .search-wrap input:focus{border-color:var(--blue-bright);background:#fff}
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:480px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:none}
    .alert-msg.show{display:block}.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:700px){.stats-row{grid-template-columns:1fr 1fr}}
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
    <a class="nav-item" href="nouvelle_facture.php"><span class="material-icons">add_circle</span> Nouvelle facture</a>
    <a class="nav-item" href="toutes_les_factures.php"><span class="material-icons">receipt_long</span> Toutes les factures</a>
    <a class="nav-item active" href="impayes.php"><span class="material-icons">pending_actions</span> Impayés</a>
    <a class="nav-item" href="paiements_recus.php"><span class="material-icons">check_circle</span> Paiements reçus</a>
    <div class="nav-section-title">Rapports</div>
    <a class="nav-item" href="rapports.php"><span class="material-icons">bar_chart</span> Rapport</a>
  
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
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher facture, patient..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbImpayees>0):?><span class="notif-badge"><?=$nbImpayees?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">CA</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Caissier</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Factures impayées</h1><p>Suivi des créances en attente de règlement</p></div>

    <?php if($totalReste>0):?>
    <div class="alerte-banner">
      <span class="material-icons">warning</span>
      <div>
        <div style="font-family:'Oswald',sans-serif;font-size:1.05rem;font-weight:700">Solde total à recouvrer</div>
        <div style="font-size:1.4rem;font-weight:700;font-family:'Oswald',sans-serif;margin-top:2px"><?=number_format($totalReste,0,',',' ')?> FCFA</div>
        <div style="font-size:.75rem;opacity:.75;margin-top:2px"><?=count($impayes)?> facture(s) non soldée(s)</div>
      </div>
    </div>
    <?php endif;?>

    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--danger)"><span class="material-icons">money_off</span></div><div><div class="stat-value"><?=$nbImpayees?></div><div class="stat-label">Totalement impayées</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">hourglass_bottom</span></div><div><div class="stat-value"><?=$nbPartielles?></div><div class="stat-label">Partiellement payées</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#7c3aed"><span class="material-icons">account_balance</span></div><div><div class="stat-value"><?=number_format($totalReste/1000,0)?>K</div><div class="stat-label">Reste à payer (FCFA)</div></div></div>
    </div>

    <div class="search-wrap">
      <span class="material-icons">search</span>
      <input type="text" id="searchInput" placeholder="Rechercher patient..." value="<?=htmlspecialchars($search)?>" onkeyup="debounceSearch()">
    </div>

    <div class="table-card">
      <div class="table-header"><h3>Factures non soldées</h3><span style="font-size:.8rem;color:var(--muted)"><?=count($impayes)?> résultat(s)</span></div>
      <div style="overflow-x:auto"><table class="klinik-table">
        <thead><tr><th>#</th><th>Patient</th><th>Total</th><th>Payé</th><th>Reste</th><th>Statut</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
          <?php if(empty($impayes)):?>
          <tr><td colspan="8" style="text-align:center;padding:36px;color:var(--muted)">
            <span class="material-icons" style="font-size:32px;display:block;margin:0 auto 8px;color:var(--success)">check_circle</span>
            Aucune facture impayée 🎉
          </td></tr>
          <?php else: foreach($impayes as $f):
            $reste=$f['montant_total']-$f['total_paye'];
          ?>
          <tr>
            <td style="font-family:'Oswald',sans-serif;font-weight:700;color:var(--muted)">#<?=str_pad($f['id'],4,'0',STR_PAD_LEFT)?></td>
            <td><strong><?=htmlspecialchars($f['prenom'].' '.$f['nom'])?></strong></td>
            <td><?=number_format($f['montant_total'],0,',',' ')?> FCFA</td>
            <td style="color:var(--success);font-weight:600"><?=number_format($f['total_paye'],0,',',' ')?> FCFA</td>
            <td style="color:var(--danger);font-weight:700;font-family:'Oswald',sans-serif"><?=number_format($reste,0,',',' ')?> FCFA</td>
            <td><span class="status-badge <?=$f['statut']==='partielle'?'status-pending':'status-inactive'?>"><?=$f['statut']==='partielle'?'Partielle':'Impayée'?></span></td>
            <td style="font-size:.82rem;color:var(--muted)"><?=date('d/m/Y',strtotime($f['date_facture']))?></td>
            <td>
              <button class="btn-primary" style="padding:5px 12px;font-size:.76rem"
                onclick="openEnc(<?=$f['id']?>,<?=$f['montant_total']?>,<?=$f['total_paye']?>,'<?=htmlspecialchars($f['prenom'].' '.$f['nom'],ENT_QUOTES)?>')">
                <span class="material-icons" style="font-size:14px">payments</span> Encaisser
              </button>
            </td>
          </tr>
          <?php endforeach;endif;?>
        </tbody>
      </table></div>
    </div>
  </main>
</div>

<!-- Modal encaissement -->
<div class="modal-overlay" id="modalEnc">
  <div class="modal">
    <div class="modal-header"><h3>Encaissement</h3><button class="modal-close" onclick="closeModal('modalEnc')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="eAlert"></div>
    <div id="eInfo" style="background:#f0f7ff;border-radius:10px;padding:14px;margin-bottom:16px"></div>
    <input type="hidden" id="eId">
    <div class="form-group"><label>Montant encaissé (FCFA) *</label><input type="number" id="eMontant" class="form-control" min="0" step="500"></div>
    <div class="form-group"><label>Mode de paiement *</label>
      <select id="eMode" class="form-control">
        <option value="especes">💵 Espèces</option><option value="mobile_money">📱 Mobile Money</option>
        <option value="carte">💳 Carte bancaire</option><option value="cheque">📄 Chèque</option>
      </select>
    </div>
    <div class="form-group"><label>Référence</label><input type="text" id="eRef" class="form-control" placeholder="N° transaction..."></div>
    <div style="display:flex;gap:12px;margin-top:14px">
      <button class="btn-outline" onclick="closeModal('modalEnc')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveEnc()" style="flex:2" id="btnE"><span class="material-icons">payments</span> Enregistrer</button>
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
let st;function debounceSearch(){clearTimeout(st);st=setTimeout(()=>window.location.href='impayes.php?search='+encodeURIComponent(document.getElementById('searchInput').value),400);}
function openEnc(id,total,paye,nom){
  document.getElementById('eId').value=id;
  const reste=total-paye;
  document.getElementById('eMontant').value=reste;
  document.getElementById('eInfo').innerHTML=`<div style="font-weight:700;margin-bottom:6px">Facture #${String(id).padStart(4,'0')} — ${nom}</div><div style="font-size:.82rem;display:flex;gap:14px"><span>Total : <strong>${Number(total).toLocaleString('fr-FR')} FCFA</strong></span><span style="color:var(--danger)">Reste : <strong>${Number(reste).toLocaleString('fr-FR')} FCFA</strong></span></div>`;
  document.getElementById('eAlert').className='alert-msg';
  openModal('modalEnc');
}
function saveEnc(){
  const alertEl=document.getElementById('eAlert');alertEl.className='alert-msg';
  const m=document.getElementById('eMontant').value;
  if(!m||Number(m)<=0){alertEl.textContent='Montant requis.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnE');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/factures.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'encaisser',facture_id:document.getElementById('eId').value,montant_paye:m,mode_paiement:document.getElementById('eMode').value,reference:document.getElementById('eRef').value})})
  .then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Paiement enregistré !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalEnc');location.reload()},1200);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">payments</span> Enregistrer';}
  });
}
</script>
</body></html>
