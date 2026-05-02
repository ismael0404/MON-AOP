<?php
require_once '../includes/check_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(['patient']);
$user = getUser();
$pdo  = getDB();
$patientId = $user['patient_id'] ?? null;

function qry($pdo,$sql,$p=[]){$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchColumn();}

$nbRdv      = $patientId ? qry($pdo,"SELECT COUNT(*) FROM rendez_vous WHERE patient_id=? AND statut!='annule'",[$patientId]) : 0;
$nbConsult  = $patientId ? qry($pdo,"SELECT COUNT(*) FROM consultations WHERE patient_id=?",[$patientId]) : 0;
$nbImpayees = $patientId ? qry($pdo,"SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='impayee'",[$patientId]) : 0;
$nbExamens  = $patientId ? qry($pdo,"SELECT COUNT(*) FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.patient_id=? AND e.statut='transmis'",[$patientId]) : 0;

// Prochains RDV
$rdvs = [];
if ($patientId) {
    $s=$pdo->prepare("SELECT r.*,u.nom as med_nom,u.prenom as med_prenom,m.specialite FROM rendez_vous r JOIN medecins m ON r.medecin_id=m.id JOIN utilisateurs u ON m.utilisateur_id=u.id WHERE r.patient_id=? AND r.date_rdv>=NOW() AND r.statut!='annule' ORDER BY r.date_rdv ASC LIMIT 6");
    $s->execute([$patientId]); $rdvs=$s->fetchAll();
}

// Dernières consultations
$consults = [];
if ($patientId) {
    $s=$pdo->prepare("SELECT c.*,u.nom as med_nom,u.prenom as med_prenom FROM consultations c JOIN medecins m ON c.medecin_id=m.id JOIN utilisateurs u ON m.utilisateur_id=u.id WHERE c.patient_id=? ORDER BY c.date_consult DESC LIMIT 5");
    $s->execute([$patientId]); $consults=$s->fetchAll();
}

// Historique factures
$factures = [];
if ($patientId) {
    $s=$pdo->prepare("SELECT * FROM factures WHERE patient_id=? ORDER BY date_facture DESC LIMIT 6");
    $s->execute([$patientId]); $factures=$s->fetchAll();
}

// Dossier patient
$dossier = null;
if ($patientId) {
    $s=$pdo->prepare("SELECT p.*,u.nom as med_nom,u.prenom as med_prenom,m.specialite FROM patients p LEFT JOIN medecins m ON p.medecin_traitant_id=m.id LEFT JOIN utilisateurs u ON m.utilisateur_id=u.id WHERE p.id=?");
    $s->execute([$patientId]); $dossier=$s->fetch();
}

// Activité consultations 6 mois
$activiteMois=[]; $activiteVals=[];
for($i=5;$i>=0;$i--){
    $m=date('Y-m',strtotime("-$i months"));
    $activiteMois[]=date('M Y',strtotime("-$i months"));
    $activiteVals[]=$patientId?qry($pdo,"SELECT COUNT(*) FROM consultations WHERE patient_id=? AND DATE_FORMAT(date_consult,'%Y-%m')=?",[$patientId,$m]):0;
}

