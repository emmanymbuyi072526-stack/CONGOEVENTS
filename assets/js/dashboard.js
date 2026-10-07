document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('[data-dash-sidebar]');
  const toggle = document.querySelector('[data-dash-toggle]');
  const closeBtn = document.querySelector('[data-dash-close]');
  const overlay = document.querySelector('[data-dash-overlay]');

  if (sidebar) {
    const setOpen = (open) => {
      sidebar.classList.toggle('is-open', open);
      document.body.classList.toggle('dash-nav-open', open);
      if (toggle) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
      if (overlay) {
        overlay.hidden = !open;
        overlay.classList.toggle('is-visible', open);
      }
    };

    toggle?.addEventListener('click', () => {
      setOpen(!sidebar.classList.contains('is-open'));
    });

    closeBtn?.addEventListener('click', () => setOpen(false));
    overlay?.addEventListener('click', () => setOpen(false));

    sidebar.querySelectorAll('a').forEach((a) => {
      a.addEventListener('click', () => {
        if (window.innerWidth <= 860) setOpen(false);
      });
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 860) setOpen(false);
    });
  }

  // Aperçu photo salle
  const photoInput = document.querySelector('#photo');
  const photoPreview = document.querySelector('[data-photo-preview]');
  if (photoInput && photoPreview) {
    photoInput.addEventListener('change', () => {
      const file = photoInput.files && photoInput.files[0];
      if (!file) return;
      photoPreview.src = URL.createObjectURL(file);
      photoPreview.hidden = false;
      photoPreview.removeAttribute('hidden');
    });
  }

  // Menu ⋯ actions (Modifier / Désactiver)
  const closeAllMenus = () => {
    document.querySelectorAll('[data-row-menu].is-open').forEach((menu) => {
      menu.classList.remove('is-open');
      const btn = menu.querySelector('[data-row-menu-toggle]');
      const panel = menu.querySelector('.row-menu-panel');
      if (btn) btn.setAttribute('aria-expanded', 'false');
      if (panel) {
        panel.hidden = true;
        panel.setAttribute('hidden', '');
        panel.style.top = '';
        panel.style.left = '';
        panel.style.right = '';
      }
    });
  };

  const positionPanel = (btn, panel) => {
    const rect = btn.getBoundingClientRect();
    const panelWidth = 168;
    let left = rect.right - panelWidth;
    if (left < 8) left = 8;
    let top = rect.bottom + 6;
    panel.style.position = 'fixed';
    panel.style.top = `${top}px`;
    panel.style.left = `${left}px`;
    panel.style.right = 'auto';
    panel.style.zIndex = '9999';
  };

  document.querySelectorAll('[data-row-menu-toggle]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const menu = btn.closest('[data-row-menu]');
      const panel = menu?.querySelector('.row-menu-panel');
      if (!menu || !panel) return;

      const willOpen = !menu.classList.contains('is-open');
      closeAllMenus();

      if (willOpen) {
        menu.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        panel.hidden = false;
        panel.removeAttribute('hidden');
        positionPanel(btn, panel);
      }
    });
  });

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-row-menu]')) return;
    closeAllMenus();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllMenus();
  });

  window.addEventListener('scroll', () => closeAllMenus(), true);
  window.addEventListener('resize', () => closeAllMenus());
});
