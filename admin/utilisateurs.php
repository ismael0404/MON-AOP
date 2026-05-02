<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['admin']);
$user = getUser();
$pdo  = getDB();

// ── Paramètres de recherche ──
$search      = sanitize($_GET['search'] ?? '');
$roleFilter  = sanitize($_GET['role']   ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 10;
$offset      = ($page - 1) * $perPage;

// ── Requête avec filtres ──
$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $where   .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($roleFilter) {
    $where   .= " AND u.role = ?";
    $params[] = $roleFilter;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs u $where");
$total->execute($params); $total = (int)$total->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT u.*, m.specialite, p.groupe_sanguin
    FROM utilisateurs u
    LEFT JOIN medecins m ON u.id = m.utilisateur_id
    LEFT JOIN patients p ON u.id = p.utilisateur_id
    $where
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll();

// ── Compteurs par rôle ──
$counts = $pdo->query("SELECT role, COUNT(*) as nb FROM utilisateurs GROUP BY role")->fetchAll();
$countMap = array_column($counts, 'nb', 'role');

$roleLabels = ['admin'=>'Administrateur','medecin'=>'Médecin','patient'=>'Patient','laborantin'=>'Laborantin','caissier'=>'Caissier'];
$roleColors = ['admin'=>'chip-admin','medecin'=>'chip-medecin','patient'=>'chip-patient','laborantin'=>'chip-laborantin','caissier'=>'chip-caissier'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Gestion Utilisateurs</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style>
    .role-chip{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600}
    .chip-admin{background:#ede9fe;color:#6d28d9}.chip-medecin{background:#cffafe;color:#0e7490}
    .chip-patient{background:#dbeafe;color:#1d4ed8}.chip-laborantin{background:#d1fae5;color:#065f46}
    .chip-caissier{background:#fef3c7;color:#92400e}
    .status-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600}
    .status-active{background:#d1fae5;color:#065f46}.status-inactive{background:#fee2e2;color:#991b1b}

    /* Stats mini */
    .stats-mini{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
    .stat-mini{background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:14px 16px;text-align:center;box-shadow:0 2px 8px rgba(26,58,110,.05);transition:transform .2s}
    .stat-mini:hover{transform:translateY(-2px)}
    .stat-mini .nb{font-family:'Oswald',sans-serif;font-size:1.7rem;font-weight:700;color:var(--blue);line-height:1}
    .stat-mini .lb{font-size:.72rem;color:var(--muted);margin-top:3px}

    /* Barre de recherche */
    .search-bar{background:#fff;border:1.5px solid #eef0f6;border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px;box-shadow:0 2px 8px rgba(26,58,110,.04)}
    .search-input-wrap{flex:1;min-width:200px;position:relative}
    .search-input-wrap .material-icons{position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px;color:#9ca3af;pointer-events:none}
    .search-input-wrap input{width:100%;padding:9px 12px 9px 36px;border:1.5px solid #eef0f6;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .2s;background:#f8f9fc}
    .search-input-wrap input:focus{border-color:var(--blue-bright);background:#fff}
    .search-bar select{padding:9px 14px;border:1.5px solid #eef0f6;border-radius:8px;font-size:.88rem;outline:none;background:#f8f9fc;cursor:pointer;transition:border-color .2s}
    .search-bar select:focus{border-color:var(--blue-bright)}

    /* Table */
    .table-card{background:#fff;border:1.5px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .table-header{padding:18px 22px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between}
    .table-header h3{font-family:'Oswald',sans-serif;font-size:1rem;color:var(--blue);text-transform:uppercase;letter-spacing:.5px}

    /* Boutons action */
    .action-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;border:none;cursor:pointer;transition:all .18s}
    .action-btn .material-icons{font-size:16px}
    .btn-edit{background:#dbeafe;color:#1d4ed8}.btn-edit:hover{background:#1d4ed8;color:#fff}
    .btn-toggle-on{background:#d1fae5;color:#065f46}.btn-toggle-on:hover{background:#065f46;color:#fff}
    .btn-toggle-off{background:#fef3c7;color:#92400e}.btn-toggle-off:hover{background:#92400e;color:#fff}
    .btn-delete{background:#fee2e2;color:#991b1b}.btn-delete:hover{background:#991b1b;color:#fff}

    /* Pagination */
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-top:1px solid #eef0f6;font-size:.83rem;color:var(--muted)}
    .page-btns{display:flex;gap:6px}
    .page-btn{padding:5px 11px;border:1.5px solid #eef0f6;border-radius:6px;background:#fff;font-size:.8rem;cursor:pointer;transition:all .18s;text-decoration:none;color:var(--text)}
    .page-btn:hover{border-color:var(--blue-bright);color:var(--blue-bright)}
    .page-btn.active{background:var(--blue-bright);border-color:var(--blue-bright);color:#fff}

    /* Modals */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;display:none}
    .alert-msg.show{display:block}
    .alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
    .alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}

    /* Confirm dialog */
    .confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:300}
    .confirm-overlay.open{display:flex}
    .confirm-box{background:#fff;border-radius:14px;padding:28px;width:100%;max-width:380px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.25);animation:fadeUp .3s ease}
    .confirm-icon{width:52px;height:52px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
    .confirm-icon .material-icons{font-size:26px;color:#dc2626}
    .confirm-title{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);margin-bottom:6px}
    .confirm-msg{font-size:.85rem;color:var(--muted);margin-bottom:20px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:900px){.stats-mini{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:600px){.stats-mini{grid-template-columns:repeat(2,1fr)}}
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
    <div class="nav-section-title">Système</div>
    <a class="nav-item" href="rapports.php">
      <span class="material-icons">bar_chart</span> Rapports
    </a>
    <a class="nav-item" href="parametres.php">
      <span class="material-icons">settings</span> Paramètres
    </a>
  </nav>
  <div class="sidebar-footer">
    <a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a>
  </div>
</aside>

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
      <input type="text" placeholder="Rechercher patient, médecin, rendez-vous...">
    </div>
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
    <div class="page-header" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <h1>Gestion des utilisateurs</h1>
        <p><?= $total ?> utilisateur(s) trouvé(s)</p>
      </div>
      <button class="btn-primary" onclick="openModal('modalCreate')">
        <span class="material-icons">person_add</span> Nouvel utilisateur
      </button>
    </div>

    <!-- Stats mini par rôle -->
    <div class="stats-mini">
      <?php
      $roleIcons = ['admin'=>['manage_accounts','#7c3aed'],'medecin'=>['stethoscope','#0891b2'],'patient'=>['groups','#2563eb'],'laborantin'=>['science','#059669'],'caissier'=>['payments','#d97706']];
      foreach ($roleLabels as $r => $lbl):
        $nb = $countMap[$r] ?? 0;
        [$icon,$color] = $roleIcons[$r];
      ?>
      <div class="stat-mini" onclick="filterRole('<?= $r ?>')" style="cursor:pointer;border-color:<?= $roleFilter===$r?$color:'#eef0f6' ?>">
        <div style="width:36px;height:36px;border-radius:9px;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 8px">
          <span class="material-icons" style="font-size:18px;color:#fff"><?= $icon ?></span>
        </div>
        <div class="nb"><?= $nb ?></div>
        <div class="lb"><?= $lbl ?>s</div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Barre de recherche -->
    <div class="search-bar">
      <div class="search-input-wrap">
        <span class="material-icons">search</span>
        <input type="text" id="searchInput" placeholder="Rechercher par nom, email, téléphone..."
               value="<?= htmlspecialchars($search) ?>" onkeyup="debounceSearch()">
      </div>
      <select id="roleSelect" onchange="applyFilters()">
        <option value="">Tous les rôles</option>
        <?php foreach ($roleLabels as $r => $lbl): ?>
        <option value="<?= $r ?>" <?= $roleFilter===$r?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($search || $roleFilter): ?>
      <a href="utilisateurs.php" class="btn-outline" style="padding:8px 14px;font-size:.83rem;white-space:nowrap">
        <span class="material-icons" style="font-size:15px">close</span> Effacer
      </a>
      <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div class="table-header">
        <h3>Utilisateurs du système</h3>
        <span style="font-size:.8rem;color:var(--muted)">Page <?= $page ?> / <?= max(1,$totalPages) ?></span>
      </div>
      <div style="overflow-x:auto">
        <table class="klinik-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nom complet</th>
              <th>Email</th>
              <th>Téléphone</th>
              <th>Rôle</th>
              <th>Spécialité / Info</th>
              <th>Statut</th>
              <th>Inscription</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($utilisateurs)): ?>
            <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--muted)">Aucun utilisateur trouvé</td></tr>
            <?php else: foreach ($utilisateurs as $i => $u): ?>
            <tr>
              <td style="color:var(--muted);font-size:.8rem"><?= str_pad($offset+$i+1, 3, '0', STR_PAD_LEFT) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div style="width:34px;height:34px;border-radius:50%;background:var(--blue-light);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:.8rem;font-weight:700;color:var(--blue);flex-shrink:0">
                    <?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:600"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></div>
                  </div>
                </div>
              </td>
              <td style="color:var(--muted);font-size:.83rem"><?= htmlspecialchars($u['email']) ?></td>
              <td style="color:var(--muted);font-size:.83rem"><?= htmlspecialchars($u['telephone'] ?? '—') ?></td>
              <td><span class="role-chip <?= $roleColors[$u['role']] ?>"><?= $roleLabels[$u['role']] ?></span></td>
              <td style="font-size:.82rem;color:var(--muted)">
                <?= $u['specialite'] ? htmlspecialchars($u['specialite']) : ($u['groupe_sanguin'] ? 'Groupe : '.$u['groupe_sanguin'] : '—') ?>
              </td>
              <td>
                <span class="status-badge <?= $u['actif'] ? 'status-active' : 'status-inactive' ?>">
                  <?= $u['actif'] ? 'Actif' : 'Inactif' ?>
                </span>
              </td>
              <td style="font-size:.78rem;color:var(--muted)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:5px">
                  <button class="action-btn btn-edit" title="Modifier"
                    onclick="openEdit(<?= $u['id'] ?>,'<?= htmlspecialchars($u['nom'],ENT_QUOTES) ?>','<?= htmlspecialchars($u['prenom'],ENT_QUOTES) ?>','<?= htmlspecialchars($u['email'],ENT_QUOTES) ?>','<?= $u['role'] ?>','<?= htmlspecialchars($u['telephone']??'',ENT_QUOTES) ?>')">
                    <span class="material-icons">edit</span>
                  </button>
                  <button class="action-btn <?= $u['actif'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>" title="<?= $u['actif'] ? 'Désactiver' : 'Activer' ?>"
                    onclick="toggleUser(<?= $u['id'] ?>, <?= $u['actif'] ?>)">
                    <span class="material-icons"><?= $u['actif'] ? 'toggle_on' : 'toggle_off' ?></span>
                  </button>
                  <?php if ($u['id'] !== $user['id']): ?>
                  <button class="action-btn btn-delete" title="Supprimer"
                    onclick="confirmDelete(<?= $u['id'] ?>,'<?= htmlspecialchars($u['prenom'].' '.$u['nom'],ENT_QUOTES) ?>')">
                    <span class="material-icons">delete</span>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <span>Affichage <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> sur <?= $total ?></span>
        <div class="page-btns">
          <?php if ($page > 1): ?>
          <a class="page-btn" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>">
            <span class="material-icons" style="font-size:15px;vertical-align:middle">chevron_left</span>
          </a>
          <?php endif; ?>
          <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <a class="page-btn <?= $p===$page?'active':'' ?>" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
          <a class="page-btn" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>">
            <span class="material-icons" style="font-size:15px;vertical-align:middle">chevron_right</span>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal Créer -->
<div class="modal-overlay" id="modalCreate">
  <div class="modal">
    <div class="modal-header">
      <h3>Créer un utilisateur</h3>
      <button class="modal-close" onclick="closeModal('modalCreate')"><span class="material-icons">close</span></button>
    </div>
    <div class="alert-msg" id="createAlert"></div>
    <div class="form-row">
      <div class="form-group"><label>Nom *</label><input type="text" id="cNom" class="form-control" placeholder="NOM"></div>
      <div class="form-group"><label>Prénom *</label><input type="text" id="cPrenom" class="form-control" placeholder="Prénom"></div>
    </div>
    <div class="form-group"><label>Email *</label><input type="email" id="cEmail" class="form-control" placeholder="email@klinik.ci"></div>
    <div class="form-group"><label>Téléphone</label><input type="tel" id="cTel" class="form-control" placeholder="+225 07 00 00 00"></div>
    <div class="form-row">
      <div class="form-group">
        <label>Rôle *</label>
        <select id="cRole" class="form-control">
          <option value="">Sélectionner...</option>
          <option value="admin">Administrateur</option>
          <option value="medecin">Médecin</option>
          <option value="patient">Patient</option>
          <option value="laborantin">Laborantin</option>
          <option value="caissier">Caissier</option>
        </select>
      </div>
      <div class="form-group"><label>Mot de passe *</label><input type="password" id="cPwd" class="form-control" placeholder="Minimum 6 caractères"></div>
    </div>
    <div style="display:flex;gap:12px;margin-top:16px">
      <button class="btn-outline" onclick="closeModal('modalCreate')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="createUser()" style="flex:1" id="btnCreate">
        <span class="material-icons">person_add</span> Créer
      </button>
    </div>
  </div>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header">
      <h3>Modifier l'utilisateur</h3>
      <button class="modal-close" onclick="closeModal('modalEdit')"><span class="material-icons">close</span></button>
    </div>
    <div class="alert-msg" id="editAlert"></div>
    <input type="hidden" id="eId">
    <div class="form-row">
      <div class="form-group"><label>Nom *</label><input type="text" id="eNom" class="form-control"></div>
      <div class="form-group"><label>Prénom *</label><input type="text" id="ePrenom" class="form-control"></div>
    </div>
    <div class="form-group"><label>Email *</label><input type="email" id="eEmail" class="form-control"></div>
    <div class="form-group"><label>Téléphone</label><input type="tel" id="eTel" class="form-control"></div>
    <div class="form-row">
      <div class="form-group">
        <label>Rôle *</label>
        <select id="eRole" class="form-control">
          <option value="admin">Administrateur</option>
          <option value="medecin">Médecin</option>
          <option value="patient">Patient</option>
          <option value="laborantin">Laborantin</option>
          <option value="caissier">Caissier</option>
        </select>
      </div>
      <div class="form-group"><label>Nouveau mot de passe</label><input type="password" id="ePwd" class="form-control" placeholder="Laisser vide = inchangé"></div>
    </div>
    <div style="display:flex;gap:12px;margin-top:16px">
      <button class="btn-outline" onclick="closeModal('modalEdit')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="updateUser()" style="flex:1" id="btnEdit">
        <span class="material-icons">save</span> Enregistrer
      </button>
    </div>
  </div>
</div>

<!-- Confirm supprimer -->
<div class="confirm-overlay" id="confirmDelete">
  <div class="confirm-box">
    <div class="confirm-icon"><span class="material-icons">warning</span></div>
    <div class="confirm-title">Supprimer l'utilisateur ?</div>
    <div class="confirm-msg" id="confirmMsg">Cette action est irréversible.</div>
    <input type="hidden" id="deleteId">
    <div style="display:flex;gap:10px;justify-content:center">
      <button class="btn-outline" onclick="document.getElementById('confirmDelete').classList.remove('open')" style="padding:9px 22px">Annuler</button>
      <button class="btn-primary" style="padding:9px 22px;background:var(--danger)" onclick="doDelete()" id="btnDelete">
        <span class="material-icons">delete</span> Supprimer
      </button>
    </div>
  </div>
</div>

<script src="../assets/js/klinik.js"></script>
<script>
const user = {
  nom:'<?= htmlspecialchars($user["nom"]) ?>',
  prenom:'<?= htmlspecialchars($user["prenom"]) ?>',
  email:'<?= htmlspecialchars($user["email"]) ?>',
  role:'<?= $user["role"] ?>'
};
KlinikUI.fillUserInfo(user); KlinikUI.initSidebar();
document.getElementById('topbarUserName').textContent = user.prenom+' '+user.nom;
document.getElementById('topbarAvatar').textContent   = (user.prenom[0]+user.nom[0]).toUpperCase();

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay,.confirm-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target===m) m.classList.remove('open'); })
);

// ── Recherche avec debounce ──
let searchTimer;
function debounceSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(applyFilters, 400);
}
function applyFilters() {
  const s = document.getElementById('searchInput').value;
  const r = document.getElementById('roleSelect').value;
  window.location.href = 'utilisateurs.php?search='+encodeURIComponent(s)+'&role='+encodeURIComponent(r)+'&page=1';
}
function filterRole(role) {
  const current = document.getElementById('roleSelect').value;
  document.getElementById('roleSelect').value = current===role ? '' : role;
  applyFilters();
}

// ── Créer ──
function createUser() {
  const alertEl = document.getElementById('createAlert');
  const nom=document.getElementById('cNom').value.trim();
  const prenom=document.getElementById('cPrenom').value.trim();
  const email=document.getElementById('cEmail').value.trim();
  const role=document.getElementById('cRole').value;
  const pwd=document.getElementById('cPwd').value;
  const tel=document.getElementById('cTel').value.trim();
  alertEl.className='alert-msg';
  if(!nom||!prenom||!email||!role||!pwd){alertEl.textContent='Veuillez remplir tous les champs obligatoires.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnCreate');btn.disabled=true;
  btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span> Création...';
  fetch('../api/utilisateurs.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'create',nom,prenom,email,role,password:pwd,telephone:tel})})
  .then(r=>r.json()).then(data=>{
    if(data.success){alertEl.textContent='Utilisateur créé avec succès !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalCreate');location.reload()},1200);}
    else{alertEl.textContent=data.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">person_add</span> Créer';}
  });
}

// ── Modifier ──
function openEdit(id,nom,prenom,email,role,tel){
  document.getElementById('eId').value=id;
  document.getElementById('eNom').value=nom;
  document.getElementById('ePrenom').value=prenom;
  document.getElementById('eEmail').value=email;
  document.getElementById('eRole').value=role;
  document.getElementById('eTel').value=tel;
  document.getElementById('ePwd').value='';
  document.getElementById('editAlert').className='alert-msg';
  openModal('modalEdit');
}
function updateUser(){
  const alertEl=document.getElementById('editAlert');
  const id=document.getElementById('eId').value;
  const nom=document.getElementById('eNom').value.trim();
  const prenom=document.getElementById('ePrenom').value.trim();
  const email=document.getElementById('eEmail').value.trim();
  const role=document.getElementById('eRole').value;
  const tel=document.getElementById('eTel').value.trim();
  const pwd=document.getElementById('ePwd').value;
  alertEl.className='alert-msg';
  if(!nom||!prenom||!email||!role){alertEl.textContent='Veuillez remplir tous les champs obligatoires.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnEdit');btn.disabled=true;
  btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/utilisateurs.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'update',id,nom,prenom,email,role,telephone:tel,password:pwd||null})})
  .then(r=>r.json()).then(data=>{
    if(data.success){alertEl.textContent='Modifié avec succès !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalEdit');location.reload()},1200);}
    else{alertEl.textContent=data.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">save</span> Enregistrer';}
  });
}

// ── Toggle actif/inactif ──
function toggleUser(id, actif){
  fetch('../api/utilisateurs.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'toggle',id,actif:actif?0:1})})
  .then(r=>r.json()).then(data=>{ if(data.success) location.reload(); });
}

// ── Supprimer ──
function confirmDelete(id, nom){
  document.getElementById('deleteId').value=id;
  document.getElementById('confirmMsg').textContent='Voulez-vous vraiment supprimer "'+nom+'" ? Cette action est irréversible.';
  document.getElementById('confirmDelete').classList.add('open');
}
function doDelete(){
  const id=document.getElementById('deleteId').value;
  const btn=document.getElementById('btnDelete');btn.disabled=true;
  fetch('../api/utilisateurs.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'delete',id})})
  .then(r=>r.json()).then(data=>{ if(data.success) location.reload(); });
}
</script>
</body>
</html>
