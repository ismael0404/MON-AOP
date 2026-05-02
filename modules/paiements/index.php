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

if (!$isAdminOrCaissier) {
    // If patient, find patient_id and only show their payments
    $stmtP = $pdo->prepare("SELECT id FROM patients WHERE utilisateur_id = ?");
    $stmtP->execute([$uid]);
    $patientId = $stmtP->fetchColumn();
    // A patient is linked to facture via consultation or directly via facture
    // The table is `paiements_mobile`, which is linked to `facture_id`. 
    // And `factures` table is linked to `patient_id`.
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

// Données pour le graphe (Opérateurs)
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
  <title>KLINIK — Gestion des Paiements</title>
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
    
    .pagination { display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #eef0f6;font-size:.82rem;color:var(--muted); }
    .page-btns { display:flex;gap:5px; }
    .page-btn { padding:5px 10px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.78rem;text-decoration:none;color:var(--text);transition:all .18s; }
    .page-btn:hover { border-color:var(--blue-bright);color:var(--blue-bright); }
    .page-btn.active { background:var(--blue-bright);border-color:var(--blue-bright);color:#fff; }

    .op-chip { display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase; }
    .op-wave { background:#e0f2fe;color:#0369a1; }
    .op-orange { background:#ffedd5;color:#c2410c; }
    .op-mtn { background:#fef9c3;color:#a16207; }
    .op-moov { background:#dbeafe;color:#1e3a8a; }
    .op-cash { background:#d1fae5;color:#065f46; }

    .action-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;border:none;cursor:pointer;transition:all .18s;text-decoration:none}
    .action-btn .material-icons{font-size:16px}
    .btn-view{background:#dbeafe;color:#1d4ed8}.btn-view:hover{background:#1d4ed8;color:#fff}
    
    /* Layout */
    .top-layout { display:grid;grid-template-columns:1fr 300px;gap:20px;margin-bottom:20px; }
    @media(max-width:900px) { .top-layout { grid-template-columns:1fr; } }
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
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
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
    
    <div class="page-header" style="margin-bottom:24px">
      <h1>Paiements</h1>
      <p>Suivi et gestion des paiements Mobile Money et espèces</p>
    </div>

    <div class="top-layout">
      <div>
        <div class="stats-row">
          <div class="stat-card"><div class="stat-icon" style="background:var(--success)"><span class="material-icons">account_balance_wallet</span></div><div><div class="stat-value"><?= number_format($totalRev,0,',',' ') ?></div><div class="stat-label">Total Validé (FCFA)</div></div></div>
          <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">today</span></div><div><div class="stat-value"><?= number_format($jourRev,0,',',' ') ?></div><div class="stat-label">Revenus du jour (FCFA)</div></div></div>
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
            </tr>
          </thead>
          <tbody>
            <?php if(empty($paiements)): ?>
            <tr><td colspan="<?= $isAdminOrCaissier ? 7 : 6 ?>" style="text-align:center;padding:32px;color:var(--muted)">Aucun paiement trouvé</td></tr>
            <?php else: foreach($paiements as $p): 
                $op = strtolower(str_replace('_', '', $p['provider'])); // wave, orangemoney, mtnmomo, moovmoney
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
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="pagination">
        <span>Affichage <?= min($offset+1, $total) ?>–<?= min($offset+$perPage, $total) ?> sur <?= $total ?></span>
        <div class="page-btns">
          <?php if($page > 1): ?><a class="page-btn" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&operateur=<?= urlencode($opFilter) ?>&statut=<?= urlencode($statFilter) ?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_left</span></a><?php endif; ?>
          <?php for($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
            <a class="page-btn <?= $p===$page?'active':'' ?>" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&operateur=<?= urlencode($opFilter) ?>&statut=<?= urlencode($statFilter) ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if($page < $totalPages): ?><a class="page-btn" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&operateur=<?= urlencode($opFilter) ?>&statut=<?= urlencode($statFilter) ?>"><span class="material-icons" style="font-size:14px;vertical-align:middle">chevron_right</span></a><?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="../../assets/js/klinik.js"></script>
<script>
let st; function debounceSearch(){ clearTimeout(st); st=setTimeout(applyFilters, 400); }
function applyFilters(){
  window.location.href='index.php?search='+encodeURIComponent(document.getElementById('searchInput').value)+
    '&operateur='+encodeURIComponent(document.getElementById('opSelect').value)+
    '&statut='+encodeURIComponent(document.getElementById('statutSelect').value)+'&page=1';
}

// Graphique
const ctx = document.getElementById('opChart').getContext('2d');
new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [{
      data: <?= json_encode($dataArr) ?>,
      backgroundColor: <?= json_encode($colors) ?>,
      borderWidth: 0
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'right', labels: { font: { size: 10 }, boxWidth: 12 } } },
    cutout: '65%'
  }
});
</script>
</body>
</html>
