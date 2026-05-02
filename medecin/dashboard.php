<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['medecin']);
$user = getUser();
$pdo  = getDB();
$medecinId = $user['medecin_id'] ?? null;

// ── Stats ──
function dbStat($pdo, $sql, $params=[]) {
    $s = $pdo->prepare($sql); $s->execute($params); return (int)$s->fetchColumn();
}
$nbRdvAuj    = $medecinId ? dbStat($pdo,"SELECT COUNT(*) FROM rendez_vous WHERE medecin_id=? AND DATE(date_rdv)=CURDATE()",[$medecinId]) : 0;
$nbConsultAuj= $medecinId ? dbStat($pdo,"SELECT COUNT(*) FROM consultations WHERE medecin_id=? AND DATE(date_consult)=CURDATE()",[$medecinId]) : 0;
$nbPatients  = $medecinId ? dbStat($pdo,"SELECT COUNT(DISTINCT patient_id) FROM consultations WHERE medecin_id=?",[$medecinId]) : 0;
$nbPatientsMois = $medecinId ? dbStat($pdo,"SELECT COUNT(DISTINCT patient_id) FROM consultations WHERE medecin_id=? AND MONTH(date_consult)=MONTH(NOW())",[$medecinId]) : 0;
$nbExamens   = $medecinId ? dbStat($pdo,"SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.medecin_id=? AND e.statut='transmis'",[$medecinId]) : 0;

