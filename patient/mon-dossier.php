<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['patient']);
$user = getUser(); $pdo = getDB();
$patientId = $user['patient_id'] ?? null;

// Charger dossier
$s=$pdo->prepare("SELECT p.*,u.nom as med_nom,u.prenom as med_prenom,m.specialite,u2.telephone FROM patients p LEFT JOIN medecins m ON p.medecin_traitant_id=m.id LEFT JOIN utilisateurs u ON m.utilisateur_id=u.id JOIN utilisateurs u2 ON p.utilisateur_id=u2.id WHERE p.id=?");
$s->execute([$patientId]); $dossier=$s->fetch();

// Traitement mise à jour
$successMsg='';$errorMsg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $dn=sanitize($_POST['date_naissance']??'');$sexe=sanitize($_POST['sexe']??'');
    $gs=sanitize($_POST['groupe_sanguin']??'');$ville=sanitize($_POST['ville']??'');
    $adresse=sanitize($_POST['adresse']??'');$tel=sanitize($_POST['telephone']??'');
    $pdo->prepare("UPDATE patients SET date_naissance=?,sexe=?,groupe_sanguin=?,ville=?,adresse=? WHERE id=?")->execute([$dn?:null,$sexe?:null,$gs?:null,$ville,$adresse,$patientId]);
    if($tel) $pdo->prepare("UPDATE utilisateurs SET telephone=? WHERE id=?")->execute([$tel,$user['id']]);
    $successMsg='Dossier mis à jour avec succès.';
    $s=$pdo->prepare("SELECT p.*,u.nom as med_nom,u.prenom as med_prenom,m.specialite,u2.telephone FROM patients p LEFT JOIN medecins m ON p.medecin_traitant_id=m.id LEFT JOIN utilisateurs u ON m.utilisateur_id=u.id JOIN utilisateurs u2 ON p.utilisateur_id=u2.id WHERE p.id=?");
    $s->execute([$patientId]);$dossier=$s->fetch();
}

// Dernières consultations
$consults=$pdo->prepare("SELECT c.*,u.nom as med_nom,u.prenom as med_prenom FROM consultations c JOIN medecins m ON c.medecin_id=m.id JOIN utilisateurs u ON m.utilisateur_id=u.id WHERE c.patient_id=? ORDER BY c.date_consult DESC LIMIT 5");
$consults->execute([$patientId]);$consults=$consults->fetchAll();

