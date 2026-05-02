<?php
// ═══════════════════════════════════════
//  KLINIK — API Paiements Mobile Money
//  Actions: initier, verifier, webhook
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Configuration des providers (à remplacer par vos clés réelles)
define('MOBILE_MONEY_CONFIG', [
    'wave' => [
        'api_url'    => 'https://api.wave.com/v1/checkout/sessions',
        'api_key'    => 'WAVE_API_KEY_HERE',
        'secret'     => 'WAVE_SECRET_HERE',
        'webhook_secret' => 'WAVE_WEBHOOK_SECRET',
    ],
    'orange_money' => [
        'api_url'    => 'https://api.orange.com/orange-money-webpay/dev/v1/webpayment',
        'api_key'    => 'ORANGE_API_KEY_HERE',
        'secret'     => 'ORANGE_SECRET_HERE',
        'merchant_key' => 'ORANGE_MERCHANT_KEY',
    ],
    'mtn_momo' => [
        'api_url'    => 'https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay',
        'api_key'    => 'MTN_API_KEY_HERE',
        'subscription_key' => 'MTN_SUB_KEY',
        'api_user'   => 'MTN_USER',
    ],
    'moov_money' => [
        'api_url'    => 'https://api.moov-africa.com/payment',
        'api_key'    => 'MOOV_API_KEY_HERE',
        'secret'     => 'MOOV_SECRET_HERE',
    ],
]);

header('Content-Type: application/json; charset=utf-8');
$input=json_decode(file_get_contents('php://input'),true)??[];
$action=$input['action']??($_GET['action']??'');

try {
    $pdo=getDB();

    // ═══════════════════════════════
    //  INITIER un paiement Mobile Money
    // ═══════════════════════════════
    if($action==='initier'){
        checkAuth(['caissier','admin','patient']);
        $user=getUser();
        $factureId=(int)($input['facture_id']??0);
        $provider=cleanInput($input['provider']??'');
        $telephone=cleanInput($input['telephone']??'');
        $montant=(float)($input['montant']??0);

        if(!$factureId||!$provider||!$telephone||$montant<=0){
            jsonResponse(false,'Facture, provider, téléphone et montant requis.');
        }
        if(!in_array($provider,['wave','orange_money','mtn_momo','moov_money'])){
            jsonResponse(false,'Provider invalide.');
        }
        // Vérifier facture
        $s=$pdo->prepare("SELECT * FROM factures WHERE id=?");$s->execute([$factureId]);
        $facture=$s->fetch();
        if(!$facture)jsonResponse(false,'Facture introuvable.');
        if($facture['statut']==='payee')jsonResponse(false,'Facture déjà payée.');

        // Créer l'entrée de paiement mobile
        $txId='KLK-'.strtoupper($provider).'-'.time().'-'.rand(1000,9999);
        $stmt=$pdo->prepare("INSERT INTO paiements_mobile(facture_id,provider,telephone,montant,transaction_id,statut)VALUES(?,?,?,?,?,'initie')");
        $stmt->execute([$factureId,$provider,$telephone,$montant,$txId]);
        $pmId=(int)$pdo->lastInsertId();

        // Simuler l'appel API (mode mock/sandbox)
        $apiResponse = simulateProviderCall($provider,$telephone,$montant,$txId);

        // Mettre à jour le statut
        $newStatut=$apiResponse['success']?'en_cours':'echec';
        $pdo->prepare("UPDATE paiements_mobile SET statut=?,webhook_data=? WHERE id=?")
            ->execute([$newStatut,json_encode($apiResponse),$pmId]);

        if(!$apiResponse['success']){
            logError('PAIEMENT','Échec initiation '.$provider,['facture'=>$factureId,'error'=>$apiResponse['error']??'']);
            jsonResponse(false,'Échec de l\'initiation du paiement: '.($apiResponse['error']??'Erreur provider.'));
        }

        logError('PAIEMENT','Paiement initié',['provider'=>$provider,'facture'=>$factureId,'tx'=>$txId]);
        jsonResponse(true,'Paiement initié. Confirmez sur votre téléphone.',['transaction_id'=>$txId,'pm_id'=>$pmId]);
    }

    // ═══════════════════════════════
    //  VÉRIFIER le statut
    // ═══════════════════════════════
    if($action==='verifier'){
        checkAuth();
        $txId=cleanInput($input['transaction_id']??$_GET['transaction_id']??'');
        if(!$txId)jsonResponse(false,'Transaction ID requis.');
        $s=$pdo->prepare("SELECT * FROM paiements_mobile WHERE transaction_id=?");$s->execute([$txId]);
        $pm=$s->fetch();
        if(!$pm)jsonResponse(false,'Transaction introuvable.');
        jsonResponse(true,'OK',['statut'=>$pm['statut'],'provider'=>$pm['provider'],'montant'=>$pm['montant']]);
    }

    jsonResponse(false,'Action non reconnue.');
}catch(PDOException $e){
    logError('PAIEMENT','Erreur: '.$e->getMessage());
    jsonResponse(false,'Erreur serveur.');
}

/**
 * Simulation d'appel API provider (à remplacer par les vrais appels)
 */
function simulateProviderCall(string $provider, string $phone, float $amount, string $txId): array {
    // En production, remplacez par les vrais appels cURL vers les APIs
    // Pour l'instant, simulation réussie dans 90% des cas
    $success = rand(1,10) <= 9;
    return [
        'success' => $success,
        'provider' => $provider,
        'transaction_id' => $txId,
        'phone' => $phone,
        'amount' => $amount,
        'status' => $success ? 'pending' : 'failed',
        'error' => $success ? null : 'Simulation: échec aléatoire',
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}
