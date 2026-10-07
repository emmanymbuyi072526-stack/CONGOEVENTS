document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-nav-toggle]');
  const menu = document.querySelector('[data-nav]');
  const closeBtn = document.querySelector('[data-nav-close]');
  const overlay = document.querySelector('[data-nav-overlay]');
  const header = document.querySelector('.site-header');

  const setMenuOpen = (open) => {
    if (!menu) return;
    menu.classList.toggle('is-open', open);
    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    }
    document.body.classList.toggle('nav-open', open);
    if (header) header.classList.toggle('menu-open', open);
    if (overlay) {
      overlay.hidden = !open;
      overlay.classList.toggle('is-visible', open);
    }
  };

  setMenuOpen(false);

  toggle?.addEventListener('click', () => {
    setMenuOpen(!menu.classList.contains('is-open'));
  });

  closeBtn?.addEventListener('click', () => setMenuOpen(false));
  overlay?.addEventListener('click', () => setMenuOpen(false));

  menu?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenuOpen(false));
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) setMenuOpen(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setMenuOpen(false);
  });

  if (header && header.classList.contains('header-home')) {
    const onScroll = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 36);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  document.querySelectorAll('[data-auto-dismiss]').forEach((el) => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.4s ease';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4500);
  });

  const debut = document.querySelector('#date_debut');
  const fin = document.querySelector('#date_fin');
  if (debut && fin) {
    const syncMin = () => {
      if (debut.value) {
        fin.min = debut.value;
        if (fin.value && fin.value < debut.value) {
          fin.value = debut.value;
        }
      }
    };
    debut.addEventListener('change', syncMin);
    syncMin();
  }

  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      const msg = el.getAttribute('data-confirm') || 'Confirmer cette action ?';
      if (!window.confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // Carrousel hero accueil
  const slider = document.querySelector('[data-hero-slider]');
  if (slider) {
    const slides = [...slider.querySelectorAll('.hero-slide')];
    const dots = [...document.querySelectorAll('[data-hero-dot]')];
    let index = 0;
    let timer;

    const goTo = (next) => {
      slides[index]?.classList.remove('is-active');
      dots[index]?.classList.remove('is-active');
      index = (next + slides.length) % slides.length;
      slides[index]?.classList.add('is-active');
      dots[index]?.classList.add('is-active');
    };

    const start = () => {
      clearInterval(timer);
      timer = setInterval(() => goTo(index + 1), 5000);
    };

    dots.forEach((dot) => {
      dot.addEventListener('click', () => {
        goTo(Number(dot.dataset.heroDot) || 0);
        start();
      });
    });

    start();
  }
});
