<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['admin']);
$user = getUser(); $pdo = getDB();

$successMsg = ''; $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'compte') {
        $nom=sanitize($_POST['nom']??'');$prenom=sanitize($_POST['prenom']??'');
        $email=sanitize($_POST['email']??'');$tel=sanitize($_POST['tel']??'');
        if ($nom&&$prenom&&$email) {
            $s=$pdo->prepare("SELECT id FROM utilisateurs WHERE email=? AND id!=?");$s->execute([$email,$user['id']]);
            if ($s->fetch()) { $errorMsg='Cet email est déjà utilisé.'; }
            else {
                $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,telephone=?,updated_at=NOW() WHERE id=?")->execute([$nom,$prenom,$email,$tel,$user['id']]);
                $_SESSION['user']['nom']=$nom;$_SESSION['user']['prenom']=$prenom;$_SESSION['user']['email']=$email;
                $user=getUser(); $successMsg='Informations mises à jour.';
            }
        } else { $errorMsg='Champs obligatoires manquants.'; }
    }
    if ($_POST['action'] === 'password') {
        $anc=$_POST['ancien']??'';$nou=$_POST['nouveau']??'';$conf=$_POST['confirm']??'';
        $s=$pdo->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id=?");$s->execute([$user['id']]);$row=$s->fetch();
        if (!password_verify($anc,$row['mot_de_passe'])) { $errorMsg='Mot de passe actuel incorrect.'; }
        elseif (strlen($nou)<6) { $errorMsg='Nouveau mot de passe trop court (6 car. min).'; }
        elseif ($nou!==$conf) { $errorMsg='Les mots de passe ne correspondent pas.'; }
        else { $pdo->prepare("UPDATE utilisateurs SET mot_de_passe=?,updated_at=NOW() WHERE id=?")->execute([password_hash($nou,PASSWORD_DEFAULT),$user['id']]); $successMsg='Mot de passe modifié.'; }
    }
}
$adminInfo=$pdo->prepare("SELECT * FROM utilisateurs WHERE id=?");$adminInfo->execute([$user['id']]);$adminInfo=$adminInfo->fetch();
$section = $_POST['action'] ?? 'compte';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Paramètres</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .settings-layout{display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start}
    .settings-nav{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .snav-item{display:flex;align-items:center;gap:10px;padding:13px 16px;cursor:pointer;transition:all .2s;font-size:.88rem;color:var(--text);border-left:3px solid transparent;text-decoration:none}
    .snav-item:hover{background:#f8f9fc;color:var(--blue)}.snav-item.active{background:var(--blue-light);border-left-color:var(--blue-bright);color:var(--blue);font-weight:600}
    .snav-item .material-icons{font-size:18px;color:var(--muted)}.snav-item.active .material-icons{color:var(--blue-bright)}
    .section{display:none}.section.active{display:block}
    .settings-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;padding:26px;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .settings-card h2{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase;margin-bottom:4px}
    .settings-card .subtitle{font-size:.83rem;color:var(--muted);margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #eef0f6}
    .profile-header{background:linear-gradient(135deg,var(--blue),#2563eb);border-radius:12px;padding:16px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:20px}
    .profile-avatar{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:1.2rem;font-weight:700;flex-shrink:0}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .alert{padding:11px 14px;border-radius:8px;font-size:.85rem;margin-bottom:14px;display:flex;align-items:center;gap:8px}
    .alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
    .alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .info-block{background:#f8f9fc;border-radius:10px;padding:14px;display:flex;align-items:center;gap:10px}
    .info-block .material-icons{font-size:20px;color:var(--blue-bright)}
    .ib-label{font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--muted)}
    .ib-value{font-size:.88rem;color:var(--text);margin-top:2px;font-weight:600}
    @media(max-width:900px){.settings-layout{grid-template-columns:1fr}.form-row{grid-template-columns:1fr}}
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
    <a class="nav-item active" href="parametres.php"><span class="material-icons">settings</span> Paramètres</a>
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
      <div class="topbar-user"><div class="topbar-avatar" id="topbarAvatar">AD</div><div class="topbar-user-info"><div class="topbar-user-name" id="topbarUserName">—</div><div class="topbar-user-role">Administrateur</div></div></div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px"><h1>Paramètres</h1><p>Configuration du système et du compte</p></div>

    <?php if($successMsg):?><div class="alert alert-success"><span class="material-icons">check_circle</span><?=$successMsg?></div><?php endif;?>
    <?php if($errorMsg):?><div class="alert alert-error"><span class="material-icons">error</span><?=$errorMsg?></div><?php endif;?>

    <div class="settings-layout">
      <div class="settings-nav">
        <a class="snav-item active" onclick="show('clinique')" href="#"><span class="material-icons">local_hospital</span> Clinique</a>
        <a class="snav-item" onclick="show('compte')" href="#"><span class="material-icons">manage_accounts</span> Mon compte</a>
        <a class="snav-item" onclick="show('securite')" href="#"><span class="material-icons">lock</span> Sécurité</a>
        <a class="snav-item" onclick="show('systeme')" href="#"><span class="material-icons">settings</span> Système</a>
      </div>
      <div>
        <!-- Clinique -->
        <div class="section active" id="sec-clinique">
          <div class="settings-card">
            <h2>Informations de la clinique</h2>
            <p class="subtitle">Informations générales de KLINIK</p>
            <div class="info-grid">
              <div class="info-block"><span class="material-icons">local_hospital</span><div><div class="ib-label">Nom</div><div class="ib-value">KLINIK</div></div></div>
              <div class="info-block"><span class="material-icons">location_city</span><div><div class="ib-label">Ville</div><div class="ib-value">Abidjan, Côte d'Ivoire</div></div></div>
              <div class="info-block"><span class="material-icons">phone</span><div><div class="ib-label">Téléphone</div><div class="ib-value">+225 07 00 00 00</div></div></div>
              <div class="info-block"><span class="material-icons">email</span><div><div class="ib-label">Email</div><div class="ib-value">contact@klinik.ci</div></div></div>
              <div class="info-block"><span class="material-icons">schedule</span><div><div class="ib-label">Horaires</div><div class="ib-value">Lun–Sam · 7h–19h</div></div></div>
              <div class="info-block"><span class="material-icons">people</span><div><div class="ib-label">Utilisateurs actifs</div><div class="ib-value"><?=(int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE actif=1")->fetchColumn()?> comptes</div></div></div>
            </div>
          </div>
        </div>
        <!-- Compte -->
        <div class="section" id="sec-compte">
          <div class="settings-card">
            <div class="profile-header">
              <div class="profile-avatar"><?=strtoupper(substr($adminInfo['prenom'],0,1).substr($adminInfo['nom'],0,1))?></div>
              <div><div style="font-family:'Oswald',sans-serif;font-size:1rem;font-weight:700"><?=htmlspecialchars($adminInfo['prenom'].' '.$adminInfo['nom'])?></div><div style="font-size:.72rem;opacity:.7"><?=htmlspecialchars($adminInfo['email'])?></div></div>
            </div>
            <h2>Modifier mes informations</h2>
            <p class="subtitle">Mettez à jour vos informations personnelles</p>
            <form method="POST">
              <input type="hidden" name="action" value="compte">
              <div class="form-row">
                <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" value="<?=htmlspecialchars($adminInfo['nom'])?>" required></div>
                <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" class="form-control" value="<?=htmlspecialchars($adminInfo['prenom'])?>" required></div>
              </div>
              <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" value="<?=htmlspecialchars($adminInfo['email'])?>" required></div>
              <div class="form-group"><label>Téléphone</label><input type="tel" name="tel" class="form-control" value="<?=htmlspecialchars($adminInfo['telephone']??'')?>"></div>
              <button type="submit" class="btn-primary"><span class="material-icons">save</span> Enregistrer</button>
            </form>
          </div>
        </div>
        <!-- Sécurité -->
        <div class="section" id="sec-securite">
          <div class="settings-card">
            <h2>Changer le mot de passe</h2>
            <p class="subtitle">Utilisez un mot de passe fort d'au moins 6 caractères</p>
            <form method="POST">
              <input type="hidden" name="action" value="password">
              <div class="form-group"><label>Mot de passe actuel *</label><input type="password" name="ancien" class="form-control" required></div>
              <div class="form-group"><label>Nouveau mot de passe *</label><input type="password" name="nouveau" class="form-control" required></div>
              <div class="form-group"><label>Confirmer *</label><input type="password" name="confirm" class="form-control" required></div>
              <button type="submit" class="btn-primary"><span class="material-icons">lock</span> Modifier</button>
            </form>
          </div>
        </div>
        <!-- Système -->
        <div class="section" id="sec-systeme">
          <div class="settings-card">
            <h2>Informations système</h2>
            <p class="subtitle">Détails techniques de l'installation</p>
            <div class="info-grid">
              <div class="info-block"><span class="material-icons">code</span><div><div class="ib-label">Version</div><div class="ib-value">KLINIK v1.0</div></div></div>
              <div class="info-block"><span class="material-icons">storage</span><div><div class="ib-label">Base de données</div><div class="ib-value">MySQL</div></div></div>
              <div class="info-block"><span class="material-icons">developer_mode</span><div><div class="ib-label">PHP</div><div class="ib-value"><?=phpversion()?></div></div></div>
              <div class="info-block"><span class="material-icons">calendar_today</span><div><div class="ib-label">Serveur</div><div class="ib-value"><?=date('d/m/Y H:i')?></div></div></div>
            </div>
          </div>
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
function show(id){
  document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.snav-item').forEach(n=>n.classList.remove('active'));
  document.getElementById('sec-'+id).classList.add('active');
  event.currentTarget.classList.add('active');
}
<?php if($successMsg||$errorMsg): $act=$section==='password'?'securite':'compte'; ?>
show('<?=$act?>');
document.querySelectorAll('.snav-item').forEach(n=>n.classList.remove('active'));
document.querySelectorAll('.snav-item')[<?=$section==='password'?2:1?>].classList.add('active');
<?php endif;?>
</script>
</body></html>
