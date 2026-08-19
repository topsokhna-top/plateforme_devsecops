/* =========================================================
   THEME TOGGLE (dark / light, persisté via localStorage)
========================================================= */
(function initTheme() {
  const root = document.documentElement;
  const toggle = document.getElementById('theme-toggle');
  const saved = localStorage.getItem('portfolio-cyber-theme');
  const prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;

  const initial = saved || (prefersLight ? 'light' : 'dark');
  root.setAttribute('data-theme', initial);
  toggle.setAttribute('aria-pressed', String(initial === 'light'));

  toggle.addEventListener('click', () => {
    const current = root.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem('portfolio-cyber-theme', next);
    toggle.setAttribute('aria-pressed', String(next === 'light'));
  });
})();

/* =========================================================
   MENU MOBILE
========================================================= */
(function initMobileNav() {
  const burger = document.getElementById('nav-burger');
  const nav = document.getElementById('main-nav');

  burger.addEventListener('click', () => {
    const isOpen = nav.classList.toggle('is-open');
    burger.setAttribute('aria-expanded', String(isOpen));
  });

  nav.querySelectorAll('.nav-link').forEach((link) => {
    link.addEventListener('click', () => {
      nav.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
    });
  });
})();

/* =========================================================
   EFFET DE DÉCHIFFREMENT DU RÔLE DANS LE HERO
   Le texte apparaît d'abord en caractères aléatoires,
   puis se "décode" progressivement vers le texte final.
========================================================= */
(function initDecryptEffect() {
  const el = document.getElementById('hero-role');
  if (!el) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const finalText = el.getAttribute('data-text') || el.textContent;

  if (prefersReducedMotion) {
    el.textContent = finalText;
    return;
  }

  const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ01#$%&*';
  const revealDelayPerChar = 28; // ms entre chaque caractère qui se fixe
  const scrambleFrameMs = 40;

  let frame = 0;
  let revealedCount = 0;
  let lastRevealTime = performance.now();

  el.setAttribute('aria-label', finalText);

  function tick(now) {
    if (now - lastRevealTime > revealDelayPerChar && revealedCount < finalText.length) {
      revealedCount += 1;
      lastRevealTime = now;
    }

    let output = '';
    for (let i = 0; i < finalText.length; i++) {
      const originalChar = finalText[i];
      if (originalChar === ' ') {
        output += ' ';
      } else if (i < revealedCount) {
        output += originalChar;
      } else {
        output += charset[Math.floor(Math.random() * charset.length)];
      }
    }
    el.textContent = output;
    frame += 1;

    if (revealedCount < finalText.length) {
      setTimeout(() => requestAnimationFrame(tick), scrambleFrameMs);
    } else {
      el.textContent = finalText;
    }
  }

  // Léger délai avant de démarrer, pour laisser le hero apparaître d'abord
  setTimeout(() => requestAnimationFrame(tick), 300);
})();

/* =========================================================
   REVEAL AU SCROLL
========================================================= */
(function initReveal() {
  const items = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window) || items.length === 0) {
    items.forEach((el) => el.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  items.forEach((el) => observer.observe(el));
})();

/* =========================================================
   ANNÉE COURANTE DANS LE FOOTER
========================================================= */
document.getElementById('year').textContent = new Date().getFullYear();

/* =========================================================
   JETON ANTI-CSRF (double-submit cookie)
   On génère un jeton aléatoire, déposé à la fois dans un cookie et dans
   un champ caché du formulaire. contact.php vérifie que les deux
   correspondent : un site tiers qui forcerait une soumission du
   formulaire ne peut pas lire ce cookie (règle du navigateur), donc
   ne peut pas fournir la même valeur dans le champ caché.
========================================================= */
(function initCsrfToken() {
  const field = document.getElementById('csrf_token');
  if (!field) return;

  function generateToken() {
    const bytes = new Uint8Array(32);
    (window.crypto || window.msCrypto).getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  }

  function setCookie(name, value) {
    const isSecure = window.location.protocol === 'https:';
    const parts = [
      `${name}=${value}`,
      'path=/',
      'SameSite=Strict',
      'max-age=3600',
    ];
    if (isSecure) parts.push('Secure');
    document.cookie = parts.join('; ');
  }

  const token = generateToken();
  setCookie('csrf_token', token);
  field.value = token;
})();

/* =========================================================
   FORMULAIRE DE CONTACT (envoi AJAX vers php/contact.php)
========================================================= */
(function initContactForm() {
  const form = document.getElementById('contact-form');
  const status = document.getElementById('form-status');
  const submitBtn = document.getElementById('submit-btn');

  if (!form) return;

  function setStatus(message, state) {
    status.textContent = message;
    status.setAttribute('data-state', state || '');
  }

  function validate() {
    const name = form.name.value.trim();
    const email = form.email.value.trim();
    const subject = form.subject.value.trim();
    const message = form.message.value.trim();
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!name || !email || !subject || !message) {
      setStatus('Merci de remplir tous les champs.', 'error');
      return false;
    }
    if (!emailPattern.test(email)) {
      setStatus('Merci d\u2019indiquer une adresse email valide.', 'error');
      return false;
    }
    return true;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (form.website.value.trim() !== '') {
      setStatus('Message envoyé.', 'success');
      form.reset();
      return;
    }

    if (!validate()) return;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Envoi en cours...';
    setStatus('', '');

    try {
      const formData = new FormData(form);
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });

      const data = await response.json();

      if (data.success) {
        setStatus('Message envoyé, merci ! Je te réponds au plus vite.', 'success');
        form.reset();
      } else {
        setStatus(data.message || 'Une erreur est survenue. Réessaie.', 'error');
      }
    } catch (err) {
      setStatus('Impossible de contacter le serveur. Réessaie plus tard.', 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Envoyer le message';
    }
  });
})();
