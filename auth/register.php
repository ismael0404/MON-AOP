<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
 
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
 
<title>KLINIK — Inscription</title>
 
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Source+Sans+3:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
 
<style>
 
:root{
--blue:#1a3a6e;
--blue-bright:#2563eb;
}
 
*{
margin:0;
padding:0;
box-sizing:border-box;
}
 
body{
font-family:'Source Sans 3',sans-serif;
height:100vh;
display:flex;
align-items:center;
justify-content:center;
background:url('../assets/img/login-bg.jpg') center/cover no-repeat;
position:relative;
padding:20px;
}
 
body::before{
content:"";
position:absolute;
top:0;
left:0;
width:100%;
height:100%;
background:linear-gradient(135deg,rgba(10,25,55,0.85),rgba(10,25,55,0.6));
z-index:-1;
}
 
.auth-wrap{
display:flex;
width:100%;
max-width:950px;
min-height:560px;
border-radius:20px;
overflow:hidden;
box-shadow:0 20px 60px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.05) inset;
animation:cardEntrance .9s cubic-bezier(.22,.68,0,1.71);
transition:transform .3s ease;
}
 
.auth-wrap:hover{
transform:translateY(-3px);
}
 
@keyframes cardEntrance{
0%{opacity:0;transform:translateY(40px) scale(.96);}
100%{opacity:1;transform:translateY(0) scale(1);}
}
 
/* LEFT */
 
.auth-left{
flex:1;
background:rgba(10,25,55,0.35);
backdrop-filter:blur(16px);
padding:55px 45px;
display:flex;
flex-direction:column;
justify-content:space-between;
color:white;
border:1px solid rgba(255,255,255,.08);
}
 
.auth-logo{
height:38px;
display:flex;
align-items:center;
}
 
.auth-logo img{
height:38px;
width:auto;
display:block;
}
 
/* Texte centré verticalement comme le login */
.auth-left-body{
flex:1;
display:flex;
flex-direction:column;
justify-content:center;
padding:40px 0;
}
 
.auth-left h1{
font-family:'Oswald',sans-serif;
font-size:2.5rem;
line-height:1.1;
margin-bottom:20px;
}
 
.auth-left span{
color:rgba(255,255,255,0.6);
}
 
.auth-left p{
font-size:0.95rem;
line-height:1.7;
color:rgba(255,255,255,0.7);
max-width:280px;
}
 
.auth-left-footer{
font-size:12px;
opacity:0.4;
}
 
/* RIGHT */
 
.auth-right{
width:420px;
background:rgba(10,25,55,0.35);
backdrop-filter:blur(16px);
padding:55px 45px;
display:flex;
flex-direction:column;
justify-content:center;
color:white;
border:1px solid rgba(255,255,255,.08);
}
 
.auth-title{
font-family:'Oswald',sans-serif;
font-size:1.8rem;
margin-bottom:6px;
}
 
.auth-sub{
font-size:13px;
opacity:.6;
margin-bottom:25px;
}
 
/* INPUT */
 
.form-group{
margin-bottom:18px;
}
 
.form-group label{
font-size:11px;
letter-spacing:2px;
text-transform:uppercase;
margin-bottom:8px;
display:block;
opacity:.6;
}
 
.input-wrap{
position:relative;
}
 
.input-wrap .material-icons{
position:absolute;
left:0;
top:50%;
transform:translateY(-50%);
font-size:18px;
opacity:.5;
}
 
.input-wrap input{
width:100%;
background:transparent;
border:none;
border-bottom:1.5px solid rgba(255,255,255,.5);
padding:12px 10px 12px 28px;
color:white;
font-size:15px;
outline:none;
transition:.25s;
}
 
.input-wrap input::placeholder{
color:rgba(255,255,255,.35);
}
 
.input-wrap input:focus{
border-bottom-color:white;
box-shadow:0 2px 0 rgba(255,255,255,.4);
}
 
.input-wrap input.error{
border-bottom-color:#f87171;
}
 
.input-wrap::after{
content:"";
position:absolute;
left:0;
bottom:-1px;
height:2px;
width:0%;
background:#2563eb;
transition:width .3s ease;
}
 
.input-wrap:focus-within::after{
width:100%;
}
 
.btn-submit{
width:100%;
padding:14px;
border:none;
border-radius:8px;
background:var(--blue);
color:white;
font-weight:700;
font-size:14px;
font-family:'Source Sans 3',sans-serif;
display:flex;
align-items:center;
justify-content:center;
gap:8px;
cursor:pointer;
margin-top:10px;
transition:.25s;
}
 
.btn-submit:hover{
background:var(--blue-bright);
transform:translateY(-2px) scale(1.01);
box-shadow:0 10px 25px rgba(37,99,235,.45);
}
 
.btn-submit:active{
transform:scale(.97);
}
 
