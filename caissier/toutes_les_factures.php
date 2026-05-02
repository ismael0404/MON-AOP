<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['caissier']);
$user = getUser(); $pdo = getDB();

$filter = sanitize($_GET['statut'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$page   = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;

$where="WHERE 1=1"; $params=[];
if($filter){$where.=" AND f.statut=?";$params[]=$filter;}
if($search){$where.=" AND (u.nom LIKE ? OR u.prenom LIKE ?)";$params[]="%$search%";$params[]="%$search%";}

$stmtC=$pdo->prepare("SELECT COUNT(*) FROM factures f JOIN patients p ON f.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id $where");
$stmtC->execute($params);$total=(int)$stmtC->fetchColumn();$totalPages=ceil($total/$perPage);

$stmt=$pdo->prepare("
    SELECT f.*,u.nom,u.prenom,COALESCE(SUM(pm.montant_paye),0) as total_paye
    FROM factures f
    JOIN patients p ON f.patient_id=p.id JOIN utilisateurs u ON p.utilisateur_id=u.id
    LEFT JOIN paiements pm ON f.id=pm.facture_id
    $where GROUP BY f.id ORDER BY f.date_facture DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);$factures=$stmt->fetchAll();

$nbImpayees=(int)$pdo->query("SELECT COUNT(*) FROM factures WHERE statut='impayee'")->fetchColumn();
$pts=$pdo->query("SELECT p.id,u.nom,u.prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id WHERE u.actif=1 ORDER BY u.nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Toutes les Factures</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
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
    <a class="nav-item active" href="toutes_les_factures.php"><span class="material-icons">receipt_long</span> Toutes les factures</a>
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
    <div class="page-header" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div><h1>Toutes les factures</h1><p><?=$total?> facture(s) au total</p></div>
      <a href="nouvelle_facture.php" class="btn-primary"><span class="material-icons">add</span> Nouvelle facture</a>
    </div>

    <div class="filter-bar">
      <div class="search-wrap">
        <span class="material-icons">search</span>
        <input type="text" id="searchInput" placeholder="Rechercher patient..." value="<?=htmlspecialchars($search)?>" onkeyup="debounceSearch()">
      </div>
      <a class="filter-btn <?= !$filter?'active':'' ?>" href="toutes_les_factures.php">Toutes</a>
      <a class="filter-btn <?= $filter==='impayee'?'active':'' ?>"  href="?statut=impayee">Impayées</a>
      <a class="filter-btn <?= $filter==='partielle'?'active':'' ?>" href="?statut=partielle">Partielles</a>
      <a class="filter-btn <?= $filter==='payee'?'active':'' ?>"    href="?statut=payee">Payées</a>
    </div>

    <div class="table-card">
      <div class="table-header"><h3>Factures</h3><span style="font-size:.8rem;color:var(--muted)">Page <?=$page?>/<?=max(1,$totalPages)?></span></div>
      <div style="overflow-x:auto"><table class="klinik-table">
        <thead><tr><th>#</th><th>Patient</th><th>Montant</th><th>Payé</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if(empty($factures)):?><tr><td colspan="7" style="text-align:center;padding:28px;color:var(--muted)">Aucune facture trouvée</td></tr>
          <?php else: foreach($factures as $f): ?>
          <tr>
            <td style="font-family:'Oswald',sans-serif;font-weight:700;color:var(--muted)">#<?=str_pad($f['id'],4,'0',STR_PAD_LEFT)?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:30px;height:30px;border-radius:50%;background:var(--blue-light);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:.72rem;font-weight:700;color:var(--blue);flex-shrink:0"><?=strtoupper(substr($f['prenom'],0,1).substr($f['nom'],0,1))?></div>
                <strong style="font-size:.88rem"><?=htmlspecialchars($f['prenom'].' '.$f['nom'])?></strong>
              </div>
            </td>
            <td><strong style="font-family:'Oswald',sans-serif"><?=number_format($f['montant_total'],0,',',' ')?> FCFA</strong></td>
            <td style="font-size:.84rem;color:var(--success)"><?=number_format($f['total_paye'],0,',',' ')?> FCFA</td>
            <td><span class="status-badge <?=$f['statut']==='payee'?'status-active':($f['statut']==='partielle'?'status-pending':'status-inactive')?>"><?=$f['statut']==='payee'?'Payée':($f['statut']==='partielle'?'Partielle':'Impayée')?></span></td>
            <td style="font-size:.82rem;color:var(--muted)"><?=date('d/m/Y',strtotime($f['date_facture']))?></td>
            <td>
              <?php if($f['statut']!=='payee'): ?>
              <button class="btn-primary" style="padding:5px 12px;font-size:.76rem"
                onclick="openEncaissement(<?=$f['id']?>,<?=$f['montant_total']?>,<?=$f['total_paye']?>,'<?=htmlspecialchars($f['prenom'].' '.$f['nom'],ENT_QUOTES)?>')">
                <span class="material-icons" style="font-size:14px">payments</span> Encaisser
              </button>
              <?php else: ?><span style="color:var(--success);font-size:.82rem;font-weight:600">✓ Soldée</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach;endif; ?>
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
        <option value="especes">💵 Espèces</option>
        <option value="mobile_money">📱 Mobile Money</option>
        <option value="carte">💳 Carte bancaire</option>
        <option value="cheque">📄 Chèque</option>
      </select>
    </div>
    <div class="form-group"><label>Référence transaction</label><input type="text" id="eRef" class="form-control" placeholder="N° transaction, reçu..."></div>
    <div style="display:flex;gap:12px;margin-top:14px">
      <button class="btn-outline" onclick="closeModal('modalEnc')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveEncaissement()" style="flex:2" id="btnE"><span class="material-icons">payments</span> Enregistrer paiement</button>
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
let st;function debounceSearch(){clearTimeout(st);st=setTimeout(()=>window.location.href='toutes_les_factures.php?search='+encodeURIComponent(document.getElementById('searchInput').value)+'&statut=<?=urlencode($filter)?>',400);}
function openEncaissement(id,total,paye,nom){
  document.getElementById('eId').value=id;
  const reste=total-paye;
  document.getElementById('eMontant').value=reste;
  document.getElementById('eInfo').innerHTML=`
    <div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="font-weight:700">Facture #${String(id).padStart(4,'0')}</span><span style="color:var(--muted);font-size:.82rem">${nom}</span></div>
    <div style="display:flex;gap:16px;font-size:.82rem">
      <span>Total : <strong>${Number(total).toLocaleString('fr-FR')} FCFA</strong></span>
      <span style="color:var(--success)">Payé : <strong>${Number(paye).toLocaleString('fr-FR')} FCFA</strong></span>
      <span style="color:var(--danger)">Reste : <strong>${Number(reste).toLocaleString('fr-FR')} FCFA</strong></span>
    </div>
  `;
  document.getElementById('eAlert').className='alert-msg';
  openModal('modalEnc');
}
function saveEncaissement(){
  const alertEl=document.getElementById('eAlert');alertEl.className='alert-msg';
  const m=document.getElementById('eMontant').value;
  if(!m||Number(m)<=0){alertEl.textContent='Montant requis.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnE');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/factures.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'encaisser',facture_id:document.getElementById('eId').value,montant_paye:m,mode_paiement:document.getElementById('eMode').value,reference:document.getElementById('eRef').value})})
  .then(r=>r.json()).then(d=>{
    if(d.success){alertEl.textContent='Paiement enregistré !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalEnc');location.reload()},1200);}
    else{alertEl.textContent=d.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">payments</span> Enregistrer paiement';}
  });
}
</script>
</body></html>
