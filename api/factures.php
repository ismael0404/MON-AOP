<?php
// ═══════════════════════════════════════
//  KLINIK — API Factures & Paiements
//  Actions: create, encaisser
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';
checkAuth(['caissier','admin']);
header('Content-Type: application/json; charset=utf-8');
$input=json_decode(file_get_contents('php://input'),true);
$action=$input['action']??'';
try {
    $pdo=getDB();$user=getUser();

    if($action==='create'){
        $pid=(int)($input['patient_id']??0);
        $montant=(float)($input['montant_total']??0);
        $consultId=!empty($input['consultation_id'])?(int)$input['consultation_id']:null;
        if(!$pid||$montant<=0)jsonResponse(false,'Patient et montant requis.');
        // Vérifier patient
        $s=$pdo->prepare("SELECT id FROM patients WHERE id=?");$s->execute([$pid]);
        if(!$s->fetch())jsonResponse(false,'Patient introuvable.');
        // Vérifier consultation si fournie
        if($consultId){
            $s=$pdo->prepare("SELECT id FROM consultations WHERE id=?");$s->execute([$consultId]);
            if(!$s->fetch())jsonResponse(false,'Consultation introuvable.');
            // Vérifier pas de doublon
            $s=$pdo->prepare("SELECT id FROM factures WHERE consultation_id=?");$s->execute([$consultId]);
            if($s->fetch())jsonResponse(false,'Facture déjà existante pour cette consultation.');
        }
        $stmt=$pdo->prepare("INSERT INTO factures(patient_id,consultation_id,montant_total,caissier_id)VALUES(?,?,?,?)");
        $stmt->execute([$pid,$consultId,$montant,$user['id']]);
        $fid=(int)$pdo->lastInsertId();
        // Notification patient (trigger SQL le fait aussi)
        jsonResponse(true,'Facture créée.',['facture_id'=>$fid]);
    }

    if($action==='encaisser'){
        $fid=(int)($input['facture_id']??0);
        $montant=(float)($input['montant_paye']??0);
        $mode=cleanInput($input['mode_paiement']??'especes');
        $ref=cleanInput($input['reference']??'');
        if(!$fid||$montant<=0)jsonResponse(false,'Données invalides.');
        $validModes=['especes','carte','mobile_money','cheque'];
        if(!in_array($mode,$validModes))$mode='especes';
        // Vérifier la facture
        $f=$pdo->prepare("SELECT * FROM factures WHERE id=?");$f->execute([$fid]);$ft=$f->fetch();
        if(!$ft)jsonResponse(false,'Facture introuvable.');
        if($ft['statut']==='payee')jsonResponse(false,'Cette facture est déjà entièrement payée.');

        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO paiements(facture_id,caissier_id,montant_paye,mode_paiement,reference)VALUES(?,?,?,?,?)")
                ->execute([$fid,$user['id'],$montant,$mode,$ref]);
            $paiementId=(int)$pdo->lastInsertId();
            // Calculer le total payé
            $tp=$pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE facture_id=?");
            $tp->execute([$fid]);$totalPaye=(float)$tp->fetchColumn();
            $statut=$totalPaye>=$ft['montant_total']?'payee':'partielle';
            $pdo->prepare("UPDATE factures SET statut=? WHERE id=?")->execute([$statut,$fid]);
            // Notification patient
            $s=$pdo->prepare("SELECT utilisateur_id FROM patients WHERE id=?");
            $s->execute([$ft['patient_id']]);$puid=$s->fetchColumn();
            if($puid){
                $msg=$statut==='payee'
                    ?'Votre facture de '.formatMontant($ft['montant_total']).' est entièrement payée.'
                    :'Paiement de '.formatMontant($montant).' reçu sur votre facture.';
                createNotification($pdo,(int)$puid,'Paiement reçu',$msg,$statut==='payee'?'success':'info','mes-factures.php');
            }
            $pdo->commit();
        } catch(PDOException $e){$pdo->rollBack();throw $e;}
        jsonResponse(true,'Paiement enregistré.',['nouveau_statut'=>$statut,'paiement_id'=>$paiementId]);
    }

    jsonResponse(false,'Action non reconnue.');
}catch(PDOException $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    logError('FACTURE','Erreur: '.$e->getMessage());
    jsonResponse(false,APP_ENV==='development'?'Erreur: '.$e->getMessage():'Erreur serveur.');
}
