<?php
// ═══════════════════════════════════════
//  KLINIK — API Messagerie
//  Actions: send, inbox, thread, markRead, count
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';
checkAuth();
header('Content-Type: application/json; charset=utf-8');
$input=json_decode(file_get_contents('php://input'),true)??[];
$action=$input['action']??($_GET['action']??'');
try {
    $pdo=getDB();$user=getUser();$uid=(int)$user['id'];

    // Envoyer un message
    if($action==='send'){
        $destId=(int)($input['destinataire_id']??0);
        $sujet=cleanInput($input['sujet']??'');
        $contenu=cleanInput($input['contenu']??'');
        $parentId=!empty($input['parent_id'])?(int)$input['parent_id']:null;
        if(!$destId||!$contenu)jsonResponse(false,'Destinataire et message requis.');
        if($destId===$uid)jsonResponse(false,'Vous ne pouvez pas vous envoyer un message.');
        // Vérifier destinataire
        $s=$pdo->prepare("SELECT id FROM utilisateurs WHERE id=? AND actif=1");$s->execute([$destId]);
        if(!$s->fetch())jsonResponse(false,'Destinataire introuvable.');
        $stmt=$pdo->prepare("INSERT INTO messages(expediteur_id,destinataire_id,sujet,contenu,parent_id)VALUES(?,?,?,?,?)");
        $stmt->execute([$uid,$destId,$sujet,$contenu,$parentId]);
        $msgId=(int)$pdo->lastInsertId();
        // Notification
        $nom=$user['prenom'].' '.$user['nom'];
        createNotification($pdo,$destId,'Nouveau message',"Message de {$nom}: ".mb_substr($contenu,0,50).'...','info',null);
        jsonResponse(true,'Message envoyé.',['message_id'=>$msgId]);
    }

    // Boîte de réception
    if($action==='inbox'){
        $stmt=$pdo->prepare("
            SELECT m.*, u.nom AS exp_nom, u.prenom AS exp_prenom, u.role AS exp_role
            FROM messages m JOIN utilisateurs u ON m.expediteur_id=u.id
            WHERE m.destinataire_id=? AND m.parent_id IS NULL
            ORDER BY m.created_at DESC LIMIT 50
        ");
        $stmt->execute([$uid]);
        jsonResponse(true,'OK',['messages'=>$stmt->fetchAll()]);
    }

    // Messages envoyés
    if($action==='sent'){
        $stmt=$pdo->prepare("
            SELECT m.*, u.nom AS dest_nom, u.prenom AS dest_prenom
            FROM messages m JOIN utilisateurs u ON m.destinataire_id=u.id
            WHERE m.expediteur_id=? AND m.parent_id IS NULL
            ORDER BY m.created_at DESC LIMIT 50
        ");
        $stmt->execute([$uid]);
        jsonResponse(true,'OK',['messages'=>$stmt->fetchAll()]);
    }

    // Thread (fil de discussion)
    if($action==='thread'){
        $parentId=(int)($input['parent_id']??$_GET['parent_id']??0);
        if(!$parentId)jsonResponse(false,'ID du message parent requis.');
        // Vérifier accès
        $s=$pdo->prepare("SELECT * FROM messages WHERE id=? AND (expediteur_id=? OR destinataire_id=?)");
        $s->execute([$parentId,$uid,$uid]);
        if(!$s->fetch())jsonResponse(false,'Message introuvable.');
        $stmt=$pdo->prepare("
            SELECT m.*, u.nom AS exp_nom, u.prenom AS exp_prenom
            FROM messages m JOIN utilisateurs u ON m.expediteur_id=u.id
            WHERE m.id=? OR m.parent_id=?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$parentId,$parentId]);
        jsonResponse(true,'OK',['messages'=>$stmt->fetchAll()]);
    }

    // Marquer comme lu
    if($action==='markRead'){
        $id=(int)($input['id']??0);
        if(!$id)jsonResponse(false,'ID requis.');
        $pdo->prepare("UPDATE messages SET lu=1 WHERE id=? AND destinataire_id=?")->execute([$id,$uid]);
        jsonResponse(true,'Message lu.');
    }

    // Compter non-lus
    if($action==='count'){
        $s=$pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id=? AND lu=0");
        $s->execute([$uid]);
        jsonResponse(true,'OK',['count'=>(int)$s->fetchColumn()]);
    }

    // Liste des utilisateurs contactables
    if($action==='contacts'){
        $stmt=$pdo->prepare("SELECT id,nom,prenom,role FROM utilisateurs WHERE id!=? AND actif=1 ORDER BY nom");
        $stmt->execute([$uid]);
        jsonResponse(true,'OK',['contacts'=>$stmt->fetchAll()]);
    }

    jsonResponse(false,'Action non reconnue.');
}catch(PDOException $e){
    logError('MESSAGE','Erreur: '.$e->getMessage());
    jsonResponse(false,'Erreur serveur.');
}
