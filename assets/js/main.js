// WebsiteVoorJou — Main JS

document.addEventListener('DOMContentLoaded', () => {

  // ---- Mobile nav toggle ----
  const toggle = document.querySelector('.navbar-toggle');
  const nav    = document.querySelector('.navbar-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => nav.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (!toggle.contains(e.target) && !nav.contains(e.target)) {
        nav.classList.remove('open');
      }
    });
  }

  // ---- Sidebar toggle (mobile) ----
  const sidebar = document.querySelector('.sidebar');
  if (sidebar) {
    // Injecteer mobiele topbalk als die er nog niet is
    if (!document.querySelector('.mobile-topbar')) {
      const brand = sidebar.querySelector('.sidebar-brand');
      const brandText = brand ? brand.textContent : 'WebsiteVoorJou';

      const overlay = document.createElement('div');
      overlay.className = 'sidebar-overlay';
      document.body.prepend(overlay);

      const topbar = document.createElement('div');
      topbar.className = 'mobile-topbar';
      topbar.innerHTML = `<button class="sidebar-toggle" aria-label="Menu openen"><span></span><span></span><span></span></button><div class="mobile-topbar-brand">${brandText}</div>`;
      document.body.prepend(topbar);
    }

    const toggle  = document.querySelector('.sidebar-toggle');
    const overlay = document.querySelector('.sidebar-overlay');

    const openSidebar  = () => { sidebar.classList.add('open');    overlay.classList.add('open'); document.body.style.overflow = 'hidden'; };
    const closeSidebar = () => { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; };

    if (toggle)  toggle.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Sluit sidebar na klik op een link (mobile)
    sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); }));
  }

  // ---- FAQ accordion ----
  document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.faq-item');
      const isOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      if (!isOpen) item.classList.add('open');
    });
  });

  // ---- Animate on scroll ----
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-up');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));

  // ---- Smooth scroll for anchor links ----
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // ---- Sticky navbar shadow ----
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.style.boxShadow = window.scrollY > 20 ? '0 4px 24px rgba(0,0,0,0.4)' : '';
    });
  }

  // ---- File drop zone ----
  const drops = document.querySelectorAll('.file-drop');
  drops.forEach(drop => {
    const input = drop.querySelector('input[type="file"]');

    drop.addEventListener('click', () => input && input.click());
    drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('drag-over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('drag-over'));
    drop.addEventListener('drop', (e) => {
      e.preventDefault();
      drop.classList.remove('drag-over');
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
      }
    });

    if (input) {
      input.addEventListener('change', () => {
        const listEl = drop.querySelector('.file-list');
        if (!listEl) return;
        listEl.innerHTML = '';
        Array.from(input.files).forEach(file => {
          const size = file.size > 1024*1024
            ? (file.size/1024/1024).toFixed(1) + ' MB'
            : (file.size/1024).toFixed(0) + ' KB';
          const icon = file.type.startsWith('image/') ? '🖼️' : '📄';
          listEl.innerHTML += `<div class="file-item"><span class="file-item-icon">${icon}</span><span class="file-item-name">${file.name}</span><span class="file-item-size">${size}</span></div>`;
        });
      });
    }
  });

  // ---- Alert auto-dismiss ----
  document.querySelectorAll('.alert[data-dismiss]').forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity 0.4s';
      setTimeout(() => alert.remove(), 400);
    }, parseInt(alert.dataset.dismiss) || 5000);
  });

  // ---- Confirm dialogs ----
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // ---- Status color for select ----
  document.querySelectorAll('select.status-select').forEach(sel => {
    const setColor = () => {
      const colors = {
        nieuw: '#6C63FF', in_behandeling: '#00D4FF',
        preview_beschikbaar: '#FFB300', afgerond: '#00E676',
        factuur_gestuurd: '#FF5252', factuur_betaald: '#00E676'
      };
      sel.style.borderColor = colors[sel.value] || '';
    };
    sel.addEventListener('change', setColor);
    setColor();
  });

  // ---- Copy to clipboard ----
  document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
      navigator.clipboard.writeText(btn.dataset.copy).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Gekopieerd!';
        setTimeout(() => btn.textContent = orig, 2000);
      });
    });
  });

  // ---- Preview protection: disable right-click & selection ----
  if (document.body.classList.contains('preview-mode')) {
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('selectstart', e => e.preventDefault());
    document.addEventListener('keydown', e => {
      if (e.key === 'F12' || (e.ctrlKey && (e.key === 'u' || e.key === 's' || e.key === 'a'))) {
        e.preventDefault();
      }
    });
    document.addEventListener('copy', e => e.preventDefault());
  }

});
