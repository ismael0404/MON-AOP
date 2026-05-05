<?php
require_once '../../includes/check_auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkAuth(['admin', 'caissier', 'patient']);
$user = getUser();
$pdo = getDB();
$uid = (int)$user['id'];
$isAdminOrCaissier = in_array($user['role'], ['admin', 'caissier']);

$search = sanitize($_GET['search'] ?? '');
$opFilter = sanitize($_GET['operateur'] ?? '');
$statFilter = sanitize($_GET['statut'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];

$patientId = null;
if (!$isAdminOrCaissier) {
    $stmtP = $pdo->prepare("SELECT id FROM patients WHERE utilisateur_id = ?");
    $stmtP->execute([$uid]);
    $patientId = $stmtP->fetchColumn();
    $where .= " AND p.facture_id IN (SELECT id FROM factures WHERE patient_id = ?)";
    $params[] = $patientId;
}

if ($search) {
    $where .= " AND (p.transaction_id LIKE ? OR p.telephone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($opFilter) {
    $where .= " AND p.provider = ?";
    $params[] = $opFilter;
}
if ($statFilter) {
    $where .= " AND p.statut = ?";
    $params[] = $statFilter;
}

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM paiements_mobile p $where");
$stmtC->execute($params);
$total = (int)$stmtC->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupérer les paiements
$stmt = $pdo->prepare("
  SELECT p.*, f.montant_total as montant_facture, 
         up.nom as patient_nom, up.prenom as patient_prenom
  FROM paiements_mobile p
  JOIN factures f ON p.facture_id = f.id
  JOIN patients pt ON f.patient_id = pt.id
  JOIN utilisateurs up ON pt.utilisateur_id = up.id
  $where
  ORDER BY p.created_at DESC
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$paiements = $stmt->fetchAll();

// Factures impayées (pour le patient)
$facturesImpayees = [];
if (!$isAdminOrCaissier && $patientId) {
    $stmtF = $pdo->prepare("SELECT f.id, f.montant_total, f.date_facture FROM factures f WHERE f.patient_id = ? AND f.statut != 'payee'");
    $stmtF->execute([$patientId]);
    $facturesImpayees = $stmtF->fetchAll();
}

// Stats globales
$statW = "1=1";
$statP = [];
if (!$isAdminOrCaissier) {
    $statW = "p.facture_id IN (SELECT id FROM factures WHERE patient_id = ?)";
    $statP[] = $patientId;
}
$revenusTotal = $pdo->prepare("SELECT SUM(montant) FROM paiements_mobile p WHERE $statW AND p.statut = 'succes'");
$revenusTotal->execute($statP);
$totalRev = (float)$revenusTotal->fetchColumn();

$paiementsJour = $pdo->prepare("SELECT SUM(montant) FROM paiements_mobile p WHERE $statW AND p.statut = 'succes' AND DATE(p.created_at) = CURDATE()");
$paiementsJour->execute($statP);
$jourRev = (float)$paiementsJour->fetchColumn();

$nbAttente = $pdo->prepare("SELECT COUNT(*) FROM paiements_mobile p WHERE $statW AND p.statut IN ('initie', 'en_cours')");
$nbAttente->execute($statP);
$attente = (int)$nbAttente->fetchColumn();

// Données graphe
$graph = $pdo->prepare("SELECT provider, COUNT(*) as c FROM paiements_mobile p WHERE $statW GROUP BY provider");
$graph->execute($statP);
$opData = $graph->fetchAll();
$labels = []; $dataArr = []; $colors = [];
$opColors = ['wave'=>'#00a8ff','orange_money'=>'#ff7900','mtn_momo'=>'#ffcc00','moov_money'=>'#005b9f','cash'=>'#059669'];
foreach($opData as $row) {
    $labels[] = ucfirst(str_replace('_', ' ', $row['provider']));
    $dataArr[] = $row['c'];
    $colors[] = $opColors[strtolower($row['provider'])] ?? '#64748b';
}

$statutLabels = ['initie'=>'Initié', 'en_cours'=>'En cours', 'succes'=>'Validé', 'echec'=>'Échoué', 'expire'=>'Expiré'];
$statutColors = ['initie'=>'status-pending', 'en_cours'=>'status-pending', 'succes'=>'status-active', 'echec'=>'status-inactive', 'expire'=>'status-inactive'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KLINIK — Plateforme de Paiements</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .card { background:#fff;border-radius:14px;padding:22px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:20px; }
    .stats-row { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:22px; }
    .stat-card { background:#fff;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);transition:transform .2s; }
    .stat-card:hover { transform:translateY(-2px); }
    .stat-icon { width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .stat-icon .material-icons { font-size:21px;color:#fff; }
    .stat-value { font-family:"Oswald",sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1; }
    .stat-label { font-size:.7rem;color:var(--muted);margin-top:3px; }
    
    .table-card { background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05);margin-bottom:20px; }
    .table-header { padding:16px 20px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between; }
    .table-header h3 { font-family:"Oswald",sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase; }
    
    .filter-bar { display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:12px 16px;align-items:center; }
    
    .op-chip { display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase; }
    .op-wave { background:#e0f2fe;color:#0369a1; }
    .op-orange { background:#ffedd5;color:#c2410c; }
    .op-mtn { background:#fef9c3;color:#a16207; }
    .op-moov { background:#dbeafe;color:#1e3a8a; }
    .op-cash { background:#d1fae5;color:#065f46; }

    .action-btn{display:inline-flex;align-items:center;justify-content:center;padding:4px 8px;border-radius:6px;border:none;cursor:pointer;transition:all .18s;text-decoration:none;font-size:.75rem;font-weight:600;}
    .btn-receipt { background:var(--blue-light); color:var(--blue-bright); }
    .btn-receipt:hover { background:var(--blue-bright); color:#fff; }
    .btn-valid { background:#d1fae5; color:#065f46; }
    .btn-valid:hover { background:#059669; color:#fff; }
    .btn-cancel { background:#fee2e2; color:#b91c1c; }
    .btn-cancel:hover { background:#dc2626; color:#fff; }
    
    /* Layout */
    .top-layout { display:grid;grid-template-columns:1fr 300px;gap:20px;margin-bottom:20px; }
    @media(max-width:900px) { .top-layout { grid-template-columns:1fr; } }

    /* Modal Simulation */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200;}
    .modal-overlay.open{display:flex;}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:450px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease;position:relative;}
    .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .modal-header h3{font-family:"Oswald",sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase;}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted);}
    
    /* Loading state */
    .spinner-overlay { position:absolute;inset:0;background:rgba(255,255,255,0.9);border-radius:14px;display:none;flex-direction:column;align-items:center;justify-content:center;z-index:10; }
    .spinner { width:40px;height:40px;border:4px solid #eef0f6;border-top-color:var(--blue-bright);border-radius:50%;animation:spin 1s linear infinite;margin-bottom:16px; }
    @keyframes spin { to { transform:rotate(360deg); } }
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  </style>
</head>
<body>

<div class="main-wrapper" style="margin-left: 0; padding: 0;">
  <header class="topbar">
    <a class="topbar-logo" href="../../<?= $user['role'] ?>/dashboard.php">
      <img src="../../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'">
      <span class="logo-name">KLINIK</span>
    </a>
    <div class="topbar-left">
      <a href="../../<?= $user['role'] ?>/dashboard.php" class="btn-outline" style="padding:6px 12px;display:flex;align-items:center;gap:6px">
        <span class="material-icons" style="font-size:16px">arrow_back</span> Retour
      </a>
    </div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span></div>
      <div class="topbar-user">
        <div class="topbar-avatar" id="topbarAvatar"><?= strtoupper($user['prenom'][0].$user['nom'][0]) ?></div>
        <div class="topbar-user-info">
          <div class="topbar-user-name" id="topbarUserName"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></div>
          <div class="topbar-user-role"><?= ucfirst($user['role']) ?></div>
        </div>
      </div>
    </div>
  </header>

  <main class="page-content" style="max-width: 1200px; margin: 0 auto; padding-top: 100px;">
    
    <div class="page-header" style="margin-bottom:24px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <h1>Paiements</h1>
        <p>Simulation et gestion des paiements sécurisés</p>
      </div>
      <?php if(!$isAdminOrCaissier): ?>
        <button class="btn-primary" onclick="openSimulationModal()" style="display:flex;align-items:center;gap:8px">
          <span class="material-icons">payment</span> Simuler un paiement
        </button>
      <?php endif; ?>
    </div>

    <div class="top-layout">
      <div>
        <div class="stats-row">
          <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">account_balance_wallet</span></div><div><div class="stat-value"><?= number_format($totalRev,0,',',' ') ?></div><div class="stat-label">Total Validé (FCFA)</div></div></div>
          <?php if($isAdminOrCaissier): ?>
            <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">today</span></div><div><div class="stat-value"><?= number_format($jourRev,0,',',' ') ?></div><div class="stat-label">Revenus du jour (FCFA)</div></div></div>
          <?php endif; ?>
          <div class="stat-card"><div class="stat-icon" style="background:var(--warning)"><span class="material-icons">pending_actions</span></div><div><div class="stat-value"><?= $attente ?></div><div class="stat-label">Transactions en attente</div></div></div>
        </div>

        <div class="filter-bar">
          <div style="position:relative;flex:1;min-width:180px">
            <span class="material-icons" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:17px;color:#9ca3af">search</span>
            <input type="text" id="searchInput" class="form-control" style="padding-left:32px" placeholder="Rechercher réf, téléphone..." value="<?= htmlspecialchars($search) ?>" onkeyup="debounceSearch()">
          </div>
          <select id="opSelect" class="form-control" style="width:160px" onchange="applyFilters()">
            <option value="">Tous les opérateurs</option>
            <option value="wave" <?= strtolower($opFilter)==='wave'?'selected':'' ?>>Wave</option>
            <option value="orange_money" <?= strtolower($opFilter)==='orange_money'?'selected':'' ?>>Orange Money</option>
            <option value="mtn_momo" <?= strtolower($opFilter)==='mtn_momo'?'selected':'' ?>>MTN MoMo</option>
            <option value="moov_money" <?= strtolower($opFilter)==='moov_money'?'selected':'' ?>>Moov Money</option>
            <option value="cash" <?= strtolower($opFilter)==='cash'?'selected':'' ?>>Espèces</option>
          </select>
          <select id="statutSelect" class="form-control" style="width:150px" onchange="applyFilters()">
            <option value="">Tous statuts</option>
            <option value="succes" <?= $statFilter==='succes'?'selected':'' ?>>Validé</option>
            <option value="en_cours" <?= $statFilter==='en_cours'?'selected':'' ?>>En attente</option>
            <option value="echec" <?= $statFilter==='echec'?'selected':'' ?>>Échoué</option>
          </select>
          <?php if($search || $opFilter || $statFilter): ?>
            <a href="index.php" class="btn-outline" style="padding:8px 14px;font-size:.82rem;"><span class="material-icons" style="font-size:14px">close</span> Effacer</a>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Graphique Répartition -->
      <div class="card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px;margin-bottom:0;">
        <h3 style="font-family:'Oswald',sans-serif;font-size:.9rem;color:var(--blue);text-transform:uppercase;margin-bottom:10px;align-self:flex-start">Répartition</h3>
        <div style="position:relative;height:140px;width:100%">
          <canvas id="opChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div class="table-header">
        <h3>Historique des transactions</h3>
        <span style="font-size:.8rem;color:var(--muted)"><?= $total ?> transaction(s)</span>
      </div>
      <div style="overflow-x:auto">
        <table class="klinik-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Référence</th>
              <?php if($isAdminOrCaissier): ?><th>Patient</th><?php endif; ?>
              <th>Téléphone</th>
              <th>Opérateur</th>
              <th>Montant</th>
              <th>Statut</th>
              <th style="text-align:right">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($paiements)): ?>
            <tr><td colspan="<?= $isAdminOrCaissier ? 8 : 7 ?>" style="text-align:center;padding:32px;color:var(--muted)">Aucun paiement trouvé</td></tr>
            <?php else: foreach($paiements as $p): 
                $op = strtolower(str_replace('_', '', $p['provider']));
                $opClass = 'op-'.$op;
            ?>
            <tr>
              <td style="font-size:.85rem;font-weight:600"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
              <td style="font-size:.8rem;font-family:monospace;color:var(--muted)"><?= htmlspecialchars($p['transaction_id'] ?: '—') ?></td>
              <?php if($isAdminOrCaissier): ?>
                <td><strong><?= htmlspecialchars($p['patient_prenom'].' '.$p['patient_nom']) ?></strong></td>
              <?php endif; ?>
              <td style="font-size:.85rem"><?= htmlspecialchars($p['telephone'] ?: '—') ?></td>
              <td><span class="op-chip <?= $opClass ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $p['provider']))) ?></span></td>
              <td style="font-weight:700;color:var(--blue)"><?= number_format($p['montant'],0,',',' ') ?> F</td>
              <td><span class="status-badge <?= $statutColors[$p['statut']] ?? 'status-pending' ?>"><?= $statutLabels[$p['statut']] ?? '—' ?></span></td>
              <td style="text-align:right">
                <?php if($p['statut'] === 'succes'): ?>
                  <a href="recu.php?id=<?= $p['id'] ?>" target="_blank" class="action-btn btn-receipt" title="Télécharger le reçu">Reçu PDF</a>
                <?php elseif($p['statut'] === 'en_cours' && $isAdminOrCaissier): ?>
                  <button class="action-btn btn-valid" title="Valider le paiement" onclick="validerPaiement(<?= $p['id'] ?>)">Valider</button>
                  <button class="action-btn btn-cancel" title="Annuler le paiement" onclick="annulerPaiement(<?= $p['id'] ?>)">Annuler</button>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:.8rem">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Modal Simulation Paiement (Patient) -->
<?php if(!$isAdminOrCaissier): ?>
<div class="modal-overlay" id="modalSimulate">
  <div class="modal">
    <div class="spinner-overlay" id="simSpinner">
      <div class="spinner"></div>
      <h3 style="color:var(--blue);font-family:'Oswald'">Traitement en cours...</h3>
      <p style="color:var(--muted);font-size:.85rem;margin-top:4px">Connexion à l'opérateur en cours</p>
    </div>
    
    <div class="spinner-overlay" id="simResult" style="background:#fff">
      <div id="simIcon" class="material-icons" style="font-size:64px;margin-bottom:16px"></div>
      <h3 id="simTitle" style="color:var(--blue);font-family:'Oswald';font-size:1.5rem"></h3>
      <p id="simText" style="color:var(--muted);font-size:.9rem;margin-top:8px;text-align:center"></p>
      <div style="margin-top:24px;display:flex;gap:12px;width:100%">
        <button class="btn-outline" style="flex:1" onclick="location.reload()">Fermer</button>
        <a href="#" id="simReceiptBtn" class="btn-primary" target="_blank" style="flex:1;display:none;text-align:center">Télécharger Reçu</a>
      </div>
    </div>

    <div class="modal-header">
      <h3>Simuler un Paiement SaaS</h3>
      <button class="modal-close" onclick="document.getElementById('modalSimulate').classList.remove('open')"><span class="material-icons">close</span></button>
    </div>
    
    <!-- Étape 1 : Formulaire -->
    <div id="simFormStep">
      <div class="form-group" style="margin-bottom:16px">
        <label style="display:block;font-size:.8rem;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase">Facture Impayée</label>
        <select id="simFacture" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1px solid #eef0f6" onchange="updateSimAmount()">
          <option value="">Sélectionner une facture...</option>
          <?php foreach($facturesImpayees as $f): ?>
            <option value="<?= $f['id'] ?>" data-amount="<?= $f['montant_total'] ?>">Facture #<?= $f['id'] ?> - <?= date('d/m/Y', strtotime($f['date_facture'])) ?> (<?= number_format($f['montant_total'],0,',',' ') ?> F)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:16px">
        <label style="display:block;font-size:.8rem;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase">Opérateur</label>
        <select id="simProvider" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1px solid #eef0f6">
          <option value="wave">Wave</option>
          <option value="orange_money">Orange Money</option>
          <option value="mtn_momo">MTN MoMo</option>
          <option value="moov_money">Moov Money</option>
          <option value="cash">Espèces (Dépôt)</option>
        </select>
      </div>
      
      <div class="form-group" style="margin-bottom:16px">
        <label style="display:block;font-size:.8rem;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase">Numéro de téléphone</label>
        <input type="text" id="simPhone" class="form-control" placeholder="Ex: 0700000000" style="width:100%;padding:10px;border-radius:8px;border:1px solid #eef0f6">
      </div>
      
      <div class="form-group" style="margin-bottom:24px">
        <label style="display:block;font-size:.8rem;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase">Montant à payer (FCFA)</label>
        <input type="number" id="simAmount" class="form-control" readonly style="width:100%;padding:10px;border-radius:8px;border:1px solid #eef0f6;background:#f8f9fc;font-weight:700">
      </div>

      <button class="btn-primary" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px" onclick="startSimulation()">
        <span class="material-icons">lock</span> Lancer le paiement
      </button>
    </div>

    <!-- Étape 2 : OTP -->
    <div id="simOtpStep" style="display:none;text-align:center;padding:20px 0">
      <div class="material-icons" style="font-size:48px;color:var(--blue-bright);margin-bottom:12px">sms</div>
      <h3 style="font-family:'Oswald';font-size:1.2rem;margin-bottom:8px">Validation OTP</h3>
      <p style="font-size:.85rem;color:var(--muted);margin-bottom:20px">Saisissez le code reçu par SMS (Simulation : 0404)</p>
      
      <input type="text" id="simOtpInput" maxlength="4" placeholder="••••" style="width:120px;text-align:center;font-size:1.8rem;letter-spacing:8px;padding:10px;border-radius:8px;border:2px solid var(--blue-bright);outline:none;font-family:monospace;margin-bottom:20px">
      
      <button class="btn-primary" style="width:100%" onclick="verifyOtp()">
        Confirmer le paiement
      </button>
      <button class="btn-link" style="margin-top:12px;font-size:.8rem;color:var(--muted)" onclick="location.reload()">Annuler</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="../../assets/js/klinik.js"></script>
<script>
// Graphique
const ctx = document.getElementById('opChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            data: <?= json_encode($dataArr) ?>,
            backgroundColor: <?= json_encode($colors) ?>,
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { boxWidth:12, font: { family: "'Source Sans 3', sans-serif" } } }
        },
        cutout: '70%'
    }
});

let searchTimeout;
function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(applyFilters, 500);
}
function applyFilters() {
  const q = document.getElementById('searchInput').value;
  const op = document.getElementById('opSelect').value;
  const st = document.getElementById('statutSelect').value;
  let url = new URL(window.location.href);
  url.searchParams.set('search', q);
  url.searchParams.set('operateur', op);
  url.searchParams.set('statut', st);
  url.searchParams.set('page', '1');
  window.location.href = url.href;
}

// ════════════════════════════════════════
// Actions Caissier / Admin
// ════════════════════════════════════════
async function validerPaiement(id) {
    if(!confirm("Confirmer la validation de ce paiement ?")) return;
    try {
        const res = await fetch('../../api/paiements.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'valider', pm_id: id})
        });
        const d = await res.json();
        if(d.success) location.reload();
        else alert(d.message);
    } catch(e) {}
}
async function annulerPaiement(id) {
    if(!confirm("Confirmer l'annulation de ce paiement ?")) return;
    try {
        const res = await fetch('../../api/paiements.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'annuler', pm_id: id})
        });
        const d = await res.json();
        if(d.success) location.reload();
        else alert(d.message);
    } catch(e) {}
}

// ════════════════════════════════════════
// Simulation Patient
// ════════════════════════════════════════
<?php if(!$isAdminOrCaissier): ?>
function openSimulationModal() {
    document.getElementById('modalSimulate').classList.add('open');
}

function updateSimAmount() {
    const sel = document.getElementById('simFacture');
    const opt = sel.options[sel.selectedIndex];
    const amountInput = document.getElementById('simAmount');
    if(opt && opt.value) {
        amountInput.value = opt.getAttribute('data-amount');
    } else {
        amountInput.value = '';
    }
}

let currentTxId = null;

async function startSimulation() {
    const factureId = document.getElementById('simFacture').value;
    const provider = document.getElementById('simProvider').value;
    const phone = document.getElementById('simPhone').value;
    const amount = document.getElementById('simAmount').value;
    
    if(!factureId || !phone || !amount) {
        alert("Veuillez remplir tous les champs.");
        return;
    }

    // Afficher spinner
    document.getElementById('simSpinner').style.display = 'flex';
    
    try {
        const resInit = await fetch('../../api/paiements.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'initier', facture_id: factureId, provider: provider, telephone: phone, montant: amount })
        });
        const dInit = await resInit.json();
        
        document.getElementById('simSpinner').style.display = 'none';

        if(!dInit.success) {
            alert(dInit.message);
            return;
        }

        currentTxId = dInit.transaction_id;

        // Passage à l'étape OTP
        document.getElementById('simFormStep').style.display = 'none';
        document.getElementById('simOtpStep').style.display = 'block';
        
    } catch(e) {
        document.getElementById('simSpinner').style.display = 'none';
        alert("Erreur réseau durant l'initiation.");
    }
}

async function verifyOtp() {
    const otp = document.getElementById('simOtpInput').value;
    if(!otp) { alert("Saisissez l'OTP."); return; }

    document.getElementById('simSpinner').style.display = 'flex';

    try {
        const res = await fetch('../../api/paiements.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'verify_otp', transaction_id: currentTxId, otp: otp })
        });
        const d = await res.json();
        
        document.getElementById('simSpinner').style.display = 'none';
        
        const resOverlay = document.getElementById('simResult');
        const icon = document.getElementById('simIcon');
        const title = document.getElementById('simTitle');
        const text = document.getElementById('simText');
        
        resOverlay.style.display = 'flex';
        document.getElementById('simOtpStep').style.display = 'none';

        if(d.success) {
            icon.innerHTML = 'check_circle';
            icon.style.color = 'var(--success)';
            title.innerText = 'Paiement Réussi !';
            text.innerText = 'Votre transaction a été validée avec succès.';
            
            // Afficher bouton reçu si possible
            const btn = document.getElementById('simReceiptBtn');
            btn.style.display = 'block';
            btn.href = 'recu.php?tx=' + currentTxId;
        } else {
            icon.innerHTML = 'error';
            icon.style.color = 'var(--danger)';
            title.innerText = 'Échec du Paiement';
            text.innerText = d.message;
            
            // Bouton retour au formulaire si erreur
            setTimeout(() => {
                resOverlay.style.display = 'none';
                document.getElementById('simOtpStep').style.display = 'block';
            }, 2000);
        }
    } catch(e) {
        document.getElementById('simSpinner').style.display = 'none';
        alert("Erreur réseau durant la vérification.");
    }
}
<?php endif; ?>
</script>
</body>
</html>
