<?php
require_once '../../includes/check_auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkAuth(['patient', 'admin', 'caissier']);

$pdo = getDB();
$user = getUser();
$isAdminOrCaissier = in_array($user['role'], ['admin', 'caissier']);

$txId = $_GET['tx'] ?? null;
$pmId = $_GET['id'] ?? null;

if (!$txId && !$pmId) {
    die("Référence manquante.");
}

$where = $txId ? "p.transaction_id = ?" : "p.id = ?";
$param = $txId ? $txId : $pmId;

$stmt = $pdo->prepare("
  SELECT p.*, f.montant_total, f.date_facture, 
         pt.id as patient_id, up.nom as patient_nom, up.prenom as patient_prenom, up.telephone as patient_phone, up.email as patient_email
  FROM paiements_mobile p
  JOIN factures f ON p.facture_id = f.id
  JOIN patients pt ON f.patient_id = pt.id
  JOIN utilisateurs up ON pt.utilisateur_id = up.id
  WHERE $where
");
$stmt->execute([$param]);
$paiement = $stmt->fetch();

if (!$paiement) {
    die("Paiement introuvable.");
}

// Vérifier les permissions
if (!$isAdminOrCaissier) {
    $stmtP = $pdo->prepare("SELECT id FROM patients WHERE utilisateur_id = ?");
    $stmtP->execute([$user['id']]);
    $pid = $stmtP->fetchColumn();
    if ($paiement['patient_id'] != $pid) {
        die("Accès refusé. Ce reçu ne vous appartient pas.");
    }
}

$opColors = [
    'wave' => '#0369a1',
    'orange_money' => '#c2410c',
    'mtn_momo' => '#a16207',
    'moov_money' => '#1e3a8a',
    'cash' => '#065f46'
];
$opLabel = ucfirst(str_replace('_', ' ', $paiement['provider']));
$opColor = $opColors[strtolower($paiement['provider'])] ?? '#333';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu KLINIK - <?= htmlspecialchars($paiement['transaction_id'] ?? 'N/A') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Source Sans 3', sans-serif; background: #eef0f6; margin: 0; padding: 40px; color: #1a3a6e; }
        .receipt-card { max-width: 800px; margin: 0 auto; background: #fff; padding: 50px; border-radius: 12px; box-shadow: 0 10px 30px rgba(26,58,110,0.1); position: relative; overflow: hidden; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-family: 'Oswald', sans-serif; font-size: 120px; color: rgba(26,58,110,0.03); white-space: nowrap; pointer-events: none; }
        
        .r-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f1f5f9; padding-bottom: 30px; margin-bottom: 30px; }
        .r-logo { display: flex; align-items: center; gap: 12px; }
        .r-logo img { width: 50px; height: 50px; }
        .r-logo h1 { font-family: 'Oswald', sans-serif; margin: 0; font-size: 1.8rem; color: #1a3a6e; }
        
        .r-title { text-align: right; }
        .r-title h2 { font-family: 'Oswald', sans-serif; margin: 0 0 5px 0; font-size: 1.5rem; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
        .r-title p { margin: 0; color: #94a3b8; font-family: monospace; font-size: .9rem; }
        
        .r-details { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        .info-block h3 { font-family: 'Oswald', sans-serif; font-size: .9rem; color: #94a3b8; text-transform: uppercase; margin: 0 0 10px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; }
        .info-block p { margin: 5px 0; font-size: .95rem; font-weight: 600; }
        .info-block p span { font-weight: 400; color: #64748b; display: inline-block; width: 100px; }
        
        .r-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .r-table th { text-align: left; background: #f8fafc; padding: 12px 15px; font-family: 'Oswald', sans-serif; text-transform: uppercase; color: #64748b; font-size: .85rem; border-bottom: 2px solid #e2e8f0; }
        .r-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: .95rem; font-weight: 600; }
        
        .r-total { display: flex; justify-content: flex-end; }
        .total-box { background: #f8fafc; padding: 20px 30px; border-radius: 8px; text-align: right; min-width: 250px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: .95rem; color: #64748b; }
        .total-final { display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px solid #e2e8f0; font-family: 'Oswald', sans-serif; font-size: 1.5rem; color: #1a3a6e; }
        
        .r-footer { margin-top: 50px; text-align: center; color: #94a3b8; font-size: .8rem; }
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: .8rem; font-weight: 700; background: #d1fae5; color: #065f46; text-transform: uppercase; margin-top: 10px; }
        .status-echec { background: #fee2e2; color: #b91c1c; }
        .status-attente { background: #fef3c7; color: #b45309; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-card { box-shadow: none; max-width: 100%; padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div style="text-align:center;margin-bottom:20px" class="no-print">
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-family:'Oswald';font-size:1rem;cursor:pointer;box-shadow:0 4px 6px rgba(37,99,235,0.2)">🖨️ Imprimer / Sauvegarder PDF</button>
</div>

<div class="receipt-card">
    <div class="watermark"><?= $paiement['statut'] === 'succes' ? 'PAYÉ' : strtoupper($paiement['statut']) ?></div>
    
    <div class="r-header">
        <div class="r-logo">
            <img src="../../assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none'">
            <h1>KLINIK</h1>
        </div>
        <div class="r-title">
            <h2>Reçu de Paiement</h2>
            <p>RÉF: <?= htmlspecialchars($paiement['transaction_id'] ?? 'ATTENTE') ?></p>
            <p>Date: <?= date('d/m/Y H:i', strtotime($paiement['created_at'])) ?></p>
            <?php if($paiement['statut'] === 'succes'): ?>
                <div class="status-badge">Confirmé</div>
            <?php elseif($paiement['statut'] === 'echec'): ?>
                <div class="status-badge status-echec">Échoué</div>
            <?php else: ?>
                <div class="status-badge status-attente">En attente</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="r-details">
        <div class="info-block">
            <h3>Facturé à</h3>
            <p><span>Patient:</span> <?= htmlspecialchars(strtoupper($paiement['patient_nom']) . ' ' . $paiement['patient_prenom']) ?></p>
            <p><span>ID Patient:</span> #<?= str_pad($paiement['patient_id'], 5, '0', STR_PAD_LEFT) ?></p>
            <p><span>Téléphone:</span> <?= htmlspecialchars($paiement['patient_phone'] ?: 'N/A') ?></p>
            <p><span>Email:</span> <?= htmlspecialchars($paiement['patient_email'] ?: 'N/A') ?></p>
        </div>
        <div class="info-block">
            <h3>Détails du Paiement</h3>
            <p><span>Opérateur:</span> <b style="color:<?= $opColor ?>"><?= $opLabel ?></b></p>
            <p><span>Tél Paiement:</span> <?= htmlspecialchars($paiement['telephone']) ?></p>
            <p><span>Facture Liée:</span> #<?= str_pad($paiement['facture_id'], 5, '0', STR_PAD_LEFT) ?></p>
        </div>
    </div>
    
    <table class="r-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align:right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Règlement de la facture médicale #<?= str_pad($paiement['facture_id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td style="text-align:right"><?= number_format($paiement['montant'], 0, ',', ' ') ?> FCFA</td>
            </tr>
        </tbody>
    </table>
    
    <div class="r-total">
        <div class="total-box">
            <div class="total-row">
                <span>Sous-total</span>
                <span><?= number_format($paiement['montant'], 0, ',', ' ') ?> FCFA</span>
            </div>
            <div class="total-row">
                <span>Frais de transaction</span>
                <span>0 FCFA</span>
            </div>
            <div class="total-final">
                <span>TOTAL PAYÉ</span>
                <span><?= number_format($paiement['montant'], 0, ',', ' ') ?> FCFA</span>
            </div>
        </div>
    </div>
    
    <div class="r-footer">
        <p>Merci de votre confiance. Ce reçu est généré électroniquement et tient lieu de justificatif de paiement officiel.</p>
        <p>KLINIK S.A - Abidjan, Côte d'Ivoire - Contact: +225 00 00 00 00 00</p>
    </div>
</div>

<script>
    // Auto-lancement de la fenêtre d'impression
    window.onload = function() {
        setTimeout(() => {
            window.print();
        }, 500);
    };
</script>
</body>
</html>
