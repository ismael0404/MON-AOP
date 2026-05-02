<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['admin']);
$user = getUser();
$pdo  = getDB();

// ── Stats depuis la BD ──
$nbPatients     = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='patient' AND actif=1")->fetchColumn();
$nbMedecins     = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='medecin' AND actif=1")->fetchColumn();
$nbConsultAuj   = $pdo->query("SELECT COUNT(*) FROM consultations WHERE DATE(date_consult)=CURDATE()")->fetchColumn();
$nbUtilisateurs = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE actif=1")->fetchColumn();

// ── Derniers utilisateurs ──
$derniers = $pdo->query("SELECT * FROM utilisateurs ORDER BY created_at DESC LIMIT 8")->fetchAll();

// ── Activité 7 derniers jours ──
$activite = $pdo->query("
    SELECT DATE(date_consult) as jour, COUNT(*) as nb
    FROM consultations
    WHERE date_consult >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(date_consult)
    ORDER BY jour ASC
")->fetchAll();

$jours = []; $vals = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $jours[] = date('d/m', strtotime($d));
    $found = array_filter($activite, fn($r) => $r['jour'] === $d);
    $vals[] = $found ? array_values($found)[0]['nb'] : 0;
}

$roleLabels = [
    'admin'      => 'Administrateur',
    'medecin'    => 'Médecin',
    'patient'    => 'Patient',
    'laborantin' => 'Laborantin',
    'caissier'   => 'Caissier',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KLINIK — Tableau de bord Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    /* ── Layout 3 colonnes ── */
    .dash-body {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 24px;
    }
    .dash-left  { min-width: 0; }
    .dash-right { display: flex; flex-direction: column; gap: 20px; }

    /* ── Stats cards ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: #fff;
      border-radius: 14px;
      padding: 22px 20px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 2px 12px rgba(26,58,110,.06);
      border: 1.5px solid var(--border);
      transition: transform .25s, box-shadow .25s;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(26,58,110,.1); }
    .stat-icon {
      width: 52px; height: 52px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .stat-icon .material-icons { font-size: 26px; color: #fff; }
    .stat-value { font-family: 'Oswald', sans-serif; font-size: 1.9rem; font-weight: 700; color: var(--blue); line-height: 1; }
    .stat-label { font-size: .78rem; color: var(--muted); margin-top: 4px; }
    .stat-trend { font-size: .72rem; color: var(--success); margin-top: 3px; display: flex; align-items: center; gap: 2px; }

    /* ── Graphique ── */
    .chart-card {
      background: #fff; border-radius: 14px; padding: 24px;
      box-shadow: 0 2px 12px rgba(26,58,110,.06); border: 1.5px solid var(--border);
      margin-bottom: 24px;
    }
    .chart-header {
      display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;
    }
    .chart-header h3 {
      font-family: 'Oswald', sans-serif; font-size: 1rem;
      color: var(--blue); text-transform: uppercase; letter-spacing: .5px;
    }
    .chart-badge {
      font-size: .72rem; font-weight: 600; padding: 4px 12px;
      border-radius: 20px; background: var(--blue-light); color: var(--blue);
    }

    /* ── Table utilisateurs ── */
    .table-card {
      background: #fff; border-radius: 14px; padding: 24px;
      box-shadow: 0 2px 12px rgba(26,58,110,.06); border: 1.5px solid var(--border);
    }
    .table-header {
      display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;
    }
    .table-header h3 { font-family: 'Oswald', sans-serif; font-size: 1rem; color: var(--blue); text-transform: uppercase; }

    /* ── Calendrier ── */
    .calendar-card {
      background: #fff; border-radius: 14px; padding: 22px;
      box-shadow: 0 2px 12px rgba(26,58,110,.06); border: 1.5px solid var(--border);
    }
    .calendar-header {
      display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
    }
    .calendar-title { font-family: 'Oswald', sans-serif; font-size: 1.05rem; color: var(--blue); }
    .cal-nav { background: none; border: 1.5px solid var(--border); border-radius: 8px; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .2s; }
    .cal-nav:hover { background: var(--blue); border-color: var(--blue); color: #fff; }
    .cal-nav .material-icons { font-size: 16px; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
    .cal-day-name { font-size: .68rem; font-weight: 700; color: var(--muted); padding: 4px 0; text-transform: uppercase; }
    .cal-day { font-size: .8rem; padding: 6px 4px; border-radius: 8px; cursor: default; color: var(--text); }
    .cal-day.today { background: var(--blue); color: #fff; font-weight: 700; }
    .cal-day.other-month { color: #d1d5db; }
    .cal-day:hover:not(.today) { background: var(--blue-light); }

    /* ── Actions rapides ── */
    .quick-card {
      background: #fff; border-radius: 14px; padding: 22px;
      box-shadow: 0 2px 12px rgba(26,58,110,.06); border: 1.5px solid var(--border);
    }
    .quick-card h3 { font-family: 'Oswald', sans-serif; font-size: 1rem; color: var(--blue); text-transform: uppercase; margin-bottom: 14px; }
    .quick-actions { display: flex; flex-direction: column; gap: 10px; }
    .quick-action-btn {
      display: flex; align-items: center; gap: 12px; padding: 12px 14px;
      border-radius: 10px; border: 1.5px solid var(--border); background: #fff;
      cursor: pointer; transition: all .22s; text-decoration: none; color: var(--text);
    }
    .quick-action-btn:hover { border-color: var(--blue-bright); background: var(--blue-light); transform: translateX(4px); }
    .qa-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .qa-icon .material-icons { font-size: 18px; color: #fff; }
    .qa-label { font-size: .85rem; font-weight: 600; color: var(--blue); }
    .qa-sub   { font-size: .72rem; color: var(--muted); }

    /* ── Chips rôles ── */
    .role-chip { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:600; }
    .chip-admin      { background:#ede9fe; color:#6d28d9; }
    .chip-medecin    { background:#cffafe; color:#0e7490; }
    .chip-patient    { background:#dbeafe; color:#1d4ed8; }
    .chip-laborantin { background:#d1fae5; color:#065f46; }
    .chip-caissier   { background:#fef3c7; color:#92400e; }
    .status-badge    { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:600; }
    .status-active   { background:#d1fae5; color:#065f46; }
    .status-inactive { background:#fee2e2; color:#991b1b; }

    /* ── Modal ── */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:200; }
    .modal-overlay.open { display:flex; }
    .modal { background:#fff; border-radius:14px; width:100%; max-width:520px; padding:32px; box-shadow:0 20px 60px rgba(0,0,0,.2); animation:fadeUp .3s ease; max-height:90vh; overflow-y:auto; }
    .modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; }
    .modal-header h3 { font-family:'Oswald',sans-serif; font-size:1.15rem; color:var(--blue); text-transform:uppercase; }
    .modal-close { background:none; border:none; cursor:pointer; color:var(--muted); }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

    .alert-msg { padding:10px 14px; border-radius:8px; font-size:.85rem; margin-bottom:16px; display:none; }
    .alert-msg.show { display:block; }
    .alert-success { background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; }
    .alert-error   { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }

    @media (max-width: 1100px) {
      .dash-body { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
      .stats-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-admin">Administrateur</div>
  <div class="sidebar-user">
    <div class="user-name" id="sidebarUserName">—</div>
    <div class="user-email" id="sidebarUserEmail">—</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item active" href="dashboard.php">
      <span class="material-icons">dashboard</span> Tableau de bord
    </a>
    <div class="nav-section-title">Gestion</div>
    <a class="nav-item" href="utilisateurs.php">
      <span class="material-icons">manage_accounts</span> Utilisateurs
    </a>
    <a class="nav-item" href="historique.php">
      <span class="material-icons">calendar_today</span> Historique des Rendez-vous
    </a>
    <a class="nav-item" href="dossiers_medicaux.php">
      <span class="material-icons">folder_shared</span> Dossiers médicaux
    </a>
    <a class="nav-item" href="facturation.php">
      <span class="material-icons">receipt_long</span> Facturation
    </a>
    
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
    <a class="nav-item" href="rapports.php">
      <span class="material-icons">bar_chart</span> Rapports
    </a>
    <a class="nav-item" href="parametres.php">
      <span class="material-icons">settings</span> Paramètres
    </a>
  </nav>
  <div class="sidebar-footer">
    <a class="nav-item" href="../auth/logout.php">
      <span class="material-icons">logout</span> Déconnexion
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="main-wrapper">
  <header class="topbar">

    <!-- Logo -->
    <a class="topbar-logo" href="dashboard.php">
      <img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'">
      <span class="logo-name">KLINIK</span>
    </a>

    <!-- Hamburger -->
    <div class="topbar-left">
      <button class="sidebar-toggle" id="sidebarToggle">
        <span class="material-icons">menu</span>
      </button>
    </div>

    <!-- Barre de recherche centrée -->
    <div class="topbar-search">
      <span class="material-icons">search</span>
      <input type="text" placeholder="Rechercher patient, médecin, rendez-vous...">
    </div>

    <!-- Droite -->
    <div class="topbar-right">
      <!-- Notifications -->
      <div class="topbar-icon-btn" id="notifToggle">
        <span class="material-icons">notifications</span>
        <span class="notif-badge" style="display:none">0</span>
      </div>
      <!-- Messagerie -->
      <div class="topbar-icon-btn">
        <span class="material-icons">mail_outline</span>
      </div>
      <!-- Profil -->
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

    <div class="page-header" style="margin-bottom:24px;">
      <h1>Tableau de bord</h1>
      <p>Vue d'ensemble du système hospitalier KLINIK — <?= date('l d F Y') ?></p>
    </div>

    <div class="dash-body">

      <!-- ── COLONNE GAUCHE ── -->
      <div class="dash-left">

        <!-- Stats -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon" style="background:var(--blue-bright)">
              <span class="material-icons">groups</span>
            </div>
            <div>
              <div class="stat-value"><?= $nbPatients ?></div>
              <div class="stat-label">Patients enregistrés</div>
              <div class="stat-trend"><span class="material-icons" style="font-size:13px">trending_up</span> Actifs</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:#0891b2">
              <span class="material-icons">stethoscope</span>
            </div>
            <div>
              <div class="stat-value"><?= $nbMedecins ?></div>
              <div class="stat-label">Médecins actifs</div>
              <div class="stat-trend"><span class="material-icons" style="font-size:13px">trending_up</span> En service</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:var(--success)">
              <span class="material-icons">event_available</span>
            </div>
            <div>
              <div class="stat-value"><?= $nbConsultAuj ?></div>
              <div class="stat-label">Consultations aujourd'hui</div>
              <div class="stat-trend"><span class="material-icons" style="font-size:13px">schedule</span> Aujourd'hui</div>
            </div>
          </div>
        </div>

        <!-- Graphique activité -->
        <div class="chart-card">
          <div class="chart-header">
            <h3>Activité des consultations</h3>
            <span class="chart-badge">7 derniers jours</span>
          </div>
          <canvas id="chartConsult" height="100"></canvas>
        </div>

        <!-- Tableau utilisateurs -->
        <div class="table-card">
          <div class="table-header">
            <h3>Utilisateurs du système</h3>
            <button class="btn-primary" onclick="openModal('modalUser')">
              <span class="material-icons">add</span> Nouvel utilisateur
            </button>
          </div>
          <div style="overflow-x:auto">
            <table class="klinik-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nom complet</th>
                  <th>Email</th>
                  <th>Rôle</th>
                  <th>Statut</th>
                  <th>Inscription</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($derniers as $i => $u): ?>
                <tr>
                  <td style="color:var(--muted);font-size:.8rem"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></td>
                  <td><strong><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></strong></td>
                  <td style="color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
                  <td>
                    <span class="role-chip chip-<?= $u['role'] ?>">
                      <?= $roleLabels[$u['role']] ?? $u['role'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="status-badge <?= $u['actif'] ? 'status-active' : 'status-inactive' ?>">
                      <?= $u['actif'] ? 'Actif' : 'Inactif' ?>
                    </span>
                  </td>
                  <td style="color:var(--muted);font-size:.8rem"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div><!-- /.dash-left -->

      <!-- ── COLONNE DROITE ── -->
      <div class="dash-right">

        <!-- Calendrier -->
        <div class="calendar-card">
          <div class="calendar-header">
            <span class="calendar-title" id="calTitle"></span>
            <div style="display:flex;gap:6px">
              <button class="cal-nav" id="calPrev"><span class="material-icons">chevron_left</span></button>
              <button class="cal-nav" id="calNext"><span class="material-icons">chevron_right</span></button>
            </div>
          </div>
          <div class="cal-grid" id="calGrid"></div>
        </div>

        <!-- Actions rapides -->
        <div class="quick-card">
          <h3>Actions rapides</h3>
          <div class="quick-actions">
            <a class="quick-action-btn" href="#" onclick="openModal('modalUser');return false;">
              <div class="qa-icon" style="background:#7c3aed"><span class="material-icons">person_add</span></div>
              <div><div class="qa-label">Créer un compte</div><div class="qa-sub">Ajouter un utilisateur</div></div>
            </a>
            <a class="quick-action-btn" href="utilisateurs.php">
              <div class="qa-icon" style="background:var(--blue-bright)"><span class="material-icons">manage_accounts</span></div>
              <div><div class="qa-label">Gérer les utilisateurs</div><div class="qa-sub">Voir tous les comptes</div></div>
            </a>
            <a class="quick-action-btn" href="#">
              <div class="qa-icon" style="background:#0891b2"><span class="material-icons">local_hospital</span></div>
              <div><div class="qa-label">Départements</div><div class="qa-sub">Gérer les départements</div></div>
            </a>
            <a class="quick-action-btn" href="#">
              <div class="qa-icon" style="background:var(--success)"><span class="material-icons">bar_chart</span></div>
              <div><div class="qa-label">Rapports</div><div class="qa-sub">Générer un rapport</div></div>
            </a>
          </div>
        </div>

        <!-- Mini stat utilisateurs -->
        <div class="quick-card">
          <h3>Répartition des rôles</h3>
          <canvas id="chartRoles" height="180"></canvas>
        </div>

      </div><!-- /.dash-right -->
    </div><!-- /.dash-body -->
  </main>
</div>

<!-- Modal créer utilisateur -->
<div class="modal-overlay" id="modalUser">
  <div class="modal">
    <div class="modal-header">
      <h3>Créer un utilisateur</h3>
      <button class="modal-close" onclick="closeModal('modalUser')">
        <span class="material-icons">close</span>
      </button>
    </div>
    <div class="alert-msg" id="modalAlert"></div>
    <div class="form-row">
      <div class="form-group">
        <label>Nom *</label>
        <input type="text" id="mNom" class="form-control" placeholder="NOM">
      </div>
      <div class="form-group">
        <label>Prénom *</label>
        <input type="text" id="mPrenom" class="form-control" placeholder="Prénom">
      </div>
    </div>
    <div class="form-group">
      <label>Email *</label>
      <input type="email" id="mEmail" class="form-control" placeholder="utilisateur@klinik.ci">
    </div>
    <div class="form-group">
      <label>Téléphone</label>
      <input type="tel" id="mTel" class="form-control" placeholder="+225 07 00 00 00">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Rôle *</label>
        <select id="mRole" class="form-control">
          <option value="">Sélectionner...</option>
          <option value="admin">Administrateur</option>
          <option value="medecin">Médecin</option>
          <option value="patient">Patient</option>
          <option value="laborantin">Laborantin</option>
          <option value="caissier">Caissier</option>
        </select>
      </div>
      <div class="form-group">
        <label>Mot de passe *</label>
        <input type="password" id="mPwd" class="form-control" placeholder="Temporaire">
      </div>
    </div>
    <div style="display:flex;gap:12px;margin-top:16px">
      <button class="btn-outline" onclick="closeModal('modalUser')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="createUser()" style="flex:1" id="btnCreate">
        <span class="material-icons">person_add</span> Créer
      </button>
    </div>
  </div>
</div>

<script src="../assets/js/klinik.js"></script>
<script>
const user = {
  nom:    '<?= htmlspecialchars($user["nom"]) ?>',
  prenom: '<?= htmlspecialchars($user["prenom"]) ?>',
  email:  '<?= htmlspecialchars($user["email"]) ?>',
  role:   '<?= $user["role"] ?>'
};
KlinikUI.fillUserInfo(user);
KlinikUI.initSidebar();

// Remplir topbar
const topbarName = document.getElementById('topbarUserName');
const topbarAvatar = document.getElementById('topbarAvatar');
if (topbarName) topbarName.textContent = user.prenom + ' ' + user.nom;
if (topbarAvatar) topbarAvatar.textContent = (user.prenom[0] + user.nom[0]).toUpperCase();

// ── Modal ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Créer utilisateur ──
function createUser() {
  const alertEl = document.getElementById('modalAlert');
  const nom     = document.getElementById('mNom').value.trim();
  const prenom  = document.getElementById('mPrenom').value.trim();
  const email   = document.getElementById('mEmail').value.trim();
  const role    = document.getElementById('mRole').value;
  const pwd     = document.getElementById('mPwd').value;
  const tel     = document.getElementById('mTel').value.trim();

  alertEl.className = 'alert-msg';
  if (!nom || !prenom || !email || !role || !pwd) {
    alertEl.textContent = 'Veuillez remplir tous les champs obligatoires.';
    alertEl.classList.add('show', 'alert-error'); return;
  }

  const btn = document.getElementById('btnCreate');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span> Création...';

  fetch('../api/utilisateurs.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'create', nom, prenom, email, role, password: pwd, telephone: tel })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alertEl.textContent = 'Utilisateur créé avec succès !';
      alertEl.classList.add('show', 'alert-success');
      setTimeout(() => { closeModal('modalUser'); location.reload(); }, 1200);
    } else {
      alertEl.textContent = data.message;
      alertEl.classList.add('show', 'alert-error');
      btn.disabled = false;
      btn.innerHTML = '<span class="material-icons">person_add</span> Créer';
    }
  })
  .catch(() => {
    alertEl.textContent = 'Erreur réseau. Réessayez.';
    alertEl.classList.add('show', 'alert-error');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-icons">person_add</span> Créer';
  });
}

// ── Graphique consultations ──
const ctxLine = document.getElementById('chartConsult').getContext('2d');
new Chart(ctxLine, {
  type: 'bar',
  data: {
    labels: <?= json_encode($jours) ?>,
    datasets: [{
      label: 'Consultations',
      data: <?= json_encode($vals) ?>,
      backgroundColor: 'rgba(37,99,235,.15)',
      borderColor: '#2563eb',
      borderWidth: 2,
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f0f4ff' } },
      x: { grid: { display: false } }
    }
  }
});

// ── Graphique répartition rôles ──
const ctxDoughnut = document.getElementById('chartRoles').getContext('2d');
new Chart(ctxDoughnut, {
  type: 'doughnut',
  data: {
    labels: ['Patients', 'Médecins', 'Laborantins', 'Caissiers', 'Admins'],
    datasets: [{
      data: [
        <?= $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='patient'")->fetchColumn() ?>,
        <?= $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='medecin'")->fetchColumn() ?>,
        <?= $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='laborantin'")->fetchColumn() ?>,
        <?= $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='caissier'")->fetchColumn() ?>,
        <?= $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='admin'")->fetchColumn() ?>
      ],
      backgroundColor: ['#2563eb','#0891b2','#059669','#d97706','#7c3aed'],
      borderWidth: 0,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } }
    },
    cutout: '65%'
  }
});

// ── Calendrier ──
let calYear = new Date().getFullYear();
let calMonth = new Date().getMonth();
const today = new Date();

function renderCalendar() {
  const months = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  document.getElementById('calTitle').textContent = months[calMonth] + ' ' + calYear;
  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';
  ['Lu','Ma','Me','Je','Ve','Sa','Di'].forEach(d => {
    grid.innerHTML += `<div class="cal-day-name">${d}</div>`;
  });
  const first = new Date(calYear, calMonth, 1);
  let startDay = first.getDay() === 0 ? 6 : first.getDay() - 1;
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
  const prevDays = new Date(calYear, calMonth, 0).getDate();
  for (let i = startDay - 1; i >= 0; i--) {
    grid.innerHTML += `<div class="cal-day other-month">${prevDays - i}</div>`;
  }
  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = d === today.getDate() && calMonth === today.getMonth() && calYear === today.getFullYear();
    grid.innerHTML += `<div class="cal-day ${isToday ? 'today' : ''}">${d}</div>`;
  }
}

document.getElementById('calPrev').addEventListener('click', () => {
  calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; } renderCalendar();
});
document.getElementById('calNext').addEventListener('click', () => {
  calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; } renderCalendar();
});
renderCalendar();
</script>
</body>
</html>