.alert{
display:none;
padding:10px;
border-radius:6px;
margin-bottom:15px;
font-size:13px;
background:rgba(220,38,38,.2);
border:1px solid rgba(248,113,113,.4);
color:#fca5a5;
}
 
.alert.show{
display:block;
}
 
.card-footer{
margin-top:20px;
text-align:center;
font-size:13px;
opacity:.6;
}
 
.card-footer a{
color:white;
font-weight:600;
text-decoration:none;
}
 
.card-footer a:hover{
text-decoration:underline;
}
 
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
 
@media(max-width:720px){
.auth-left{display:none;}
.auth-wrap{max-width:420px;}
.auth-right{width:100%;}
}
 
</style>
</head>
 
<body>
 
<div class="auth-wrap">
 
  <!-- GAUCHE -->
  <div class="auth-left">
 
    <a href="../index.php" class="auth-logo">
      <img src="../assets/img/logo.png" alt="KLINIK">
    </a>
 
    <div class="auth-left-body">
      <h1>Centre Médical<br>KLINIK<br><span>Plateforme numérique</span></h1>
      <p>Créez votre compte pour accéder à la gestion des patients, consultations et services médicaux.</p>
    </div>
 
    <div class="auth-left-footer">© 2026 KLINIK — Tous droits réservés</div>
 
  </div>
 
  <!-- DROITE -->
  <div class="auth-right">
 
    <p class="auth-title">Créer un compte</p>
    <p class="auth-sub">Inscrivez-vous pour accéder à la plateforme</p>
 
    <div class="alert" id="alertBox"></div>
 
    <form id="registerForm" novalidate>
 
      <div class="form-group">
        <label>Nom complet</label>
        <div class="input-wrap">
          <span class="material-icons">person</span>
          <input type="text" id="nom" placeholder="Nom et prénom" required>
        </div>
      </div>
 
      <div class="form-group">
        <label>Adresse e-mail</label>
        <div class="input-wrap">
          <span class="material-icons">email</span>
          <input type="email" id="email" placeholder="votre@email.ci" required>
        </div>
      </div>
 
      <div class="form-group">
        <label>Mot de passe</label>
        <div class="input-wrap">
          <span class="material-icons">lock</span>
          <input type="password" id="password" placeholder="••••••••" required>
        </div>
      </div>
 
      <div class="form-group">
        <label>Confirmer le mot de passe</label>
        <div class="input-wrap">
          <span class="material-icons">lock</span>
          <input type="password" id="password2" placeholder="••••••••" required>
        </div>
      </div>
 
      <button class="btn-submit" id="registerBtn">
        <span class="material-icons">person_add</span>
        Créer mon compte
      </button>
 
    </form>
 
    <div class="card-footer">
      Déjà inscrit ? <a href="login.php">Se connecter</a>
    </div>
    <div class="card-footer" style="margin-top:10px;">
      <a href="../index.php" style="display:inline-flex;align-items:center;gap:4px;opacity:.6;font-size:12px;"><span class="material-icons" style="font-size:14px;">arrow_back</span> Retour à l'accueil</a>
    </div>
 
  </div>
 
</div>
 
<script>
document.getElementById('registerForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const nom      = document.getElementById('nom');
  const email    = document.getElementById('email');
  const pwd      = document.getElementById('password');
  const pwd2     = document.getElementById('password2');
  const alertBox = document.getElementById('alertBox');
  const btn      = document.getElementById('registerBtn');

  [nom, email, pwd, pwd2].forEach(el => el.classList.remove('error'));
  alertBox.classList.remove('show');

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  let valid = true;
  if (!nom.value.trim())             { nom.classList.add('error');   valid = false; }
  if (!emailRegex.test(email.value)) { email.classList.add('error'); valid = false; }
  if (pwd.value.length < 6)          { pwd.classList.add('error');   valid = false; }
  if (pwd.value !== pwd2.value)      { pwd2.classList.add('error');  valid = false; }

  if (!valid) {
    alertBox.textContent = pwd.value !== pwd2.value
      ? 'Les mots de passe ne correspondent pas.'
      : 'Veuillez remplir correctement tous les champs.';
    alertBox.classList.add('show');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span> Creation...';

  fetch('../api/auth.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'register', nom: nom.value.trim(), email: email.value.trim(), password: pwd.value })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      window.location.href = data.redirect;
    } else {
      alertBox.textContent = data.message;
      alertBox.classList.add('show');
      btn.disabled = false;
      btn.innerHTML = '<span class="material-icons">person_add</span> Creer mon compte';
    }
  })
  .catch(() => {
    alertBox.textContent = 'Erreur serveur. Reessayez.';
    alertBox.classList.add('show');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-icons">person_add</span> Creer mon compte';
  });
});
</script>
</body>
</html>