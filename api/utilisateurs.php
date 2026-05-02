<?php
// ═══════════════════════════════════════
//  KLINIK — API Utilisateurs (Admin)
//  Actions: create, update, toggle, delete
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';
checkAuth(['admin']);
header('Content-Type: application/json; charset=utf-8');
$input=json_decode(file_get_contents('php://input'),true);
$action=$input['action']??'';
try {
    $pdo=getDB();

    // ── CRÉER ──
    if($action==='create'){
        $nom=cleanInput($input['nom']??'');
        $prenom=cleanInput($input['prenom']??'');
        $email=trim($input['email']??'');
        $role=cleanInput($input['role']??'');
        $password=$input['password']??'';
        $tel=cleanInput($input['telephone']??'');

        if(!$nom||!$prenom||!$email||!$role||!$password)
            jsonResponse(false,'Tous les champs obligatoires doivent être remplis.');
        if(!isValidEmail($email))jsonResponse(false,'Email invalide.');
        if(strlen($password)<6)jsonResponse(false,'Mot de passe trop court (6 caractères minimum).');
        $validRoles=['admin','medecin','patient','laborantin','caissier'];
        if(!in_array($role,$validRoles,true))jsonResponse(false,'Rôle invalide.');

        $stmt=$pdo->prepare("SELECT id FROM utilisateurs WHERE email=?");
        $stmt->execute([$email]);
        if($stmt->fetch())jsonResponse(false,'Cet email est déjà utilisé.');

        $pdo->beginTransaction();
        try {
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $stmt=$pdo->prepare("INSERT INTO utilisateurs(nom,prenom,email,mot_de_passe,role,telephone)VALUES(?,?,?,?,?,?)");
            $stmt->execute([$nom,$prenom,$email,$hash,$role,$tel?:null]);
            $userId=(int)$pdo->lastInsertId();

            if($role==='patient'){
                $pdo->prepare("INSERT INTO patients(utilisateur_id)VALUES(?)")->execute([$userId]);
            }elseif($role==='medecin'){
                $specialite=cleanInput($input['specialite']??'');
                $pdo->prepare("INSERT INTO medecins(utilisateur_id,specialite)VALUES(?,?)")->execute([$userId,$specialite?:null]);
            }

            createNotification($pdo,$userId,'Bienvenue sur KLINIK',
                'Votre compte a été créé par l\'administrateur.','info','dashboard.php');

            $pdo->commit();
        }catch(PDOException $e){$pdo->rollBack();throw $e;}
        jsonResponse(true,'Utilisateur créé avec succès.',['user_id'=>$userId]);
    }

    // ── MODIFIER ──
    if($action==='update'){
        $id=(int)($input['id']??0);
        $nom=cleanInput($input['nom']??'');
        $prenom=cleanInput($input['prenom']??'');
        $email=trim($input['email']??'');
        $role=cleanInput($input['role']??'');
        $tel=cleanInput($input['telephone']??'');
        $pwd=$input['password']??null;

        if(!$id||!$nom||!$prenom||!$email||!$role)jsonResponse(false,'Champs obligatoires manquants.');
        if(!isValidEmail($email))jsonResponse(false,'Email invalide.');

        $stmt=$pdo->prepare("SELECT id FROM utilisateurs WHERE email=? AND id!=?");
        $stmt->execute([$email,$id]);
        if($stmt->fetch())jsonResponse(false,'Email déjà utilisé par un autre compte.');

        if($pwd){
            if(strlen($pwd)<6)jsonResponse(false,'Mot de passe trop court.');
            $hash=password_hash($pwd,PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,role=?,telephone=?,mot_de_passe=?,updated_at=NOW() WHERE id=?")
                ->execute([$nom,$prenom,$email,$role,$tel?:null,$hash,$id]);
        }else{
            $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,role=?,telephone=?,updated_at=NOW() WHERE id=?")
                ->execute([$nom,$prenom,$email,$role,$tel?:null,$id]);
        }
        jsonResponse(true,'Utilisateur modifié avec succès.');
    }

    // ── TOGGLE ACTIF ──
    if($action==='toggle'){
        $id=(int)($input['id']??0);$actif=(int)($input['actif']??0);
        if(!$id)jsonResponse(false,'ID manquant.');
        $session=getUser();
        if($id===(int)$session['id'])jsonResponse(false,'Vous ne pouvez pas vous désactiver vous-même.');
        $pdo->prepare("UPDATE utilisateurs SET actif=?,updated_at=NOW() WHERE id=?")->execute([$actif,$id]);
        jsonResponse(true,$actif?'Compte activé.':'Compte désactivé.');
    }

    // ── SUPPRIMER ──
    if($action==='delete'){
        $id=(int)($input['id']??0);
        if(!$id)jsonResponse(false,'ID manquant.');
        $session=getUser();
        if($id===(int)$session['id'])jsonResponse(false,'Vous ne pouvez pas supprimer votre propre compte.');
        $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$id]);
        jsonResponse(true,'Utilisateur supprimé.');
    }

    jsonResponse(false,'Action non reconnue.');
}catch(PDOException $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    logError('UTILISATEUR','Erreur: '.$e->getMessage());
    jsonResponse(false,APP_ENV==='development'?'Erreur: '.$e->getMessage():'Erreur serveur.');
}
