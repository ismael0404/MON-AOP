<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KLINIK — Système de Gestion Hospitalière</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- NAVBAR -->
<div class="nav-float-wrap">
  <nav class="navbar-float" id="mainNav">
    <a class="nav-logo" href="index.php">
      <img src="assets/img/logo.png" alt="KLINIK" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
     
    </a>
    <ul class="nav-links">
      <li><a href="#" class="active">Accueil</a></li>
      <li><a href="#about">À propos</a></li>
      <li><a href="#services">Services</a></li>
      <li><a href="#departements">Départements</a></li>
    </ul>

    <div class="nav-cta">
      <a href="auth/login.php" class="nav-btn-login">Connexion</a>
      <a href="auth/register.php" class="nav-btn-register">S'inscrire</a>
    </div>

     <!-- Menu hamburger pour mobile -->
    <button class="nav-burger" id="navBurger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

    <!-- Menu mobile déroulant -->
  </nav>
  <div class="nav-mobile-menu" id="navMobile">
    <a href="#">Accueil</a>
    <a href="#about">À propos</a>
    <a href="#services">Services</a>
    <a href="#departements">Départements</a>
    <div class="nav-mobile-btns">
      <a href="auth/login.php" class="nav-btn-login">Connexion</a>
      <a href="auth/register.php" class="nav-btn-register">S'inscrire</a>
    </div>
  </div>
</div>

<!-- HERO (ACCEUIL) -->
<section class="hero" id="hero">

  <div class="hero-slides">
    <div class="hero-slide active" style="background-image:url('assets/img/hero1.jpg')"></div>
    <div class="hero-slide"        style="background-image:url('assets/img/hero2.jpg')"></div>
    <div class="hero-slide"        style="background-image:url('assets/img/hero3.jpg')"></div>
    <div class="hero-slide"        style="background-image:url('assets/img/hero4.jpg')"></div>
  </div>

  <div class="hero-overlay"></div>
  <div class="hero-body">
    <div class="container">
      <div class="hero-bottom">
        <div class="hero-content">
          <p class="hero-eyebrow">Bienvenue sur notre plateforme</p>
          <h1 class="hero-title">Centre Médical KLINIK </h1>
          <p class="hero-desc">Simplifiez l’organisation de votre centre de santé et accédez facilement aux informations essentielles.</p>
        </div>
        <div class="hero-cta">
          <a href="auth/register.php" class="btn-hero-primary">
            <span class="material-icons">person_add</span>Créer mon compte patient
          </a>
          <a href="auth/login.php" class="btn-hero-outline">
            <span class="material-icons">lock_open</span>Accéder à mon espace
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="hero-dots">
    <span class="hero-dot active" data-idx="0"></span>
    <span class="hero-dot" data-idx="1"></span>
    <span class="hero-dot" data-idx="2"></span>
    <span class="hero-dot" data-idx="3"></span>
  </div>
</section>

<!-- A PROPOS -->
<section class="about-section" id="about">
  <div class="container">
    <div class="row align-items-center g-5">

      <div class="col-lg-6">
        <div class="about-content">
          <p class="section-tag">À propos de KLINIK</p>
          <h2 class="about-title">Un partenaire de confiance<br>pour les soins médicaux</h2>
          <p class="about-text">KLINIK est une plateforme intuitive conçue pour placer le bien-être du patient au cœur de l'hôpital. En simplifiant la gestion administrative et en sécurisant le partage des données, nous permettons aux soignants de se consacrer pleinement à leur mission : offrir un accompagnement humain et un suivi médical d'exception.</p>

          <div class="about-stats">
            
            <div class="about-stat">
              <span class="stat-num">130<span class="stat-plus">+</span></span>
              <span class="stat-label">Patients suivis</span>
              <p class="stat-desc">Un suivi personnalisé et sécurisé pour garantir une prise en charge humaine et continue à chaque étape du parcours de soin.</p>
            </div>

            <div class="about-stat">
              <span class="stat-num">6<span class="stat-plus">+</span></span>
              <span class="stat-label">Spécialités médicales</span>
              <p class="stat-desc">Des départements couvrant toutes les disciplines essentielles pour une vision globale et précise de la santé de chaque patient.</p>
            </div>
            
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="about-img-wrap">
          <img src="assets/img/about.jpg" alt="Équipe médicale KLINIK" class="about-img">
        </div>
      </div>

    </div>
  </div>
</section>

