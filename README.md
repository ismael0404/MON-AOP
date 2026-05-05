# KLINIK — Système de Gestion Hospitalière

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.x-purple)
![MySQL](https://img.shields.io/badge/Database-MySQL-orange)

**KLINIK** est une plateforme moderne et intuitive de gestion hospitalière conçue pour simplifier les processus administratifs et médicaux. Elle permet une interaction fluide entre les différents acteurs d'un établissement de santé (Administrateurs, Médecins, Patients, Laborantins et Caissiers).

---

## 🚀 Fonctionnalités Principales

### 👨‍💼 Administration
- Gestion complète des utilisateurs et des rôles.
- Configuration des départements et services.
- Dashboard de statistiques en temps réel.

### 🩺 Médical & Consultation
- Prise de rendez-vous en ligne par les patients.
- Gestion des dossiers médicaux sécurisés.
- Saisie des diagnostics, prescriptions et observations.
- Édition d'ordonnances (e-ordonnance).

### 🔬 Laboratoire
- Demandes d'examens directement depuis la consultation.
- Transmission sécurisée des résultats de laboratoire.
- Notification automatique aux médecins lors de la disponibilité des résultats.

### 💰 Facturation & Paiement
- Génération automatique de factures après consultation.
- Support multi-paiement : Espèces, Carte, Chèque.
- Intégration Mobile Money (Wave, Orange, MTN, Moov).
- Suivi des règlements et impayés.

### 💬 Communication
- Système de messagerie interne entre personnels et patients.
- Centre de notifications temps réel (Succès, Info, Alertes).

---

## 🛠️ Stack Technique

- **Backend** : PHP 8.x (Architecture modulaire)
- **Base de données** : MySQL / MariaDB
- **Frontend** : HTML5, Vanilla CSS, Bootstrap 5.3
- **Design** : Google Fonts (Oswald, Source Sans), Material Icons
- **Sécurité** : Hachage de mot de passe Bcrypt, protection contre les injections SQL via PDO.

---

## ⚙️ Installation & Configuration

### Prérequis
- Un serveur local (XAMPP, WAMP, Laragon) avec PHP 8.0+.
- MySQL / MariaDB.

### Étapes d'installation
1. **Clonage / Copie** : Placez le dossier `MON AOP` dans votre répertoire `htdocs`.
2. **Base de données** :
   - Accédez à `phpMyAdmin`.
   - Créez une base de données nommée `klinik_db`.
   - Importez le fichier `klinik_db.sql` situé à la racine du projet.
3. **Configuration** : 
   - Vérifiez le fichier `config/database.php` pour ajuster les identifiants de connexion si nécessaire (`DB_USER`, `DB_PASSWORD`).
4. **Accès** : Ouvrez votre navigateur et rendez-vous sur `http://localhost/MON AOP/`.

---

## 🔑 Identifiants de Connexion (Test)

Tous les comptes de test utilisent le mot de passe par défaut : **`password`**

| Rôle | Email | Mot de passe |
| :--- | :--- | :--- |
| **Administrateur** | `admin@klinik.ci` | `password` |
| **Médecin** | `medecin@klinik.ci` | `password` |
| **Patient** | `patient@klinik.ci` | `password` |
| **Laborantin** | `labo@klinik.ci` | `password` |
| **Caissier** | `caisse@klinik.ci` | `password` |

---

## 📂 Structure du Projet

```text
MON AOP/
├── admin/          # Espace d'administration
├── api/            # Endpoints API (Paiements, RDV, Notifications)
├── assets/         # Images, JS, Libs tierces
├── auth/           # Login, Register, Logout
├── caissier/       # Interface de facturation et encaissement
├── config/         # Configuration BDD et constantes globales
├── includes/       # Composants réutilisables (Header, Sidebar, Auth checks)
├── laborantin/     # Gestion des examens de laboratoire
├── medecin/        # Consultations et suivi patient
├── modules/        # Modules métier (Paiements, Ordonnances)
├── notifications/  # Gestion des alertes
├── patient/        # Espace personnel patient
├── uploads/        # Documents et photos téléchargés
├── index.php       # Page d'accueil publique
├── style.css       # Design principal (AOP UI Style)
└── klinik_db.sql   # Script d'importation de la base de données
```

---

## 📝 Licence

Ce projet est réalisé dans le cadre académique. Tous droits réservés © 2026.