// ── RDV du jour ──
$rdvAuj = [];
if ($medecinId) {
    $s = $pdo->prepare("
        SELECT r.*, u.nom, u.prenom, p.groupe_sanguin, p.date_naissance
        FROM rendez_vous r
        JOIN patients pt ON r.patient_id = pt.id
        JOIN utilisateurs u ON pt.utilisateur_id = u.id
        LEFT JOIN patients p ON pt.id = p.id
        WHERE r.medecin_id=? AND DATE(r.date_rdv)=CURDATE()
        ORDER BY r.date_rdv ASC LIMIT 10
    ");
    $s->execute([$medecinId]); $rdvAuj = $s->fetchAll();
}

// ── Examens transmis ──
$examens = [];
if ($medecinId) {
    $s = $pdo->prepare("
        SELECT e.*, u.nom, u.prenom
        FROM examens e
        JOIN consultations c ON e.consultation_id=c.id
        JOIN patients pt ON c.patient_id=pt.id
        JOIN utilisateurs u ON pt.utilisateur_id=u.id
        WHERE c.medecin_id=? AND e.statut='transmis'
        ORDER BY e.date_resultat DESC LIMIT 5
    ");
    $s->execute([$medecinId]); $examens = $s->fetchAll();
}

// ── Patients de la semaine ──
$patientsWeek = $medecinId ? dbStat($pdo,"SELECT COUNT(DISTINCT patient_id) FROM consultations WHERE medecin_id=? AND date_consult >= DATE_SUB(NOW(),INTERVAL 7 DAY)",[$medecinId]) : 0;

// ── RDV sélectionné (premier du jour par défaut) ──
$selectedRdv = !empty($rdvAuj) ? $rdvAuj[0] : null;

$statutLabels = ['en_attente'=>'En attente','confirme'=>'Confirmé','termine'=>'Terminé','annule'=>'Annulé'];

// ── Patients pour select ──
$pts = $pdo->query("SELECT p.id, u.nom, u.prenom FROM patients p JOIN utilisateurs u ON p.utilisateur_id=u.id ORDER BY u.nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KLINIK — Espace Médecin</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    /* ── Layout 3 colonnes ── */
    .dash-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 300px;
      gap: 20px;
      align-items: start;
    }
    .col-mid { display:flex; flex-direction:column; gap:20px; }
    .col-right { display:flex; flex-direction:column; gap:20px; }

    /* ── Stats ── */
    .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
    .stat-card { background:#fff; border-radius:14px; padding:18px; display:flex; align-items:center; gap:12px; border:1.5px solid #eef0f6; box-shadow:0 2px 10px rgba(26,58,110,.05); transition:transform .25s,box-shadow .25s; animation:fadeUp .5s ease both; opacity:0; }
    .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(26,58,110,.1); }
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}.stat-card:nth-child(4){animation-delay:.24s}
    .stat-icon { width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .stat-icon .material-icons { font-size:22px;color:#fff; }
    .stat-value { font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1; }
    .stat-label { font-size:.72rem;color:var(--muted);margin-top:3px; }

    /* ── Card ── */
    .card { background:#fff;border-radius:14px;padding:20px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05); }
    .card-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:16px; }
    .card-header h3 { font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase;letter-spacing:.5px; }

    /* ── Calendrier ── */
    .cal-nav-row { display:flex;align-items:center;justify-content:space-between;margin-bottom:12px; }
    .cal-month { font-family:'Oswald',sans-serif;font-size:1.1rem;font-weight:700;color:var(--blue); }
    .cal-btn { background:none;border:1.5px solid #eef0f6;border-radius:8px;width:26px;height:26px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s; }
    .cal-btn:hover { background:var(--blue);border-color:var(--blue); }
    .cal-btn:hover .material-icons { color:#fff; }
    .cal-btn .material-icons { font-size:15px;color:var(--muted); }
    .cal-grid { display:grid;grid-template-columns:repeat(7,1fr);gap:3px;text-align:center; }
    .cal-day-name { font-size:.62rem;font-weight:700;color:var(--muted);padding:3px 0;text-transform:uppercase; }
    .cal-day { font-size:.78rem;padding:5px 3px;border-radius:7px;cursor:pointer;color:var(--text);position:relative;transition:background .15s; }
    .cal-day:hover:not(.today):not(.empty) { background:var(--blue-light); }
    .cal-day.today { background:var(--blue-bright);color:#fff;font-weight:700; }
    .cal-day.has-rdv::after { content:'';position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:var(--blue-bright); }
    .cal-day.today.has-rdv::after { background:#fff; }
    .cal-day.empty { color:#d1d5db;cursor:default; }
    .cal-legend { display:flex;gap:12px;margin-top:10px;font-size:.68rem;color:var(--muted); }
    .cal-legend span { display:flex;align-items:center;gap:4px; }
    .cal-dot { width:7px;height:7px;border-radius:50%;display:inline-block; }

    /* ── Timeline RDV ── */
    .timeline-item {
      display:flex;align-items:flex-start;gap:12px;padding:10px;
      border-radius:10px;cursor:pointer;transition:background .2s;margin-bottom:6px;
      border:1.5px solid transparent;
    }
    .timeline-item:hover { background:#f8faff; border-color:#eef0f6; }
    .timeline-item.active { background:var(--blue-light);border-color:var(--blue-bright); }
    .tl-time { font-family:'Oswald',sans-serif;font-size:.85rem;font-weight:700;color:var(--blue);min-width:44px;padding-top:2px; }
    .tl-bar { width:3px;border-radius:2px;flex-shrink:0;align-self:stretch;min-height:36px; }
    .tl-content { flex:1;min-width:0; }
    .tl-name { font-weight:600;font-size:.88rem;color:var(--text); }
    .tl-detail { font-size:.74rem;color:var(--muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }

    /* ── Détail RDV ── */
    .rdv-detail-card { background:var(--blue);border-radius:14px;padding:20px;color:#fff; }
    .rdv-detail-name { font-family:'Oswald',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:4px; }
    .rdv-detail-id   { font-size:.72rem;opacity:.6;margin-bottom:16px; }
    .rdv-detail-row  { display:flex;justify-content:space-between;font-size:.82rem;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.12); }
    .rdv-detail-row:last-child { border:none; }
    .rdv-detail-row .label { opacity:.65; }
    .rdv-detail-row .val   { font-weight:600; }
    .btn-card { display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:#fff;color:var(--blue);border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer;margin-top:14px;width:100%;justify-content:center;transition:opacity .2s; }
    .btn-card:hover { opacity:.9; }

    /* ── Charge de travail ── */
    .workload-ring { position:relative;display:flex;align-items:center;justify-content:center;margin:0 auto 14px; }
    .workload-pct  { position:absolute;font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--blue); }
    .workload-row  { display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:6px; }
    .workload-label { color:var(--muted); }
    .workload-val   { font-weight:600;color:var(--text); }

    /* ── Patients par état ── */
    .condition-row { display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f0f4fa;font-size:.82rem; }
    .condition-row:last-child { border:none; }
    .cond-dot  { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
    .cond-name { flex:1;color:var(--text); }
    .cond-nb   { font-weight:700;color:var(--blue); }

    /* ── Actions rapides ── */
    .qa-btn { display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;border:1.5px solid #eef0f6;background:#fff;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text);margin-bottom:8px; }
    .qa-btn:hover { border-color:var(--blue-bright);background:var(--blue-light);transform:translateX(3px); }
    .qa-icon { width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .qa-icon .material-icons { font-size:16px;color:#fff; }
    .qa-label { font-size:.83rem;font-weight:600;color:var(--blue); }
    .qa-sub   { font-size:.7rem;color:var(--muted); }

    /* ── Modals ── */
    .modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200; }
    .modal-overlay.open { display:flex; }
    .modal { background:#fff;border-radius:14px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease; }
    .modal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:22px; }
    .modal-header h3 { font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase; }
    .modal-close { background:none;border:none;cursor:pointer;color:var(--muted); }
    .form-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
    .alert-msg { padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;display:none; }
    .alert-msg.show { display:block; }
    .alert-success { background:#d1fae5;border:1px solid #6ee7b7;color:#065f46; }
    .alert-error   { background:#fee2e2;border:1px solid #fca5a5;color:#991b1b; }

    @media(max-width:1200px) { .dash-grid{grid-template-columns:1fr 300px} .col-mid{display:none} }
    @media(max-width:900px)  { .dash-grid{grid-template-columns:1fr} .stats-row{grid-template-columns:repeat(2,1fr)} }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-medecin">Médecin</div>
  <div class="sidebar-user">
    <div class="user-name" id="sidebarUserName">—</div>
    <div class="user-email" id="sidebarUserEmail">—</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>
    <a class="nav-item active" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <div class="nav-section-title">Médical</div>
    <a class="nav-item" href="#" onclick="openModal('modalConsult');return false;"><span class="material-icons">add_circle</span> Nouvelle consultation</a>
    <a class="nav-item" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span>Mes rendez-vous</a>
    <a class="nav-item" href="dossiers.php"><span class="material-icons">folder_shared</span> Dossiers patients</a>
    <a class="nav-item" href="examens.php"><span class="material-icons">science</span> Examens</a>
    <a class="nav-item" href="ordonnances.php"><span class="material-icons">description</span> Ordonnances</a>
  
    <div class="nav-section-title">Communication</div>
    <a class="nav-item" href="../notifications/index.php">
      <span class="material-icons">notifications</span> Notifications
    </a>
    <a class="nav-item" href="../modules/messages/index.php">
      <span class="material-icons">chat</span> Messagerie
    </a>
  </nav>
  <div class="sidebar-footer">
    <a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a>
  </div>
</aside>

<!-- MAIN -->
<div class="main-wrapper">
  <header class="topbar">
    <a class="topbar-logo" href="dashboard.php">
      <img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'">
      <span class="logo-name">KLINIK</span>
    </a>
    <div class="topbar-left">
      <button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button>
    </div>
    <div class="topbar-search">
      <span class="material-icons">search</span>
      <input type="text" placeholder="Rechercher patient, diagnostic...">
    </div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><span class="notif-badge"><?= $nbExamens ?></span></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user">
        <div class="topbar-avatar" id="topbarAvatar">MD</div>
        <div class="topbar-user-info">
          <div class="topbar-user-name" id="topbarUserName">—</div>
          <div class="topbar-user-role">Médecin</div>
        </div>
      </div>
    </div>
  </header>

  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px;">
      <h1>Bonjour, <span style="color:var(--blue-bright)">Dr. <?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></span></h1>
      <p><?= date('l d F Y') ?></p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon" style="background:#0891b2"><span class="material-icons">calendar_today</span></div>
        <div><div class="stat-value"><?= $nbRdvAuj ?></div><div class="stat-label">RDV aujourd'hui</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">stethoscope</span></div>
        <div><div class="stat-value"><?= $nbConsultAuj ?></div><div class="stat-label">Consultations</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--success)"><span class="material-icons">groups</span></div>
        <div><div class="stat-value"><?= $nbPatients ?></div><div class="stat-label">Patients total</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#7c3aed"><span class="material-icons">science</span></div>
        <div><div class="stat-value"><?= $nbExamens ?></div><div class="stat-label">Résultats reçus</div></div>
      </div>
    </div>

    <!-- Corps 3 colonnes -->
    <div class="dash-grid">

      <!-- ── COL GAUCHE : Calendrier + Timeline ── -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Calendrier -->
        <div class="card">
          <div class="cal-nav-row">
            <span class="cal-month" id="calTitle"></span>
            <div style="display:flex;gap:6px">
              <button class="cal-btn" id="calPrev"><span class="material-icons">chevron_left</span></button>
              <button class="cal-btn" id="calNext"><span class="material-icons">chevron_right</span></button>
            </div>
          </div>
          <div class="cal-grid" id="calGrid"></div>
          <div class="cal-legend">
            <span><span class="cal-dot" style="background:var(--blue-bright)"></span> Aujourd'hui</span>
            <span><span class="cal-dot" style="background:#0891b2"></span> RDV prévu</span>
          </div>
        </div>

        <!-- Timeline du jour -->
        <div class="card">
          <div class="card-header">
            <h3>Aujourd'hui</h3>
            <span style="font-size:.75rem;color:var(--muted)"><?= count($rdvAuj) ?> rendez-vous</span>
          </div>
          <?php if (empty($rdvAuj)): ?>
            <p style="color:var(--muted);text-align:center;padding:20px 0;font-size:.85rem;">Aucun rendez-vous aujourd'hui</p>
          <?php else: ?>
            <div id="timeline">
              <?php foreach ($rdvAuj as $i => $rdv):
                $colors = ['#2563eb','#0891b2','#7c3aed','#059669','#d97706'];
                $c = $colors[$i % count($colors)];
              ?>
              <div class="timeline-item <?= $i===0?'active':'' ?>"
                   onclick="selectRdv(<?= $i ?>)"
                   data-idx="<?= $i ?>">
                <span class="tl-time"><?= date('H:i',strtotime($rdv['date_rdv'])) ?></span>
                <div class="tl-bar" style="background:<?= $c ?>"></div>
                <div class="tl-content">
                  <div class="tl-name"><?= htmlspecialchars($rdv['prenom'].' '.$rdv['nom']) ?></div>
                  <div class="tl-detail"><?= htmlspecialchars($rdv['motif'] ?? 'Consultation') ?></div>
                </div>
                <span class="status-badge <?= $rdv['statut']==='confirme'?'status-active':($rdv['statut']==='termine'?'status-done':'status-pending') ?>" style="font-size:.68rem">
                  <?= $statutLabels[$rdv['statut']] ?? $rdv['statut'] ?>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── COL MILIEU : Détail RDV + Examens ── -->
      <div class="col-mid">

        <!-- Détail patient sélectionné -->
        <?php if ($selectedRdv): ?>
        <div class="rdv-detail-card" id="rdvDetailCard">
          <div style="font-size:.72rem;opacity:.6;margin-bottom:4px"><?= date('H:i',strtotime($selectedRdv['date_rdv'])).' — '.date('H:i',strtotime($selectedRdv['date_rdv'].'+30 minutes')) ?></div>
          <div class="rdv-detail-name" id="detailNom"><?= htmlspecialchars($selectedRdv['prenom'].' '.$selectedRdv['nom']) ?></div>
          <div class="rdv-detail-id" id="detailId">#PT<?= str_pad($selectedRdv['patient_id'],8,'0',STR_PAD_LEFT) ?></div>
          <div class="rdv-detail-row"><span class="label">Type</span><span class="val" id="detailMotif"><?= htmlspecialchars($selectedRdv['motif'] ?? 'Consultation') ?></span></div>
          <div class="rdv-detail-row"><span class="label">Groupe sanguin</span><span class="val" id="detailSang"><?= htmlspecialchars($selectedRdv['groupe_sanguin'] ?? '—') ?></span></div>
          <div class="rdv-detail-row"><span class="label">Date naissance</span><span class="val" id="detailNaiss"><?= $selectedRdv['date_naissance'] ? date('d/m/Y',strtotime($selectedRdv['date_naissance'])) : '—' ?></span></div>
          <div class="rdv-detail-row"><span class="label">Statut</span><span class="val" id="detailStatut"><?= $statutLabels[$selectedRdv['statut']] ?></span></div>
          <button class="btn-card" onclick="openModal('modalConsult')">
            <span class="material-icons" style="font-size:18px">assignment</span> Ouvrir le dossier
          </button>
        </div>
        <?php else: ?>
        <div class="card" style="text-align:center;padding:32px 20px;">
          <span class="material-icons" style="font-size:36px;color:var(--border)">event_busy</span>
          <p style="color:var(--muted);margin-top:8px;font-size:.85rem">Aucun RDV aujourd'hui</p>
        </div>
        <?php endif; ?>

        <!-- Résultats examens -->
        <div class="card">
          <div class="card-header"><h3>Résultats d'examens</h3></div>
          <?php if (empty($examens)): ?>
            <p style="color:var(--muted);text-align:center;padding:16px 0;font-size:.83rem">Aucun résultat</p>
          <?php else: ?>
            <?php foreach ($examens as $ex): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f0f4fa;">
              <div style="width:34px;height:34px;border-radius:8px;background:var(--blue-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span class="material-icons" style="font-size:17px;color:var(--blue-bright)">biotech</span>
              </div>
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($ex['type_examen']) ?></div>
                <div style="font-size:.74rem;color:var(--muted)"><?= htmlspecialchars($ex['prenom'].' '.$ex['nom']) ?></div>
              </div>
              <span class="status-badge status-done" style="font-size:.68rem">Transmis</span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── COL DROITE : Charge + Patients + Actions ── -->
      <div class="col-right">

        <!-- Charge de travail -->
        <div class="card">
          <div class="card-header"><h3>Charge de travail</h3></div>
          <div class="workload-ring">
            <canvas id="chartWorkload" width="100" height="100"></canvas>
            <div class="workload-pct" id="workloadPct">—%</div>
          </div>
          <div class="workload-row"><span class="workload-label">Patients total</span><span class="workload-val"><?= $nbPatients ?></span></div>
          <div class="workload-row"><span class="workload-label">Patients ce mois</span><span class="workload-val"><?= $nbPatientsMois ?></span></div>
          <div class="workload-row"><span class="workload-label">Cette semaine</span><span class="workload-val"><?= $patientsWeek ?></span></div>
        </div>

        <!-- Actions rapides -->
        <div class="card">
          <div class="card-header"><h3>Actions rapides</h3></div>
          <a class="qa-btn" href="#" onclick="openModal('modalConsult');return false;">
            <div class="qa-icon" style="background:var(--blue-bright)"><span class="material-icons">add_circle</span></div>
            <div><div class="qa-label">Nouvelle consultation</div><div class="qa-sub">Enregistrer</div></div>
          </a>
          <a class="qa-btn" href="#">
            <div class="qa-icon" style="background:#0891b2"><span class="material-icons">folder_shared</span></div>
            <div><div class="qa-label">Dossiers patients</div><div class="qa-sub">Voir tous</div></div>
          </a>
          <a class="qa-btn" href="#">
            <div class="qa-icon" style="background:#7c3aed"><span class="material-icons">science</span></div>
            <div><div class="qa-label">Demander examen</div><div class="qa-sub">Prescrire</div></div>
          </a>
          <a class="qa-btn" href="#">
            <div class="qa-icon" style="background:var(--success)"><span class="material-icons">description</span></div>
            <div><div class="qa-label">Ordonnance</div><div class="qa-sub">Rédiger</div></div>
          </a>
        </div>

      </div>
    </div>
  </main>
</div>

<!-- Modal consultation -->
<div class="modal-overlay" id="modalConsult">
  <div class="modal">
    <div class="modal-header">
      <h3>Nouvelle consultation</h3>
      <button class="modal-close" onclick="closeModal('modalConsult')"><span class="material-icons">close</span></button>
    </div>
    <div class="alert-msg" id="consultAlert"></div>
    <div class="form-group">
      <label>Patient *</label>
      <select id="cPatient" class="form-control">
        <option value="">Sélectionner un patient...</option>
        <?php foreach ($pts as $pt) echo "<option value='{$pt['id']}'>{$pt['prenom']} {$pt['nom']}</option>"; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Diagnostic</label>
      <textarea id="cDiag" class="form-control" rows="3" placeholder="Diagnostic..."></textarea>
    </div>
    <div class="form-group">
      <label>Prescription</label>
      <textarea id="cPrescription" class="form-control" rows="3" placeholder="Médicaments, posologie..."></textarea>
    </div>
    <div class="form-group">
      <label>Observations</label>
      <textarea id="cObs" class="form-control" rows="2" placeholder="Observations..."></textarea>
    </div>
    <div style="display:flex;gap:12px;margin-top:16px">
      <button class="btn-outline" onclick="closeModal('modalConsult')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveConsultation()" style="flex:1" id="btnConsult">
        <span class="material-icons">save</span> Enregistrer
      </button>
    </div>
  </div>
</div>

<script src="../assets/js/klinik.js"></script>
<script>
// ── Init user ──
const user = {
  nom:'<?= htmlspecialchars($user["nom"]) ?>',
  prenom:'<?= htmlspecialchars($user["prenom"]) ?>',
  email:'<?= htmlspecialchars($user["email"]) ?>',
  role:'<?= $user["role"] ?>'
};
KlinikUI.fillUserInfo(user); KlinikUI.initSidebar();
document.getElementById('topbarUserName').textContent = user.prenom+' '+user.nom;
document.getElementById('topbarAvatar').textContent = (user.prenom[0]+user.nom[0]).toUpperCase();

// ── Modals ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); });
});

// ── Données RDV ──
const rdvData = <?= json_encode(array_values($rdvAuj)) ?>;
const statutLabels = { en_attente:'En attente', confirme:'Confirmé', termine:'Terminé', annule:'Annulé' };

function selectRdv(idx) {
  document.querySelectorAll('.timeline-item').forEach((el,i) => el.classList.toggle('active', i===idx));
  if (!rdvData[idx]) return;
  const r = rdvData[idx];
  const t = new Date(r.date_rdv);
  const h = t.getHours().toString().padStart(2,'0')+':'+t.getMinutes().toString().padStart(2,'0');
  const t2 = new Date(t.getTime()+30*60000);
  const h2 = t2.getHours().toString().padStart(2,'0')+':'+t2.getMinutes().toString().padStart(2,'0');
  const card = document.getElementById('rdvDetailCard');
  if (!card) return;
  card.querySelector('div').textContent = h+' — '+h2;
  document.getElementById('detailNom').textContent  = r.prenom+' '+r.nom;
  document.getElementById('detailId').textContent   = '#PT'+String(r.patient_id).padStart(8,'0');
  document.getElementById('detailMotif').textContent= r.motif || 'Consultation';
  document.getElementById('detailSang').textContent = r.groupe_sanguin || '—';
  document.getElementById('detailNaiss').textContent= r.date_naissance ? new Date(r.date_naissance).toLocaleDateString('fr-FR') : '—';
  document.getElementById('detailStatut').textContent = statutLabels[r.statut] || r.statut;
}

// ── Calendrier ──
let calYear = new Date().getFullYear(), calMonth = new Date().getMonth();
const today = new Date();
const rdvDates = rdvData.map(r => new Date(r.date_rdv).toDateString());

function renderCal() {
  const months = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  document.getElementById('calTitle').textContent = months[calMonth]+' '+calYear;
  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';
  ['Lu','Ma','Me','Je','Ve','Sa','Di'].forEach(d => grid.innerHTML += `<div class="cal-day-name">${d}</div>`);
  const first = new Date(calYear, calMonth, 1);
  const startDay = first.getDay()===0 ? 6 : first.getDay()-1;
  const daysInMonth = new Date(calYear, calMonth+1, 0).getDate();
  const prevDays = new Date(calYear, calMonth, 0).getDate();
  for (let i=startDay-1; i>=0; i--) grid.innerHTML += `<div class="cal-day empty">${prevDays-i}</div>`;
  for (let d=1; d<=daysInMonth; d++) {
    const date = new Date(calYear, calMonth, d);
    const isToday = d===today.getDate() && calMonth===today.getMonth() && calYear===today.getFullYear();
    const hasRdv  = rdvDates.includes(date.toDateString());
    grid.innerHTML += `<div class="cal-day${isToday?' today':''}${hasRdv?' has-rdv':''}">${d}</div>`;
  }
}
document.getElementById('calPrev').addEventListener('click', () => { calMonth--; if(calMonth<0){calMonth=11;calYear--;} renderCal(); });
document.getElementById('calNext').addEventListener('click', () => { calMonth++; if(calMonth>11){calMonth=0;calYear++;} renderCal(); });
renderCal();

// ── Graphique charge de travail ──
const maxPatients = 50;
const pct = Math.min(Math.round(<?= $nbPatients ?> / maxPatients * 100), 100);
document.getElementById('workloadPct').textContent = pct+'%';
new Chart(document.getElementById('chartWorkload').getContext('2d'), {
  type: 'doughnut',
  data: {
    datasets: [{
      data: [pct, 100-pct],
      backgroundColor: [pct>75?'#ef4444':pct>50?'#f59e0b':'#2563eb', '#eef0f6'],
      borderWidth: 0
    }]
  },
  options: { cutout:'75%', plugins:{legend:{display:false}}, animation:{animateRotate:true} }
});

// ── Consultation ──
function saveConsultation() {
  const alertEl = document.getElementById('consultAlert');
  const patient = document.getElementById('cPatient').value;
  alertEl.className = 'alert-msg';
  if (!patient) { alertEl.textContent='Veuillez sélectionner un patient.'; alertEl.classList.add('show','alert-error'); return; }
  const btn = document.getElementById('btnConsult');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span> Enregistrement...';
  fetch('../api/consultations.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      action:'create', patient_id:patient,
      diagnostic:document.getElementById('cDiag').value,
      prescription:document.getElementById('cPrescription').value,
      observations:document.getElementById('cObs').value
    })
  })
  .then(r=>r.json())
  .then(data => {
    if (data.success) {
      alertEl.textContent='Consultation enregistrée !'; alertEl.classList.add('show','alert-success');
      setTimeout(()=>{ closeModal('modalConsult'); location.reload(); }, 1200);
    } else {
      alertEl.textContent=data.message; alertEl.classList.add('show','alert-error');
      btn.disabled=false; btn.innerHTML='<span class="material-icons">save</span> Enregistrer';
    }
  });
}
</script>
</body>
</html>