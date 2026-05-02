<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['admin']);
$user = getUser();
$pdo  = getDB();

$search = sanitize($_GET['search'] ?? '');
$page = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;

$where = "WHERE 1=1"; $params = [];
if ($search) {
    $where .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR p.ville LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id $where");
$stmtC->execute($params); $total = (int)$stmtC->fetchColumn(); $totalPages = ceil($total/$perPage);

$stmt = $pdo->prepare("
    SELECT p.*,u.nom,u.prenom,u.email,u.telephone,
           um.nom as med_nom,um.prenom as med_prenom,m.specialite,
           COUNT(DISTINCT c.id) as nb_consult,
           COUNT(DISTINCT e.id) as nb_examens
    FROM patients p
    JOIN utilisateurs u ON p.utilisateur_id=u.id
    LEFT JOIN medecins m ON p.medecin_traitant_id=m.id
    LEFT JOIN utilisateurs um ON m.utilisateur_id=um.id
    LEFT JOIN consultations c ON p.id=c.patient_id
    LEFT JOIN examens e ON c.id=e.consultation_id
    $where GROUP BY p.id ORDER BY u.nom ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $patients = $stmt->fetchAll();

$patientDetail = null; $consultDetail = []; $examenDetail = [];
if (isset($_GET['pid'])) {
    $pid = (int)$_GET['pid'];
    $s=$pdo->prepare("SELECT p.*,u.nom,u.prenom,u.email,u.telephone,um.nom as med_nom,um.prenom as med_prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id LEFT JOIN medecins m ON p.medecin_traitant_id=m.id LEFT JOIN utilisateurs um ON m.utilisateur_id=um.id WHERE p.id=?");
    $s->execute([$pid]); $patientDetail = $s->fetch();
    $cd=$pdo->prepare("SELECT c.*,um.nom as med_nom,um.prenom as med_prenom FROM consultations c JOIN medecins m ON c.medecin_id=m.id JOIN utilisateurs um ON m.utilisateur_id=um.id WHERE c.patient_id=? ORDER BY c.date_consult DESC LIMIT 8");
    $cd->execute([$pid]); $consultDetail=$cd->fetchAll();
    $ed=$pdo->prepare("SELECT e.* FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.patient_id=? ORDER BY e.date_demande DESC LIMIT 5");
    $ed->execute([$pid]); $examenDetail=$ed->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Dossiers médicaux</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .two-col{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start}
    .card{background:#fff;border-radius:14px;padding:20px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:16px}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase}
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:14px 18px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase}
    .search-wrap{position:relative;margin-bottom:16px}
    .search-wrap .material-icons{position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:17px;color:#9ca3af}
    .search-wrap input{width:100%;padding:9px 12px 9px 32px;border:1.5px solid #eef0f6;border-radius:8px;font-size:.86rem;outline:none;background:#f8f9fc}
    .search-wrap input:focus{border-color:var(--blue-bright);background:#fff}
    .dossier-header{background:linear-gradient(135deg,var(--blue),#2563eb);border-radius:12px;padding:18px;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:14px}
    .dossier-avatar{width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:1.15rem;font-weight:700;flex-shrink:0}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
    .info-block{background:#f8f9fc;border-radius:8px;padding:10px}
    .ib-label{font-size:.67rem;font-weight:700;text-transform:uppercase;color:var(--muted)}
    .ib-value{font-size:.86rem;color:var(--text);margin-top:2px;font-weight:600}
    .consult-item{padding:10px 0;border-bottom:1px solid #f0f4fa}.consult-item:last-child{border:none}
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted)}
    .page-btns{display:flex;gap:5px}.page-btn{padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}.page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}
    @media(max-width:1100px){.two-col{grid-template-columns:1fr}}
  </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-admin">Administrateur</div>
  <div class="sidebar-user">
    <div class="user-name" id="sidebarUserName">—</div>
    <div class="user-email" id="sidebarUserEmail">—</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Gestion</div>
    <a class="nav-item" href="utilisateurs.php"><span class="material-icons">manage_accounts</span> Utilisateurs</a>
    <a class="nav-item" href="historique.php"><span class="material-icons">calendar_today</span> Historique des Rendez-vous</a>
    <a class="nav-item active" href="dossiers_medicaux.php"><span class="material-icons">folder_shared</span> Dossiers médicaux</a>
    <a class="nav-item" href="facturation.php"><span class="material-icons">receipt_long</span> Facturation</a>
    
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
    <div class="nav-section-title">Système</div>
    <a class="nav-item" href="rapports.php"><span class="material-icons">bar_chart</span> Rapports</a>
    <a class="nav-item" href="parametres.php"><span class="material-icons">settings</span> Paramètres</a>
  </nav>
  <div class="sidebar-footer">
    <a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a>
  </div>
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
        <div class="topbar-user-info">
          <div class="topbar-user-name" id="topbarUserName">—</div>
          <div class="topbar-user-role">Administrateur</div>
        </div>
      </div>
    </div>
  </header>

  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px">
      <h1>Dossiers médicaux</h1>
      <p>Registre complet des patients — <?=$total?> patient(s)</p>
    </div>

    <div class="two-col">
      <!-- Liste patients -->
      <div>
        <div class="search-wrap">
          <span class="material-icons">search</span>
          <input type="text" id="searchInput" placeholder="Rechercher par nom, email, ville..." value="<?=htmlspecialchars($search)?>" onkeyup="debounceSearch()">
        </div>
        <div class="table-card">
          <div class="table-header"><h3>Patients</h3><span style="font-size:.8rem;color:var(--muted)"><?=$total?> patient(s)</span></div>
          <div style="overflow-x:auto"><table class="klinik-table">
            <thead><tr><th>#</th><th>Patient</th><th>Groupe</th><th>Ville</th><th>Médecin traitant</th><th>Consult.</th><th>Action</th></tr></thead>
            <tbody>
              <?php if(empty($patients)):?><tr><td colspan="7" style="text-align:center;padding:28px;color:var(--muted)">Aucun patient trouvé</td></tr>
              <?php else: foreach($patients as $i=>$p):?>
              <tr>
                <td style="color:var(--muted);font-size:.78rem"><?=str_pad($offset+$i+1,3,'0',STR_PAD_LEFT)?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:9px">
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--blue-light);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:.78rem;font-weight:700;color:var(--blue);flex-shrink:0"><?=strtoupper(substr($p['prenom'],0,1).substr($p['nom'],0,1))?></div>
                    <div>
                      <div style="font-weight:600;font-size:.88rem"><?=htmlspecialchars($p['prenom'].' '.$p['nom'])?></div>
                      <div style="font-size:.72rem;color:var(--muted)"><?=htmlspecialchars($p['email']??'')?></div>
                    </div>
                  </div>
                </td>
                <td><?=$p['groupe_sanguin']?'<span style="background:#fee2e2;color:#991b1b;padding:2px 7px;border-radius:10px;font-size:.7rem;font-weight:700">'.htmlspecialchars($p['groupe_sanguin']).'</span>':'—'?></td>
                <td style="font-size:.82rem;color:var(--muted)"><?=htmlspecialchars($p['ville']??'—')?></td>
                <td style="font-size:.82rem"><?=$p['med_nom']?'Dr. '.htmlspecialchars($p['med_prenom'].' '.$p['med_nom']):'<span style="color:var(--muted)">—</span>'?></td>
                <td style="text-align:center"><strong><?=$p['nb_consult']?></strong></td>
                <td>
                  <a href="?pid=<?=$p['id']?>&search=<?=urlencode($search)?>&page=<?=$page?>" class="btn-outline" style="padding:5px 10px;font-size:.76rem">
                    <span class="material-icons" style="font-size:14px">visibility</span> Dossier
                  </a>
                </td>
              </tr>
              <?php endforeach;endif;?>
            </tbody>
          </table></div>
          <div class="pagination">
            <span>Affichage <?=min($offset+1,$total)?>–<?=min($offset+$perPage,$total)?> sur <?=$total?></span>
            <div class="page-btns">
              <?php if($page>1):?><a class="page-btn" href="?page=<?=$page-1?>&search=<?=urlencode($search)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif;?>
              <?php for($pg=max(1,$page-2);$pg<=min($totalPages,$page+2);$pg++):?><a class="page-btn <?=$pg===$page?'active':''?>" href="?page=<?=$pg?>&search=<?=urlencode($search)?>"><?=$pg?></a><?php endfor;?>
              <?php if($page<$totalPages):?><a class="page-btn" href="?page=<?=$page+1?>&search=<?=urlencode($search)?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif;?>
            </div>
          </div>
        </div>
      </div>

      <!-- Dossier détail -->
      <div>
        <?php if($patientDetail):?>
        <div class="card">
          <div class="dossier-header">
            <div class="dossier-avatar"><?=strtoupper(substr($patientDetail['prenom'],0,1).substr($patientDetail['nom'],0,1))?></div>
            <div>
              <div style="font-family:'Oswald',sans-serif;font-size:1.05rem;font-weight:700"><?=htmlspecialchars($patientDetail['prenom'].' '.$patientDetail['nom'])?></div>
              <div style="font-size:.72rem;opacity:.7">#PT<?=str_pad($patientDetail['id'],8,'0',STR_PAD_LEFT)?></div>
            </div>
          </div>
          <div class="info-grid">
            <div class="info-block"><div class="ib-label">Naissance</div><div class="ib-value"><?=$patientDetail['date_naissance']?date('d/m/Y',strtotime($patientDetail['date_naissance'])):'—'?></div></div>
            <div class="info-block"><div class="ib-label">Groupe sanguin</div><div class="ib-value"><?=htmlspecialchars($patientDetail['groupe_sanguin']??'—')?></div></div>
            <div class="info-block"><div class="ib-label">Sexe</div><div class="ib-value"><?=$patientDetail['sexe']==='M'?'Masculin':($patientDetail['sexe']==='F'?'Féminin':'—')?></div></div>
            <div class="info-block"><div class="ib-label">Ville</div><div class="ib-value"><?=htmlspecialchars($patientDetail['ville']??'—')?></div></div>
            <div class="info-block" style="grid-column:1/-1"><div class="ib-label">Médecin traitant</div><div class="ib-value"><?=$patientDetail['med_nom']?'Dr. '.htmlspecialchars($patientDetail['med_prenom'].' '.$patientDetail['med_nom']):'—'?></div></div>
          </div>

          <div class="card-header" style="margin-bottom:10px"><h3>Consultations</h3></div>
          <?php if(empty($consultDetail)):?><p style="color:var(--muted);font-size:.83rem;text-align:center;padding:10px">Aucune consultation</p>
          <?php else: foreach($consultDetail as $c):?>
          <div class="consult-item">
            <div style="display:flex;justify-content:space-between;margin-bottom:3px">
              <strong style="font-size:.84rem">Dr. <?=htmlspecialchars($c['med_prenom'].' '.$c['med_nom'])?></strong>
              <span style="font-size:.72rem;color:var(--muted)"><?=date('d/m/Y',strtotime($c['date_consult']))?></span>
            </div>
            <div style="font-size:.77rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(substr($c['diagnostic']??'—',0,65))?></div>
          </div>
          <?php endforeach;endif;?>
        </div>
        <?php else:?>
        <div class="card" style="text-align:center;padding:40px 20px">
          <span class="material-icons" style="font-size:42px;color:var(--border)">folder_shared</span>
          <p style="color:var(--muted);margin-top:10px;font-size:.88rem">Cliquez sur "Dossier" pour consulter les informations d'un patient</p>
        </div>
        <?php endif;?>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/klinik.js"></script>
<script>
const user = { nom:'<?=htmlspecialchars($user["nom"])?>', prenom:'<?=htmlspecialchars($user["prenom"])?>', email:'<?=htmlspecialchars($user["email"])?>', role:'<?=$user["role"]?>' };
KlinikUI.fillUserInfo(user); KlinikUI.initSidebar();
document.getElementById('topbarUserName').textContent = user.prenom+' '+user.nom;
document.getElementById('topbarAvatar').textContent   = (user.prenom[0]+user.nom[0]).toUpperCase();
let st; function debounceSearch(){clearTimeout(st);st=setTimeout(()=>window.location.href='dossiers_medicaux.php?search='+encodeURIComponent(document.getElementById('searchInput').value)+'&page=1',400);}
</script>
</body>
</html>