// Medecins pour RDV
$meds=$pdo->query("SELECT m.id,u.nom,u.prenom,m.specialite FROM medecins m JOIN utilisateurs u ON m.utilisateur_id=u.id ORDER BY u.nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KLINIK — Espace Patient</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    .dash-grid{display:grid;grid-template-columns:1fr 1fr 280px;gap:20px;align-items:start}
    .col-left{display:flex;flex-direction:column;gap:20px}
    .col-mid{display:flex;flex-direction:column;gap:20px}
    .col-right{display:flex;flex-direction:column;gap:20px}
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:14px;padding:18px;display:flex;align-items:center;gap:12px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05);transition:transform .25s,box-shadow .25s;animation:fadeUp .5s ease both;opacity:0}
    .stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(26,58,110,.1)}
    .stat-card:nth-child(1){animation-delay:.06s}.stat-card:nth-child(2){animation-delay:.12s}.stat-card:nth-child(3){animation-delay:.18s}.stat-card:nth-child(4){animation-delay:.24s}
    .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .stat-icon .material-icons{font-size:22px;color:#fff}
    .stat-value{font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:var(--blue);line-height:1}
    .stat-label{font-size:.72rem;color:var(--muted);margin-top:3px}
    .card{background:#fff;border-radius:14px;padding:20px;border:1.5px solid #eef0f6;box-shadow:0 2px 10px rgba(26,58,110,.05)}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
    .card-header h3{font-family:'Oswald',sans-serif;font-size:.95rem;color:var(--blue);text-transform:uppercase;letter-spacing:.5px}
    /* RDV */
    .rdv-item{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid #f0f4fa}
    .rdv-item:last-child{border-bottom:none}
    .rdv-date-box{background:var(--blue-light);border-radius:10px;padding:8px 10px;text-align:center;min-width:46px;flex-shrink:0}
    .rdv-day{font-family:'Oswald',sans-serif;font-size:1.3rem;font-weight:700;color:var(--blue);line-height:1}
    .rdv-month{font-size:.6rem;font-weight:700;text-transform:uppercase;color:var(--muted)}
    .rdv-doctor{font-weight:600;font-size:.88rem}
    .rdv-detail{font-size:.74rem;color:var(--muted);margin-top:2px}
    /* Calendrier */
    .cal-nav-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
    .cal-month{font-family:'Oswald',sans-serif;font-size:1rem;font-weight:700;color:var(--blue)}
    .cal-btn{background:none;border:1.5px solid #eef0f6;border-radius:7px;width:24px;height:24px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s}
    .cal-btn:hover{background:var(--blue);border-color:var(--blue)}
    .cal-btn:hover .material-icons{color:#fff}
    .cal-btn .material-icons{font-size:14px;color:var(--muted)}
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center}
    .cal-day-name{font-size:.58rem;font-weight:700;color:var(--muted);padding:2px 0;text-transform:uppercase}
    .cal-day{font-size:.76rem;padding:5px 2px;border-radius:6px;cursor:default;color:var(--text);position:relative;transition:background .15s}
    .cal-day.today{background:var(--blue-bright);color:#fff;font-weight:700}
    .cal-day.has-rdv::after{content:'';position:absolute;bottom:1px;left:50%;transform:translateX(-50%);width:3px;height:3px;border-radius:50%;background:#0891b2}
    .cal-day.today.has-rdv::after{background:#fff}
    .cal-day.empty{color:#d1d5db}
    /* Carte dossier */
    .dossier-card{background:linear-gradient(135deg,var(--blue) 0%,#1d4ed8 100%);border-radius:14px;padding:20px;color:#fff}
    .dossier-name{font-family:'Oswald',sans-serif;font-size:1.15rem;font-weight:700;margin-bottom:4px}
    .dossier-id{font-size:.7rem;opacity:.6;margin-bottom:14px}
    .dossier-row{display:flex;justify-content:space-between;font-size:.8rem;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.12)}
    .dossier-row:last-child{border:none}
    .dossier-row .lbl{opacity:.65}
    .dossier-row .val{font-weight:600}
    .blood-badge{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:50%;font-family:'Oswald',sans-serif;font-size:1rem;font-weight:700;margin-bottom:10px}
    /* Factures */
    .facture-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f0f4fa}
    .facture-item:last-child{border-bottom:none}
    .facture-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .facture-icon .material-icons{font-size:16px;color:#fff}
    .facture-montant{font-family:'Oswald',sans-serif;font-size:1rem;font-weight:700;color:var(--blue)}
    .facture-date{font-size:.72rem;color:var(--muted)}
    /* Actions */
    .qa-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;border:1.5px solid #eef0f6;background:#fff;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text);margin-bottom:8px}
    .qa-btn:hover{border-color:var(--blue-bright);background:var(--blue-light);transform:translateX(3px)}
    .qa-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .qa-icon .material-icons{font-size:16px;color:#fff}
    .qa-label{font-size:.83rem;font-weight:600;color:var(--blue)}
    .qa-sub{font-size:.7rem;color:var(--muted)}
    /* Modal */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:14px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:fadeUp .3s ease}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
    .modal-header h3{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--blue);text-transform:uppercase}
    .modal-close{background:none;border:none;cursor:pointer;color:var(--muted)}
    .alert-msg{padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;display:none}
    .alert-msg.show{display:block}
    .alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
    .alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    @media(max-width:1200px){.dash-grid{grid-template-columns:1fr 280px}.col-mid{display:none}}
    @media(max-width:900px){.dash-grid{grid-template-columns:1fr}.stats-row{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-role-badge badge-patient">Patient</div>
  <div class="sidebar-user">
    <div class="user-name" id="sidebarUserName">—</div>
    <div class="user-email" id="sidebarUserEmail">—</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Mon espace</div>
    <a class="nav-item active" href="dashboard.php"><span class="material-icons">dashboard</span> Tableau de bord</a>
    <a class="nav-item" href="#" onclick="openModal('modalRDV');return false;"><span class="material-icons">add_circle</span> Prendre un RDV</a>
    <a class="nav-item" href="mes-rendez-vous.php"><span class="material-icons">calendar_today</span> Mes rendez-vous</a>
    <a class="nav-item" href="mon-dossier.php"><span class="material-icons">folder_shared</span> Mon dossier médical</a>
    <a class="nav-item" href="mes-examens.php"><span class="material-icons">science</span> Mes examens</a>
    <a class="nav-item" href="mes-factures.php"><span class="material-icons">receipt_long</span> Mes factures</a>
  </nav>
  <div class="sidebar-footer">
    <a class="nav-item" href="../auth/logout.php"><span class="material-icons">logout</span> Déconnexion</a>
  </div>
</aside>
<div class="main-wrapper">
  <header class="topbar">
    <a class="topbar-logo" href="dashboard.php"><img src="../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'"><span class="logo-name">KLINIK</span></a>
    <div class="topbar-left"><button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button></div>
    <div class="topbar-search"><span class="material-icons">search</span><input type="text" placeholder="Rechercher médecin, rendez-vous..."></div>
    <div class="topbar-right">
      <div class="topbar-icon-btn"><span class="material-icons">notifications</span><?php if($nbImpayees>0):?><span class="notif-badge"><?=$nbImpayees?></span><?php endif;?></div>
      <div class="topbar-icon-btn"><span class="material-icons">mail_outline</span></div>
      <div class="topbar-user">
        <div class="topbar-avatar" id="topbarAvatar">PT</div>
        <div class="topbar-user-info">
          <div class="topbar-user-name" id="topbarUserName">—</div>
          <div class="topbar-user-role">Patient</div>
        </div>
      </div>
    </div>
  </header>
  <main class="page-content">
    <div class="page-header" style="margin-bottom:20px">
      <h1>Bonjour, <span style="color:var(--blue-bright)"><?=htmlspecialchars($user['prenom'].' '.$user['nom'])?></span></h1>
      <p>Bienvenue sur votre espace santé — <?=date('d F Y')?></p>
    </div>
    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon" style="background:var(--blue-bright)"><span class="material-icons">calendar_today</span></div><div><div class="stat-value"><?=$nbRdv?></div><div class="stat-label">Rendez-vous</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#0891b2"><span class="material-icons">stethoscope</span></div><div><div class="stat-value"><?=$nbConsult?></div><div class="stat-label">Consultations</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:#7c3aed"><span class="material-icons">science</span></div><div><div class="stat-value"><?=$nbExamens?></div><div class="stat-label">Résultats examens</div></div></div>
      <div class="stat-card"><div class="stat-icon" style="background:<?=$nbImpayees>0?'var(--danger)':'var(--success)'?>"><span class="material-icons">receipt_long</span></div><div><div class="stat-value"><?=$nbImpayees?></div><div class="stat-label">Factures impayées</div></div></div>
    </div>
    <!-- Grille -->
    <div class="dash-grid">
      <!-- Gauche -->
      <div class="col-left">
        <!-- Calendrier -->
        <div class="card">
          <div class="cal-nav-row">
            <span class="cal-month" id="calTitle"></span>
            <div style="display:flex;gap:5px">
              <button class="cal-btn" id="calPrev"><span class="material-icons">chevron_left</span></button>
              <button class="cal-btn" id="calNext"><span class="material-icons">chevron_right</span></button>
            </div>
          </div>
          <div class="cal-grid" id="calGrid"></div>
        </div>
        <!-- Prochains RDV -->
        <div class="card">
          <div class="card-header">
            <h3>Prochains rendez-vous</h3>
            <button class="btn-primary" style="padding:6px 14px;font-size:.78rem" onclick="openModal('modalRDV')"><span class="material-icons" style="font-size:15px">add</span> RDV</button>
          </div>
          <?php if(empty($rdvs)):?><p style="color:var(--muted);text-align:center;padding:20px 0;font-size:.85rem">Aucun rendez-vous à venir</p>
          <?php else: foreach($rdvs as $r):?>
          <div class="rdv-item">
            <div class="rdv-date-box"><div class="rdv-day"><?=date('d',strtotime($r['date_rdv']))?></div><div class="rdv-month"><?=date('M',strtotime($r['date_rdv']))?></div></div>
            <div style="flex:1;min-width:0">
              <div class="rdv-doctor">Dr. <?=htmlspecialchars($r['med_prenom'].' '.$r['med_nom'])?></div>
              <div class="rdv-detail"><?=htmlspecialchars($r['specialite']??'')?> — <?=date('H:i',strtotime($r['date_rdv']))?></div>
            </div>
            <span class="status-badge <?=$r['statut']==='confirme'?'status-active':'status-pending'?>" style="font-size:.68rem"><?=$r['statut']==='confirme'?'Confirmé':'En attente'?></span>
          </div>
          <?php endforeach; endif;?>
        </div>
        <!-- Graphique activité -->
        <div class="card">
          <div class="card-header"><h3>Activité médicale</h3><span style="font-size:.72rem;color:var(--muted)">6 derniers mois</span></div>
          <canvas id="chartActivite" height="120"></canvas>
        </div>
      </div>
      <!-- Milieu -->
      <div class="col-mid">
        <!-- Dernières consultations -->
        <div class="card">
          <div class="card-header"><h3>Dernières consultations</h3></div>
          <?php if(empty($consults)):?><p style="color:var(--muted);text-align:center;padding:20px 0;font-size:.85rem">Aucune consultation</p>
          <?php else: foreach($consults as $c):?>
          <div style="padding:11px 0;border-bottom:1px solid #f0f4fa">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
              <span style="font-weight:600;font-size:.88rem">Dr. <?=htmlspecialchars($c['med_prenom'].' '.$c['med_nom'])?></span>
              <span style="font-size:.72rem;color:var(--muted)"><?=date('d/m/Y',strtotime($c['date_consult']))?></span>
            </div>
            <div style="font-size:.78rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(substr($c['diagnostic']??'Consultation générale',0,60))?></div>
          </div>
          <?php endforeach; endif;?>
        </div>
        <!-- Factures -->
        <div class="card">
          <div class="card-header"><h3>Mes factures</h3></div>
          <?php if(empty($factures)):?><p style="color:var(--muted);text-align:center;padding:16px 0;font-size:.85rem">Aucune facture</p>
          <?php else: foreach($factures as $f):
            $ic=$f['statut']==='payee'?'var(--success)':($f['statut']==='partielle'?'var(--warning)':'var(--danger)');
          ?>
          <div class="facture-item">
            <div class="facture-icon" style="background:<?=$ic?>"><span class="material-icons">receipt_long</span></div>
            <div style="flex:1">
              <div class="facture-montant"><?=number_format($f['montant_total'],0,',',' ')?> FCFA</div>
              <div class="facture-date"><?=date('d/m/Y',strtotime($f['date_facture']))?></div>
            </div>
            <span class="status-badge <?=$f['statut']==='payee'?'status-active':($f['statut']==='partielle'?'status-pending':'status-inactive')?>" style="font-size:.68rem"><?=$f['statut']==='payee'?'Payée':($f['statut']==='partielle'?'Partielle':'Impayée')?></span>
          </div>
          <?php endforeach; endif;?>
        </div>
      </div>
      <!-- Droite -->
      <div class="col-right">
        <!-- Dossier médical -->
        <?php if($dossier):?>
        <div class="dossier-card">
          <?php if($dossier['groupe_sanguin']):?><div class="blood-badge"><?=htmlspecialchars($dossier['groupe_sanguin'])?></div><?php endif;?>
          <div class="dossier-name"><?=htmlspecialchars($user['prenom'].' '.$user['nom'])?></div>
          <div class="dossier-id">#PT<?=str_pad($patientId,8,'0',STR_PAD_LEFT)?></div>
          <div class="dossier-row"><span class="lbl">Naissance</span><span class="val"><?=$dossier['date_naissance']?date('d/m/Y',strtotime($dossier['date_naissance'])):'—'?></span></div>
          <div class="dossier-row"><span class="lbl">Sexe</span><span class="val"><?=$dossier['sexe']==='M'?'Masculin':($dossier['sexe']==='F'?'Féminin':'—')?></span></div>
          <div class="dossier-row"><span class="lbl">Ville</span><span class="val"><?=htmlspecialchars($dossier['ville']??'—')?></span></div>
          <?php if($dossier['med_nom']):?><div class="dossier-row"><span class="lbl">Médecin traitant</span><span class="val">Dr. <?=htmlspecialchars($dossier['med_prenom'].' '.$dossier['med_nom'])?></span></div><?php endif;?>
        </div>
        <?php endif;?>
        <!-- Donut factures -->
        <div class="card">
          <div class="card-header"><h3>Statut financier</h3></div>
          <canvas id="chartFactures" height="160"></canvas>
        </div>
        <!-- Actions -->
        <div class="card">
          <div class="card-header"><h3>Actions rapides</h3></div>
          <a class="qa-btn" href="#" onclick="openModal('modalRDV');return false;"><div class="qa-icon" style="background:var(--blue-bright)"><span class="material-icons">add_circle</span></div><div><div class="qa-label">Prendre un RDV</div><div class="qa-sub">Réserver</div></div></a>
          <a class="qa-btn" href="#"><div class="qa-icon" style="background:#7c3aed"><span class="material-icons">folder_shared</span></div><div><div class="qa-label">Mon dossier</div><div class="qa-sub">Antécédents</div></div></a>
          <a class="qa-btn" href="#"><div class="qa-icon" style="background:var(--success)"><span class="material-icons">science</span></div><div><div class="qa-label">Mes résultats</div><div class="qa-sub">Examens</div></div></a>
          <a class="qa-btn" href="#"><div class="qa-icon" style="background:var(--warning)"><span class="material-icons">receipt_long</span></div><div><div class="qa-label">Mes factures</div><div class="qa-sub">Historique</div></div></a>
        </div>
      </div>
    </div>
  </main>
</div>
<!-- Modal RDV -->
<div class="modal-overlay" id="modalRDV">
  <div class="modal">
    <div class="modal-header"><h3>Prendre un rendez-vous</h3><button class="modal-close" onclick="closeModal('modalRDV')"><span class="material-icons">close</span></button></div>
    <div class="alert-msg" id="rdvAlert"></div>
    <div class="form-group"><label>Médecin *</label><select id="rdvMedecin" class="form-control"><option value="">Sélectionner...</option><?php foreach($meds as $m) echo "<option value='{$m['id']}'>Dr. {$m['prenom']} {$m['nom']} — {$m['specialite']}</option>";?></select></div>
    <div class="form-group"><label>Date et heure *</label><input type="datetime-local" id="rdvDate" class="form-control" min="<?=date('Y-m-d\TH:i')?>"></div>
    <div class="form-group"><label>Motif</label><textarea id="rdvMotif" class="form-control" rows="3" placeholder="Motif..."></textarea></div>
    <div style="display:flex;gap:12px;margin-top:16px">
      <button class="btn-outline" onclick="closeModal('modalRDV')" style="flex:1">Annuler</button>
      <button class="btn-primary" onclick="saveRDV()" style="flex:1" id="btnRDV"><span class="material-icons">event</span> Confirmer</button>
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

// Calendrier
let cY=new Date().getFullYear(),cM=new Date().getMonth();
const today=new Date();
const rdvDates=<?=json_encode(array_map(fn($r)=>date('Y-m-d',strtotime($r['date_rdv'])),$rdvs))?>;
function renderCal(){
  const months=['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  document.getElementById('calTitle').textContent=months[cM]+' '+cY;
  const g=document.getElementById('calGrid');g.innerHTML='';
  ['Lu','Ma','Me','Je','Ve','Sa','Di'].forEach(d=>g.innerHTML+=`<div class="cal-day-name">${d}</div>`);
  const first=new Date(cY,cM,1),startDay=first.getDay()===0?6:first.getDay()-1;
  const dim=new Date(cY,cM+1,0).getDate(),prev=new Date(cY,cM,0).getDate();
  for(let i=startDay-1;i>=0;i--)g.innerHTML+=`<div class="cal-day empty">${prev-i}</div>`;
  for(let d=1;d<=dim;d++){
    const isToday=d===today.getDate()&&cM===today.getMonth()&&cY===today.getFullYear();
    const dateStr=cY+'-'+String(cM+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
    const hasRdv=rdvDates.includes(dateStr);
    g.innerHTML+=`<div class="cal-day${isToday?' today':''}${hasRdv?' has-rdv':''}">${d}</div>`;
  }
}
document.getElementById('calPrev').addEventListener('click',()=>{cM--;if(cM<0){cM=11;cY--;}renderCal()});
document.getElementById('calNext').addEventListener('click',()=>{cM++;if(cM>11){cM=0;cY++;}renderCal()});
renderCal();

// Graphique activité
new Chart(document.getElementById('chartActivite').getContext('2d'),{
  type:'bar',data:{labels:<?=json_encode($activiteMois)?>,datasets:[{label:'Consultations',data:<?=json_encode($activiteVals)?>,backgroundColor:'rgba(37,99,235,.15)',borderColor:'#2563eb',borderWidth:2,borderRadius:6}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'#f0f4ff'}},x:{grid:{display:false}}}}
});

// Graphique factures
const nbP=<?=$patientId?qry($pdo,"SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='payee'",[$patientId]):0?>;
const nbI=<?=$nbImpayees?>;
const nbPart=<?=$patientId?qry($pdo,"SELECT COUNT(*) FROM factures WHERE patient_id=? AND statut='partielle'",[$patientId]):0?>;
new Chart(document.getElementById('chartFactures').getContext('2d'),{
  type:'doughnut',data:{labels:['Payées','Impayées','Partielles'],datasets:[{data:[nbP,nbI,nbPart],backgroundColor:['#059669','#dc2626','#d97706'],borderWidth:0}]},
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:10}}},cutout:'60%'}
});

// RDV
function saveRDV(){
  const alertEl=document.getElementById('rdvAlert');
  const med=document.getElementById('rdvMedecin').value,date=document.getElementById('rdvDate').value;
  alertEl.className='alert-msg';
  if(!med||!date){alertEl.textContent='Veuillez remplir tous les champs.';alertEl.classList.add('show','alert-error');return;}
  const btn=document.getElementById('btnRDV');btn.disabled=true;btn.innerHTML='<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span>';
  fetch('../api/rendez-vous.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create',medecin_id:med,date_rdv:date,motif:document.getElementById('rdvMotif').value})})
  .then(r=>r.json()).then(data=>{
    if(data.success){alertEl.textContent='Rendez-vous enregistré !';alertEl.classList.add('show','alert-success');setTimeout(()=>{closeModal('modalRDV');location.reload()},1200);}
    else{alertEl.textContent=data.message;alertEl.classList.add('show','alert-error');btn.disabled=false;btn.innerHTML='<span class="material-icons">event</span> Confirmer';}
  });
}

// ── Init Notifications & Messages ──
KlinikNotifications.init();
KlinikMessages.init();
</script>
</body>
</html>