$nbImpayees=(int)$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'")->execute([$patientId])&&($si=$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'"))&&$si->execute([$patientId])?$si->fetchColumn():0;
$si=$pdo->prepare("SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'");$si->execute([$patientId]);$nbImpayees=(int)$si->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Mon Dossier Médical</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .two-col{display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start}
    .card{background:#fff;border-radius:14px;padding:22px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:16px}
    .card-header{display:flex;align-items:center;gap:10px;margin-bottom:16px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase}
    /* Carte identité patient */
    .id-card{background:linear-gradient(135deg,#1a3a6e,#2563eb);border-radius:14px;padding:24px;color:#fff;text-align:center;margin-bottom:16px}
    .id-avatar{width:68px;height:68px;border-radius:50%;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;margin:0 auto 12px}
    .id-name{font-family:'Oswald',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:2px}
    .id-ref{font-size:.7rem;opacity:.6;margin-bottom:14px}
    .blood-badge{display:inline-flex;flex-direction:column;align-items:center;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.3);border-radius:10px;padding:8px 16px}
    .blood-value{font-family:'Oswald',sans-serif;font-size:1.2rem;font-weight:700}
    .blood-label{font-size:.62rem;opacity:.7;margin-top:1px}
    .info-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f0f4fa}
    .info-row:last-child{border:none}
    .info-row .material-icons{font-size:18px;color:var(--blue-bright);flex-shrink:0}
    .info-content .label{font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--muted)}
    .info-content .value{font-size:.88rem;font-weight:600;color:var(--text);margin-top:1px}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .alert{padding:12px 16px;border-radius:8px;font-size:.85rem;margin-bottom:16px;display:flex;align-items:center;gap:8px}
    .alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
    .consult-item{padding:10px 0;border-bottom:1px solid #f0f4fa}.consult-item:last-child{border:none}
    .complete-pct{font-family:'Oswald',sans-serif;font-size:1.4rem;font-weight:700;color:var(--blue)}
    @media(max-width:1000px){.two-col{grid-template-columns:1fr}}
    @media(max-width:600px){.form-row{grid-template-columns:1fr}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-patient">Patient</div>
  <div class="sidebar-user"><div class="user-name" id="sidebarUserName">—</div><div class="user-email" id="sidebarUserEmail">—</div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Mon espace</div>
    <a class="nav-item" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <a class="nav-item" href="#" onclick="openModal('modalRDV');return false;"><span class="material-icons">add_circle</span> Prendre un RDV</a>
    <a class="nav-item" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span> Mes rendez-vous</a>
    <a class="nav-item active" href="mon-dossier.php"><span class="material-icons">folder_shared</span> Mon dossier médical</a>
    <a class="nav-item" href="mes-examens.php"><span class="material-icons">science</span> Mes examens</a>
    <a class="nav-item" href="mes-factures.php"><span class="material-icons">receipt_long</span> Mes factures</a>
  
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
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher médecin, rendez-vous..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbImpayees>0):?><span class="notif-badge"><?=$nbImpayees?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">PT</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Patient</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Mon dossier médical</h1><p>Vos informations de santé personnelles</p></div>

    <?php if($successMsg):?><div class="alert alert-success"><span class="material-icons">check_circle</span><?=$successMsg?></div><?php endif;?>

    <div class="two-col">
      <!-- Colonne gauche : carte identité + infos résumé -->
      <div>
        <div class="id-card">
          <div class="id-avatar"><?=strtoupper(substr($user['prenom'],0,1).substr($user['nom'],0,1))?></div>
          <div class="id-name"><?=htmlspecialchars($user['prenom'].' '.$user['nom'])?></div>
          <div class="id-ref">#PT<?=str_pad($patientId,8,'0',STR_PAD_LEFT)?></div>
          <?php if($dossier&&$dossier['groupe_sanguin']):?>
          <div class="blood-badge"><div class="blood-value"><?=htmlspecialchars($dossier['groupe_sanguin'])?></div><div class="blood-label">Groupe sanguin</div></div>
          <?php else:?><div style="font-size:.75rem;opacity:.6">Groupe sanguin non renseigné</div><?php endif;?>
        </div>

        <div class="card">
          <div class="card-header"><span class="material-icons" style="color:var(--blue-bright)">person</span><h3>Informations</h3></div>
          <div class="info-row"><span class="material-icons">cake</span><div class="info-content"><div class="label">Date de naissance</div><div class="value"><?=$dossier&&$dossier['date_naissance']?date('d/m/Y',strtotime($dossier['date_naissance'])):'Non renseigné'?></div></div></div>
          <div class="info-row"><span class="material-icons">wc</span><div class="info-content"><div class="label">Sexe</div><div class="value"><?=$dossier?($dossier['sexe']==='M'?'Masculin':($dossier['sexe']==='F'?'Féminin':'Non renseigné')):'Non renseigné'?></div></div></div>
          <div class="info-row"><span class="material-icons">location_on</span><div class="info-content"><div class="label">Ville</div><div class="value"><?=htmlspecialchars($dossier['ville']??'Non renseigné')?></div></div></div>
          <div class="info-row"><span class="material-icons">phone</span><div class="info-content"><div class="label">Téléphone</div><div class="value"><?=htmlspecialchars($dossier['telephone']??'Non renseigné')?></div></div></div>
          <div class="info-row"><span class="material-icons">stethoscope</span><div class="info-content"><div class="label">Médecin traitant</div><div class="value"><?=$dossier&&$dossier['med_nom']?'Dr. '.htmlspecialchars($dossier['med_prenom'].' '.$dossier['med_nom']):'Non assigné'?></div></div></div>
        </div>

        <?php if(!empty($consults)):?>
        <div class="card">
          <div class="card-header"><span class="material-icons" style="color:var(--blue-bright)">history</span><h3>Dernières consultations</h3></div>
          <?php foreach($consults as $c):?>
          <div class="consult-item">
            <div style="display:flex;justify-content:space-between;margin-bottom:3px">
              <strong style="font-size:.84rem">Dr. <?=htmlspecialchars($c['med_prenom'].' '.$c['med_nom'])?></strong>
              <span style="font-size:.72rem;color:var(--muted)"><?=date('d/m/Y',strtotime($c['date_consult']))?></span>
            </div>
            <div style="font-size:.76rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(substr($c['diagnostic']??'—',0,60))?></div>
          </div>
          <?php endforeach;?>
        </div>
        <?php endif;?>
      </div>

      <!-- Colonne droite : formulaire mise à jour -->
      <div class="card">
        <div class="card-header"><span class="material-icons" style="color:var(--blue-bright)">edit</span><h3>Mettre à jour mes informations</h3></div>
        <?php
        $fields = ['date_naissance','sexe','groupe_sanguin','ville','adresse'];
        $filled = 0;
        foreach($fields as $f) if(!empty($dossier[$f])) $filled++;
        $pct = round(($filled/count($fields))*100);
        ?>
        <div style="margin-bottom:20px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <span style="font-size:.8rem;color:var(--muted)">Complétude du dossier</span>
            <span class="complete-pct"><?=$pct?>%</span>
          </div>
          <div style="background:#eef0f6;border-radius:20px;height:8px;overflow:hidden">
            <div style="background:<?=$pct>=80?'var(--success)':($pct>=50?'var(--warning)':'var(--danger)')?>;height:100%;width:<?=$pct?>%;border-radius:20px;transition:width .6s ease"></div>
          </div>
          <?php if($pct<100):?><p style="font-size:.75rem;color:var(--muted);margin-top:6px">Complétez votre dossier pour un meilleur suivi médical.</p><?php endif;?>
        </div>

        <form method="POST">
          <div class="form-row">
            <div class="form-group"><label>Date de naissance</label><input type="date" name="date_naissance" class="form-control" value="<?=htmlspecialchars($dossier['date_naissance']??'')?>"></div>
            <div class="form-group"><label>Sexe</label>
              <select name="sexe" class="form-control">
                <option value="">Sélectionner...</option>
                <option value="M" <?=$dossier&&$dossier['sexe']==='M'?'selected':''?>>Masculin</option>
                <option value="F" <?=$dossier&&$dossier['sexe']==='F'?'selected':''?>>Féminin</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>Groupe sanguin</label>
              <select name="groupe_sanguin" class="form-control">
                <option value="">Sélectionner...</option>
                <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                <option value="<?=$g?>" <?=$dossier&&$dossier['groupe_sanguin']===$g?'selected':''?>><?=$g?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" class="form-control" value="<?=htmlspecialchars($dossier['telephone']??'')?>" placeholder="+225 07 00 00 00"></div>
          </div>
          <div class="form-group"><label>Ville</label><input type="text" name="ville" class="form-control" value="<?=htmlspecialchars($dossier['ville']??'')?>" placeholder="Votre ville..."></div>
          <div class="form-group"><label>Adresse complète</label><textarea name="adresse" class="form-control" rows="3" placeholder="Votre adresse..."><?=htmlspecialchars($dossier['adresse']??'')?></textarea></div>
          <button type="submit" class="btn-primary" style="width:100%"><span class="material-icons">save</span> Enregistrer les modifications</button>
        </form>
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
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
</script>
</body></html>
