(() => {
  const menuButton = document.getElementById('menuButton');
  const mobileMenu = document.getElementById('mobileMenu');
  const menuUse = menuButton?.querySelector('use');

  const closeMenu = () => {
    if (!menuButton || !mobileMenu) return;
    mobileMenu.classList.add('hidden');
    menuButton.setAttribute('aria-expanded', 'false');
    menuUse?.setAttribute('href', '#i-menu');
  };

  menuButton?.addEventListener('click', () => {
    const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', String(!isOpen));
    mobileMenu.classList.toggle('hidden', isOpen);
    menuUse?.setAttribute('href', isOpen ? '#i-menu' : '#i-x');
  });

  document.querySelectorAll('#mobileMenu a').forEach((link) => {
    link.addEventListener('click', closeMenu);
  });

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

  document.getElementById('year').textContent = new Date().getFullYear();

  const form = document.getElementById('demoForm');
  const toast = document.getElementById('toast');

  form?.addEventListener('submit', (event) => {
    event.preventDefault();

    const required = [...form.querySelectorAll('[required]')];
    const invalid = required.find((field) => !field.value.trim() || !field.checkValidity());

    if (invalid) {
      invalid.focus();
      invalid.classList.add('field-invalid');
      setTimeout(() => invalid.classList.remove('field-invalid'), 1400);
      return;
    }

    toast?.classList.remove('hidden');
    setTimeout(() => toast?.classList.add('hidden'), 4200);
  });
})();