<?php
// ═══════════════════════════════════════
//  KLINIK — API Dossiers Médicaux
//  Actions: get, update, history
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';
checkAuth(['medecin','admin','patient']);
header('Content-Type: application/json; charset=utf-8');
$input=json_decode(file_get_contents('php://input'),true)??[];
$action=$input['action']??($_GET['action']??'');
try {
    $pdo=getDB();$user=getUser();

    // Récupérer le dossier d'un patient
    if($action==='get'){
        $pid=(int)($input['patient_id']??$_GET['patient_id']??0);
        // Patient ne peut voir que son propre dossier
        if($user['role']==='patient'){$pid=$user['patient_id']??0;}
        if(!$pid)jsonResponse(false,'Patient ID requis.');
        
        $stmt=$pdo->prepare("
            SELECT p.*,u.nom,u.prenom,u.email,u.telephone,
                   um.nom AS med_nom,um.prenom AS med_prenom,m.specialite
            FROM patients p
            JOIN utilisateurs u ON p.utilisateur_id=u.id
            LEFT JOIN medecins m ON p.medecin_traitant_id=m.id
            LEFT JOIN utilisateurs um ON m.utilisateur_id=um.id
            WHERE p.id=?
        ");
        $stmt->execute([$pid]);$dossier=$stmt->fetch();
        if(!$dossier)jsonResponse(false,'Patient introuvable.');

        // Consultations
        $c=$pdo->prepare("SELECT c.*,um.nom AS med_nom,um.prenom AS med_prenom FROM consultations c JOIN medecins m ON c.medecin_id=m.id JOIN utilisateurs um ON m.utilisateur_id=um.id WHERE c.patient_id=? ORDER BY c.date_consult DESC LIMIT 20");
        $c->execute([$pid]);

        // Examens
        $e=$pdo->prepare("SELECT e.*,c.date_consult FROM examens e JOIN consultations c ON e.consultation_id=c.id WHERE c.patient_id=? ORDER BY e.date_demande DESC LIMIT 20");
        $e->execute([$pid]);

        // Factures
        $f=$pdo->prepare("SELECT * FROM factures WHERE patient_id=? ORDER BY date_facture DESC LIMIT 20");
        $f->execute([$pid]);

        jsonResponse(true,'OK',[
            'dossier'=>$dossier,
            'consultations'=>$c->fetchAll(),
            'examens'=>$e->fetchAll(),
            'factures'=>$f->fetchAll()
        ]);
    }

    // Mettre à jour le profil patient
    if($action==='update'){
        if(!in_array($user['role'],['medecin','admin','patient']))jsonResponse(false,'Non autorisé.');
        $pid=(int)($input['patient_id']??0);
        if($user['role']==='patient')$pid=$user['patient_id']??0;
        if(!$pid)jsonResponse(false,'Patient ID requis.');

        $fields=[];$params=[];
        $allowed=['date_naissance','sexe','groupe_sanguin','adresse','ville','medecin_traitant_id'];
        foreach($allowed as $f){
            if(isset($input[$f])){
                $fields[]="{$f}=?";
                $params[]=$input[$f]===''?null:cleanInput($input[$f]);
            }
        }
        if(empty($fields))jsonResponse(false,'Aucun champ à modifier.');
        $params[]=$pid;
        $pdo->prepare("UPDATE patients SET ".implode(',',$fields)." WHERE id=?")->execute($params);
        jsonResponse(true,'Dossier mis à jour.');
    }

    jsonResponse(false,'Action non reconnue.');
}catch(PDOException $e){
    logError('DOSSIER','Erreur: '.$e->getMessage());
    jsonResponse(false,'Erreur serveur.');
}