<!-- SERVICES -->
<section class="services-section" id="services">

  <div class="container">

    <div class="services-header">
      <p class="section-tag">Ce que nous offrons</p>
      <h2>Nos Services Médicaux</h2>
      <p class="services-sub">Une suite complète d'outils pour moderniser votre établissement de santé</p>
    </div>

     <!-- Grid de card des services -->
    <div class="services-grid">

      <!-- Card 1-->
      <div class="service-card" data-delay="0">
        <div class="service-icon-wrap">
          <span class="material-icons">stethoscope</span>
        </div>
        <h4>Consultation</h4>
        <p>Consultations médicales planifiées avec des médecins qualifiés et spécialistes certifiés.</p>
      </div>

      <!-- Card 2 -->
      <div class="service-card" data-delay="80">
        <div class="service-icon-wrap">
          <span class="material-icons">biotech</span>
        </div>
        <h4>Examens Labo</h4>
        <p>Gestion et suivi des examens biologiques, résultats en temps réel accessibles en ligne.</p>
      </div>

      <!-- Card 3 -->
      <div class="service-card" data-delay="160">
        <div class="service-icon-wrap">
          <span class="material-icons">folder_shared</span>
        </div>
        <h4>Dossier Patient</h4>
        <p>Suivi complet et sécurisé du dossier médical tout au long du parcours de soins.</p>
      </div>

      <!-- Card 4 -->
      <div class="service-card" data-delay="240">
        <div class="service-icon-wrap">
          <span class="material-icons">receipt_long</span>
        </div>
        <h4>Facturation</h4>
        <p>Gestion transparente des paiements, factures et remboursements médicaux.</p>
      </div>

      <!-- Card 5 -->
      <div class="service-card" data-delay="320">
        <div class="service-icon-wrap">
          <span class="material-icons">event_available</span>
        </div>
        <h4>Rendez-vous en ligne</h4>
        <p>Prenez et gérez vos rendez-vous médicaux en ligne, à tout moment et depuis n'importe où.</p>
      </div>

      <!-- Card 6 -->
      <!-- <div class="service-card" data-delay="400">
        <div class="service-icon-wrap">
          <span class="material-icons">description</span>
        </div>
        <h4>E-ordonnance</h4>
        <p>Ordonnances électroniques sécurisées, accessibles depuis votre espace patient.</p>
      </div> -->

      <!-- Card 7 -->
      <!-- <div class="service-card" data-delay="480">
        <div class="service-icon-wrap">
          <span class="material-icons">monitor_heart</span>
        </div>
        <h4>Suivi à distance</h4>
        <p>Téléconsultation et suivi médical à distance pour une prise en charge continue.</p>
      </div> -->

    </div>
  </div>
</section>

<!-- DEPARTEMENTS -->
<section class="dept-section" id="departements">
  <div class="container">
    <div class="services-header">
      <p class="section-tag">Nos spécialités</p>
      <h2>Nos Départements</h2>
      <p class="services-sub">Sélectionnez une spécialité pour en savoir plus</p>
    </div>

    <div class="dept-tabs">
      <!-- Card dentiserie -->
      <div class="dept-card dept-card--active" data-dept="dentisterie">
        <div class="dept-icon"><span class="material-icons">medical_services</span></div>
        <h5>Dentisterie</h5>
      </div>

      <!-- Card cardiologie -->
      <div class="dept-card" data-dept="cardiologie">
        <div class="dept-icon"><span class="material-icons">monitor_heart</span></div>
        <h5>Cardiologie</h5>
      </div>

      <!-- Card radiologie -->
      <div class="dept-card" data-dept="radiologie">
        <div class="dept-icon"><span class="material-icons">personal_injury</span></div>
        <h5>Radiologie</h5>
      </div>

      <!-- Card radiologie -->
      <div class="dept-card" data-dept="gynecologie">
        <div class="dept-icon"><span class="material-icons">female</span></div>
        <h5>Gynécologie</h5>
      </div>

      <!-- Card maternité -->
      <div class="dept-card" data-dept="maternite">
        <div class="dept-icon"><span class="material-icons">pregnant_woman</span></div>
        <h5>Maternité</h5>
      </div>

      <!-- Card neurologie -->
      <div class="dept-card" data-dept="neurologie">
        <div class="dept-icon"><span class="material-icons">psychology</span></div>
        <h5>Neurologie</h5>
      </div>
    </div>

    <div class="dept-panel" id="deptPanel">
      <div class="dept-panel-img">
        <img src="assets/img/dept-dentisterie.jpg" alt="Dentisterie" id="deptImg">
      </div>

      <div class="dept-panel-text">
        <p class="dept-panel-tag" id="deptTag">Dentisterie</p>
        <h3 class="dept-panel-title">Bienvenue en <span id="deptTitleAccent">Dentisterie</span></h3>
        <p class="dept-panel-desc" id="deptDesc">Notre service de dentisterie offre des soins bucco-dentaires complets dans un environnement moderne et rassurant. Prévention, traitement et suivi personnalisé pour toute la famille.</p>
      </div>
    </div>

  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="footer-inner">

      <div class="footer-col footer-col--brand">
        <div class="footer-brand-wrap">
          <img src="assets/img/logo.png" alt="KLINIK" class="footer-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
          <span class="footer-logo-text" style="display:none">KLINIK</span>
        </div>
        <p class="footer-desc">Plateforme intégrée pour la gestion hospitalière moderne — patients, consultations et facturation.</p>
      </div>

      <div class="footer-col">
        <h6 class="footer-heading">Navigation</h6>
        <ul class="footer-links">
          <li><a href="#">Accueil</a></li>
          <li><a href="#about">À propos</a></li>
          <li><a href="#services">Services</a></li>
          <li><a href="#departements">Départements</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h6 class="footer-heading">Accès</h6>
        <ul class="footer-links">
          <li><a href="auth/login.php">Connexion</a></li>
          <li><a href="auth/register.php">Inscription patient</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h6 class="footer-heading">Contact</h6>
        <ul class="footer-contact">
          <li><span class="material-icons">call</span><span>+225 07 00 00 00</span></li>
          <li><span class="material-icons">email</span><span>contact@klinik.ci</span></li>
          <li><span class="material-icons">schedule</span><span>Lun – Sam, 8h – 18h</span></li>
          <li><span class="material-icons">emergency</span><span>Urgences 24h/7j</span></li>
        </ul>
      </div>

    </div>
    <div class="footer-bottom">
      <p>© 2026 KLINIK. Tous droits réservés.</p>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
  const slides = document.querySelectorAll('.hero-slide');
  const dots   = document.querySelectorAll('.hero-dot');
  let current  = 0, timer;
  function goTo(idx) {
    const prev = current;
    current = (idx + slides.length) % slides.length;
    if (prev === current) return;
    slides[prev].classList.add('leaving');
    slides[current].classList.add('entering');
    dots[prev].classList.remove('active');
    dots[current].classList.add('active');
    requestAnimationFrame(() => {
      requestAnimationFrame(() => { slides[current].classList.add('active'); });
    });
    setTimeout(() => {
      slides[prev].classList.remove('active','leaving');
      slides[current].classList.remove('entering');
    }, 1500);
  }
  function startTimer() { timer = setInterval(() => goTo(current + 1), 6000); }
  function resetTimer()  { clearInterval(timer); startTimer(); }
  dots.forEach(dot => dot.addEventListener('click', () => { goTo(+dot.dataset.idx); resetTimer(); }));
  startTimer();
})();

