<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['admin']);
$user = getUser(); $pdo = getDB();

$filter = sanitize($_GET['statut'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$page = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;

$where = "WHERE 1=1"; $params = [];
if ($filter) { $where .= " AND f.statut=?"; $params[] = $filter; }
if ($search) { $where .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM factures f JOIN patients p ON f.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id $where");
$stmtC->execute($params); $total=(int)$stmtC->fetchColumn(); $totalPages=ceil($total/$perPage);

$stmt = $pdo->prepare("SELECT f.*,u.nom,u.prenom FROM factures f JOIN patients p ON f.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id $where ORDER BY f.date_facture DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $factures = $stmt->fetchAll();

$totalAuj    = (float)$pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE DATE(date_paiement)=CURDATE()")->fetchColumn();
$nbImpayees  = (int)$pdo->query("SELECT COUNT(*) FROM factures WHERE statut='impayee'")->fetchColumn();
$totalMois   = (float)$pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE MONTH(date_paiement)=MONTH(NOW()) AND YEAR(date_paiement)=YEAR(NOW())")->fetchColumn();
$nbPayeesAuj = (int)$pdo->query("SELECT COUNT(*) FROM paiements WHERE DATE(date_paiement)=CURDATE()")->fetchColumn();

$pts = $pdo->query("SELECT p.id,u.nom,u.prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id ORDER BY u.nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Facturation</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
    .stat-card{background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);transition:transform .2s;animation:fadeUp .5s ease both;opacity:0}
    .stat-card:hover{transform:translateY(-2px)}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}.stat-card:nth-child(4){animation-delay:.24s}
    .stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.stat-icon .material-icons{font-size:21px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--blue);line-height:1}.stat-label{font-size:.7rem;color:var(--muted);margin-top:3px}
    .filter-bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
    .filter-btn{padding:7px 16px;border-radius:20px;border:1.5px solid #eef0f6;background:#fff;font-size:.82rem;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text)}
    .filter-btn:hover,.filter-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    .search-wrap{position:relative;flex:1;min-width:180px}
    .search-wrap .material-icons{position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:17px;color:#9ca3af}
    .search-wrap input{width:100%;padding:8px 12px 8px 32px;border:1.5px solid #eef0f6;border-radius:8px;font-size:.86rem;outline:none;background:#f8f9fc}
    .search-wrap input:focus{border-color:var(--blue-bright);background:#fff}
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
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
    @media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-admin">Administrateur</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Gestion</div>
    <a class="nav-item" href="utilisateurs.php"><span class="material-icons">manage_accounts</span> Utilisateurs</a>
    <a class="nav-item" href="historique.php"><span class="material-icons">calendar_today</span> Historique des Rendez-vous</a>
    <a class="nav-item" href="dossiers_medicaux.php"><span class="material-icons">folder_shared</span> Dossiers médicaux</a>
    <a class="nav-item active" href="facturation.php"><span class="material-icons">receipt_long</span> Facturation</a>
    <div class="nav-section-title">Système</div>
    <a class="nav-item" href="rapports.php"><span class="material-icons">bar_chart</span> Rapports</a>
    <a class="nav-item" href="parametres.php"><span class="material-icons">settings</span> Paramètres</a>
  </nav>
  <div class="sidebar-footer"><a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a></div>
</aside>

<div class="main-wrapper">
  <header class="topbar">
    <a class="topbar-logo" href="dashboard.php"><img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'"><span class="logo-name">KLINIK</span></a>
    <div class="topbar-left"><button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button></div>
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher patient, médecin, rendez-vous..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user">
        <div class="topbar-avatar" id="topbarAvatar">AD</div>
        <div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Administrateur</div></div>
      </div>
    </div>
  </header>

  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
      <div><h1>Facturation</h1><p>Gestion des factures et paiements</p></div>
      <button class="btn-primary" onclick="openModal('modalFacture')"><span class="material-icons">add</span> Nouvelle facture</button>
    </div>

    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">payments</span></div><div><div class="stat-value"><?=number_format($totalAuj/1000,0)?>K</div><div class="stat-label">Recette aujourd'hui (FCFA)</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--danger)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?=$nbImpayees?></div><div class="stat-label">Factures impayées</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">receipt_long</span></div><div><div class="stat-value"><?=$nbPayeesAuj?></div><div class="stat-label">Paiements aujourd'hui</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#7c3aed"><span class="material-icons">trending_up</span></div><div><div class="stat-value"><?=number_format($totalMois/1000,0)?>K</div><div class="stat-label">Recette du mois (FCFA)</div></div></div>
    </div>

    <div class="filter-bar">
      <div class="search-wrap"><span class="material-icons">search</span><input type="text" id="searchInput" placeholder="Rechercher patient..." value="<?=htmlspecialchars($search)?>" onkeyup="debounceSearch()"></div>
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="facturation.php">Toutes</a>
      <a class="filter-btn <?= $filter==='impayee'?'active':'' ?>"  href="?statut=impayee">Impayées</a>
      <a class="filter-btn <?= $filter==='partielle'?'active':'' ?>" href="?statut=partielle">Partielles</a>
      <a class="filter-btn <?= $filter==='payee'?'active':'' ?>"    href="?statut=payee">Payées</a>
    </div>

    <div class="table-card">
      <div class="table-header"><h3>Factures</h3><span style="font-size:.8rem;color:var(--muted)"><?=$total?> facture(s)</span></div>
      <div style="overflow-x:auto"><table class="klinik-table">
        <thead><tr><th>#</th><th>Patient</th><th>Montant</th><th>Statut</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
          <?php if(empty($factures)):?><tr><td colspan="6" style="text-align:center;padding:28px;color:var(--muted)">Aucune facture trouvée</td></tr>
          <?php else: foreach($factures as $f):?>
          <tr>
            <td style="font-family:'Oswald',sans-serif;font-weight:700;color:var(--muted)">#<?=str_pad($f['id'],4,'0',STR_PAD_LEFT)?></td>
            <td><strong><?=htmlspecialchars($f['prenom'].' '.$f['nom'])?></strong></td>
            <td><strong style="font-family:'Oswald',sans-serif"><?=number_format($f['montant_total'],0,',',' ')?> FCFA</strong></td>
            <td><span class="status-badge <?=$f['statut']==='payee'?'status-active':($f['statut']==='partielle'?'status-pending':'status-inactive')?>"><?=$f['statut']==='payee'?'Payée':($f['statut']==='partielle'?'Partielle':'Impayée')?></span></td>
            <td style="font-size:.82rem;color:var(--muted)"><?=date('d/m/Y',strtotime($f['date_facture']))?></td>
            <td><?php if($f['statut']!=='payee'):?>
              <button class="btn-primary" style="padding:5px 12px;font-size:.76rem" onclick="openEncaissement(<?=$f['id']?>,<?=$f['montant_total']?>,'<?=htmlspecialchars($f['prenom'].' '.$f['nom'],ENT_QUOTES)?>')">
                <span class="material-icons" style="font-size:14px">payments</span> Encaisser
              </button>
              <?php else:?><span style="color:var(--success);font-size:.82rem">✓ Soldée</span><?php endif;?></td>
          </tr>
          <?php endforeach;endif;?>
        </tbody>
      </table></div>
      <div class="pagination">
        <span>Affichage <?=min($offset+1,$total)?>–<?=min($offset+$perPage,$total)?> sur <?=$total?></span>
        <div class="page-btns">
          <?php if($page>1):?><a class="page-btn" href="?page=<?=$page-1?>&statut=<?=urlencode($filter)?>&search=<?=urlencode($search)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif;?>
          <?php for($pg=max(1,$page-2);$pg<=min($totalPages,$page+2);$pg++):?><a class="page-btn <?=$pg===$page?'active':''?>" href="?page=<?=$pg?>&statut=<?=urlencode($filter)?>&search=<?=urlencode($search)?>"><?=$pg?></a><?php endfor;?>
          <?php if($page<$totalPages):?><a class="page-btn" href="?page=<?=$page+1?>&statut=<?=urlencode($filter)?>&search=<?=urlencode($search)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif;?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal nouvelle facture -->
<div class="modal-overlay" id="modalFacture">
  <div class="modal">
    <div class="modal-header"><h3>Nouvelle facture</h3><button class="modal-close" onclick="closeModal('modalFacture')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="fAlert"></div>
    <div class="form-group"><label>Patient *</label>
      <select id="fPatient" class="form-control">
        <option value="">Sélectionner un patient...</option>
        <?php foreach($pts as $pt) echo "<option value='{$pt['id']}'>{$pt['prenom']} {$pt['nom']}</option>"; ?>
      </select>
    </div>
    <div class="form-group"><label>Montant total (FCFA) *</label><input type="number" id="fMontant" class="form-control" placeholder="0" min="0"></div>
    <div style="display:flex;gap:12px;margin-top:16px">
      <button class="btn-outline" onclick="closeModal('modalFacture')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveFacture()" style="flex:1" id="btnF"><span class="material-icons">receipt_long</span> Créer</button>
    </div>
  </div>
</div>

<!-- Modal encaissement -->
<div class="modal-overlay" id="modalEncaissement">
  <div class="modal">
    <div class="modal-header"><h3>Encaissement</h3><button class="modal-close" onclick="closeModal('modalEncaissement')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="eAlert"></div>
    <div id="eInfo" style="background:#f0f7ff;border-radius:8px;padding:12px;font-size:.83rem;color:var(--blue);margin-bottom:14px"></div>
    <input type="hidden" id="eFactureId">
    <div class="form-group"><label>Montant encaissé (FCFA) *</label><input type="number" id="eMontant" class="form-control" min="0"></div>
    <div class="form-group"><label>Mode de paiement *</label>
      <select id="eMode" class="form-control">
        <option value="especes">💵 Espèces</option><option value="carte">💳 Carte</option>
        <option value="mobile_money">📱 Mobile Money</option><option value="cheque">📄 Chèque</option>
      </select>
    </div>
    <div class="form-group"><label>Référence</label><input type="text" id="eRef" class="form-control" placeholder="N° transaction..."></div>
    <div style="display:flex;gap:12px;margin-top:14px">
      <button class="btn-outline" onclick="closeModal('modalEncaissement')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveEncaissement()" style="flex:1" id="btnE"><span class="material-icons">payments</span> Encaisser</button>
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
let st;function debounceSearch(){clearTimeout(st);st=setTimeout(()=>window.location.href='facturation.php?search='+encodeURIComponent(document.getElementById('searchInput').value)+'&statut=<?=urlencode($filter)?>',400);}
function openEncaissement(id,montant,nom){
  document.getElementById('eFactureId').value=id;document.getElementById('eMontant').value=montant;
  document.getElementById('eInfo').innerHTML='<strong>Facture #'+String(id).padStart(4,'0')+'</strong> · '+nom+'<br>Montant : <strong>'+Number(montant).toLocaleString('fr-FR')+' FCFA</strong>';
  document.getElementById('eAlert').className='alert-msg';openModal('modalEncaissement');
}
function saveFacture(){
  const alertEl=document.getElementById('fAlert');alertEl.className='alert-msg';
  const p=document.getElementById('fPatient').value,m=document.getElementById('fMontant').value;
  if(!p||!m){alertEl.textContent='Patient et montant requis.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnF');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/factures.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create',patient_id:p,montant_total:m})})
  .then(r=>r.json()).then(d=>{if(d.success){alertEl.textContent='Facture créée !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalFacture');location.reload()},1200);}else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">receipt_long</span> Créer';}});
}
function saveEncaissement(){
  const alertEl=document.getElementById('eAlert');alertEl.className='alert-msg';
  const m=document.getElementById('eMontant').value;
  if(!m){alertEl.textContent='Montant requis.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnE');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/factures.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'encaisser',facture_id:document.getElementById('eFactureId').value,montant_paye:m,mode_paiement:document.getElementById('eMode').value,reference:document.getElementById('eRef').value})})
  .then(r=>r.json()).then(d=>{if(d.success){alertEl.textContent='Paiement enregistré !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalEncaissement');location.reload()},1200);}else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">payments</span> Encaisser';}});
}
</script>
</body></html>
