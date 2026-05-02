<?php
// ═══════════════════════════════════════
//  KLINIK — API Examens
//  Actions: demander, prendreEnCharge, transmettre
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';
checkAuth(['laborantin','medecin','admin']);
header('Content-Type: application/json; charset=utf-8');
$input=json_decode(file_get_contents('php://input'),true);
$action=$input['action']??($_GET['action']??'');
try {
    $pdo=getDB();$user=getUser();

    if($action==='demander'){
        if(!in_array($user['role'],['medecin','admin']))jsonResponse(false,'Réservé aux médecins.');
        $cid=(int)($input['consultation_id']??0);$type=cleanInput($input['type_examen']??'');
        $prio=cleanInput($input['priorite']??'normale');
        if(!$cid||!$type)jsonResponse(false,'Données manquantes.');
        if(!in_array($prio,['normale','urgente']))$prio='normale';
        $s=$pdo->prepare("SELECT id FROM consultations WHERE id=?");$s->execute([$cid]);
        if(!$s->fetch())jsonResponse(false,'Consultation introuvable.');
        $pdo->prepare("INSERT INTO examens(consultation_id,type_examen,priorite)VALUES(?,?,?)")->execute([$cid,$type,$prio]);
        $eid=(int)$pdo->lastInsertId();
        notifyRole($pdo,'laborantin','Nouvel examen'.($prio==='urgente'?' URGENT':''),
            "Examen \"{$type}\" à traiter.",$prio==='urgente'?'danger':'info','examens-en-attente.php');
        jsonResponse(true,'Examen demandé.',['examen_id'=>$eid]);
    }

    if($action==='prendreEnCharge'){
        if($user['role']!=='laborantin')jsonResponse(false,'Réservé aux laborantins.');
        $id=(int)($input['examen_id']??0);if(!$id)jsonResponse(false,'ID manquant.');
        $s=$pdo->prepare("SELECT statut FROM examens WHERE id=?");$s->execute([$id]);$ex=$s->fetch();
        if(!$ex)jsonResponse(false,'Examen introuvable.');
        if($ex['statut']!=='en_attente')jsonResponse(false,'Déjà pris en charge.');
        $pdo->prepare("UPDATE examens SET statut='en_cours',laborantin_id=? WHERE id=? AND statut='en_attente'")->execute([$user['id'],$id]);
        jsonResponse(true,'Pris en charge.');
    }

    if($action==='transmettre'){
        if($user['role']!=='laborantin')jsonResponse(false,'Réservé aux laborantins.');
        $id=(int)($input['examen_id']??0);$res=cleanInput($input['resultat']??'');
        if(!$id||!$res)jsonResponse(false,'Données manquantes.');
        $s=$pdo->prepare("SELECT * FROM examens WHERE id=?");$s->execute([$id]);$ex=$s->fetch();
        if(!$ex)jsonResponse(false,'Examen introuvable.');
        if($ex['statut']==='transmis')jsonResponse(false,'Déjà transmis.');
        $pdo->prepare("UPDATE examens SET resultat=?,statut='transmis',laborantin_id=?,date_resultat=NOW() WHERE id=?")->execute([$res,$user['id'],$id]);
        // Notify patient
        $s=$pdo->prepare("SELECT p.utilisateur_id FROM consultations c JOIN patients p ON c.patient_id=p.id WHERE c.id=?");
        $s->execute([$ex['consultation_id']]);$puid=$s->fetchColumn();
        if($puid)createNotification($pdo,(int)$puid,'Résultat disponible',"Résultat de \"{$ex['type_examen']}\" disponible.",'success','mes-examens.php');
        jsonResponse(true,'Résultat transmis.');
    }

    jsonResponse(false,'Action non reconnue.');
}catch(PDOException $e){
    logError('EXAMEN','Erreur: '.$e->getMessage());
    jsonResponse(false,APP_ENV==='development'?'Erreur: '.$e->getMessage():'Erreur serveur.');
}