window.addEventListener('scroll', () => {
  document.getElementById('mainNav').classList.toggle('scrolled', window.scrollY > 60);
});

document.getElementById('navBurger').addEventListener('click', () => {
  document.getElementById('navMobile').classList.toggle('open');
});

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const delay = parseInt(entry.target.dataset.delay || 0);
      setTimeout(() => entry.target.classList.add('visible'), delay);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });
document.querySelectorAll('.service-card, .about-content, .about-img-wrap').forEach(el => observer.observe(el));

const deptData = {
  dentisterie: { tag:'Dentisterie', title:'Dentisterie', desc:"Notre service de dentisterie offre des soins bucco-dentaires complets dans un environnement moderne et rassurant. Prévention, traitement et suivi personnalisé pour toute la famille.", img:'assets/img/dept-dentisterie.jpg' },
  cardiologie:  { tag:'Cardiologie',  title:'Cardiologie',  desc:"Un suivi cardiovasculaire rigoureux assuré par des spécialistes expérimentés. Diagnostic, traitement et prévention des maladies du cœur avec des équipements de pointe.", img:'assets/img/dept-cardiologie.jpg' },
  radiologie:   { tag:'Radiologie',   title:'Radiologie',   desc:"Des examens d'imagerie médicale précis et rapides. Nos radiologues analysent chaque résultat avec rigueur pour orienter le diagnostic vers le traitement approprié.", img:'assets/img/dept-radiologie.jpg' },
  gynecologie:  { tag:'Gynécologie',  title:'Gynécologie',  desc:"Un accompagnement médical dédié à la santé féminine, de la prévention au suivi spécialisé. Consultations et examens dans un cadre confidentiel et bienveillant.", img:'assets/img/dept-gynecologie.jpg' },
  maternite:    { tag:'Maternité',    title:'Maternité',    desc:"Nous accompagnons les futures mères à chaque étape de leur grossesse. Une équipe dédiée pour assurer la sécurité et le bien-être de la mère et de l'enfant.", img:'assets/img/dept-maternite.jpg' },
  neurologie:   { tag:'Neurologie',   title:'Neurologie',   desc:"Diagnostic et prise en charge des maladies du système nerveux. Nos neurologues utilisent des protocoles modernes pour un traitement adapté à chaque patient.", img:'assets/img/dept-neurologie.jpg' }
};

const panel      = document.getElementById('deptPanel');
const deptImg    = document.getElementById('deptImg');
const deptTag    = document.getElementById('deptTag');
const deptAccent = document.getElementById('deptTitleAccent');
const deptDesc   = document.getElementById('deptDesc');

document.querySelectorAll('.dept-card').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.dept-card').forEach(c => c.classList.remove('dept-card--active'));
    card.classList.add('dept-card--active');
    const d = deptData[card.dataset.dept];
    panel.classList.add('switching');
    setTimeout(() => {
      deptTag.textContent    = d.tag;
      deptAccent.textContent = d.title;
      deptDesc.textContent   = d.desc;
      deptImg.src            = d.img;
      deptImg.alt            = d.tag;
      panel.classList.remove('switching');
    }, 300);
  });
});
</script>
</body>
</html>
