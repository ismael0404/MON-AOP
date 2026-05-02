<?php
// ═══════════════════════════════════════
//  KLINIK — API Notifications
//  Actions: fetch, count, markRead, markAllRead
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

    // Récupérer les notifications
    if($action==='fetch'){
        $limit=(int)($input['limit']??$_GET['limit']??20);
        if($limit<1||$limit>100)$limit=20;
        $stmt=$pdo->prepare("SELECT * FROM notifications WHERE utilisateur_id=? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$uid,$limit]);
        jsonResponse(true,'OK',['notifications'=>$stmt->fetchAll()]);
    }

    // Compter les non-lues
    if($action==='count'){
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE utilisateur_id=? AND lue=0");
        $stmt->execute([$uid]);
        jsonResponse(true,'OK',['count'=>(int)$stmt->fetchColumn()]);
    }

    // Marquer une notification comme lue
    if($action==='markRead'){
        $id=(int)($input['id']??0);
        if(!$id)jsonResponse(false,'ID requis.');
        $pdo->prepare("UPDATE notifications SET lue=1 WHERE id=? AND utilisateur_id=?")->execute([$id,$uid]);
        jsonResponse(true,'Notification marquée comme lue.');
    }

    // Marquer toutes comme lues
    if($action==='markAllRead'){
        $pdo->prepare("UPDATE notifications SET lue=1 WHERE utilisateur_id=? AND lue=0")->execute([$uid]);
        jsonResponse(true,'Toutes les notifications marquées comme lues.');
    }

    jsonResponse(false,'Action non reconnue.');
}catch(PDOException $e){
    logError('NOTIFICATION','Erreur: '.$e->getMessage());
    jsonResponse(false,'Erreur serveur.');
}
