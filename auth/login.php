<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
 
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
 
<title>KLINIK — Connexion</title>
 
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
max-width:920px;
min-height:520px;
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
 
/* Logo : affiche l'image si disponible, sinon rien */
.auth-left .auth-logo{
height:38px;
margin-bottom:40px;
display:flex;
align-items:center;
}
.auth-left .auth-logo img{
height:38px;
width:auto;
display:block;
}
a.auth-logo { text-decoration:none; transition:opacity .2s; }
a.auth-logo:hover { opacity:.75; }
 
.auth-left h1{
font-family:'Oswald',sans-serif;
font-size:2.6rem;
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
 
.auth-right{
width:400px;
background:rgba(10,25,55,0.35);
backdrop-filter:blur(16px);
padding:55px 45px;
display:flex;
flex-direction:column;
justify-content:center;
color:white;
border:1px solid rgba(255,255,255,.08);
}
 
.auth-welcome{
font-size:12px;
letter-spacing:2px;
text-transform:uppercase;
margin-bottom:6px;
opacity:.7;
}
 
.auth-title{
font-family:'Oswald',sans-serif;
font-size:1.7rem;
margin-bottom:4px;
}
 
.auth-sub{
font-size:13px;
opacity:.6;
margin-bottom:25px;
}
 
.form-group{
margin-bottom:20px;
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
transition:0.25s;
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
 
.pwd-toggle{
position:absolute;
right:0;
top:50%;
transform:translateY(-50%);
background:none;
border:none;
cursor:pointer;
color:white;
opacity:.6;
}
 
.auth-options{
display:flex;
justify-content:space-between;
font-size:13px;
margin-top:10px;
margin-bottom:25px;
}
 
.auth-options label{
display:flex;
gap:6px;
align-items:center;
cursor:pointer;
opacity:.7;
}
 
.auth-options a{
color:white;
text-decoration:none;
font-weight:600;
}
 
.auth-options a:hover{
text-decoration:underline;
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
 
    <div>
      <h1>Centre Médical<br>KLINIK<br><span>Plateforme numérique</span></h1>
      <p>Une solution moderne pour gérer les patients, les consultations et les services médicaux en toute simplicité et sécurité.</p>
    </div>
 
    <div class="auth-left-footer">© 2026 KLINIK — Tous droits réservés</div>
 
  </div>
 
  <!-- DROITE -->
  <div class="auth-right">
 
    <p class="auth-welcome">Bon retour</p>
    <p class="auth-title">Connexion</p>
    <p class="auth-sub">Accédez à votre espace personnel</p>
 
    <div class="alert" id="alertBox"></div>
 
    <form id="loginForm">
 
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
          <button type="button" class="pwd-toggle" id="togglePwd">
            <span class="material-icons" id="eyeIcon">visibility</span>
          </button>
        </div>
      </div>
 
      <div class="auth-options">
        <label><input type="checkbox"> Se souvenir de moi</label>
        <a href="#">Mot de passe oublié ?</a>
      </div>
 
      <button class="btn-submit" id="loginBtn">
        <span class="material-icons">lock_open</span>
        Se connecter
      </button>
 
      <p style="text-align:center;font-size:12px;margin-top:10px;opacity:.5;">
        Connexion sécurisée 🔒
      </p>
 
    </form>
 
    <div class="card-footer">
      Pas encore de compte ? <a href="register.php">Créer un compte</a>
    </div>
    <div class="card-footer" style="margin-top:10px;">
      <a href="../index.php" style="display:inline-flex;align-items:center;gap:4px;opacity:.6;font-size:12px;"><span class="material-icons" style="font-size:14px;">arrow_back</span> Retour à l'accueil</a>
    </div>
 
  </div>
 
</div>
 
<script>
const togglePwd = document.getElementById('togglePwd');
const password  = document.getElementById('password');
const eyeIcon   = document.getElementById('eyeIcon');

togglePwd.addEventListener('click', () => {
  password.type = password.type === 'password' ? 'text' : 'password';
  eyeIcon.textContent = password.type === 'password' ? 'visibility' : 'visibility_off';
});

document.getElementById('loginForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const email    = document.getElementById('email');
  const pwd      = document.getElementById('password');
  const alertBox = document.getElementById('alertBox');
  const btn      = document.getElementById('loginBtn');

  email.classList.remove('error');
  pwd.classList.remove('error');
  alertBox.classList.remove('show');

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  let valid = true;
  if (!emailRegex.test(email.value)) { email.classList.add('error'); valid = false; }
  if (!pwd.value)                    { pwd.classList.add('error');   valid = false; }
  if (!valid) {
    alertBox.textContent = 'Veuillez remplir correctement les champs.';
    alertBox.classList.add('show');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="material-icons" style="animation:spin .8s linear infinite">refresh</span> Connexion...';

  fetch('../api/auth.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'login', email: email.value.trim(), password: pwd.value })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      window.location.href = data.redirect;
    } else {
      alertBox.textContent = data.message;
      alertBox.classList.add('show');
      btn.disabled = false;
      btn.innerHTML = '<span class="material-icons">lock_open</span> Se connecter';
    }
  })
  .catch(() => {
    alertBox.textContent = 'Erreur serveur. Reessayez.';
    alertBox.classList.add('show');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-icons">lock_open</span> Se connecter';
  });
});
</script>
</body>
</html>