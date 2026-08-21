(() => {
  const CONFIG_KEY = 'sgc_consent_v1';
  const VERSION = 1;

  const defaults = {
    version: VERSION,
    necessary: true,
    preferences: false,
    analytics: false,
    marketing: false,
    updatedAt: null
  };

  const safeParse = (value) => {
    try { return JSON.parse(value); } catch { return null; }
  };

  const storage = {
    get() { try { return localStorage.getItem(CONFIG_KEY); } catch { return null; } },
    set(value) { try { localStorage.setItem(CONFIG_KEY, value); return true; } catch { return false; } },
    remove() { try { storage.remove(); } catch {} }
  };

  const readConsent = () => {
    const stored = safeParse(storage.get());
    if (!stored || stored.version !== VERSION) return { ...defaults };
    return { ...defaults, ...stored, necessary: true };
  };

  const writeConsent = (next) => {
    const consent = {
      ...defaults,
      ...next,
      necessary: true,
      version: VERSION,
      updatedAt: new Date().toISOString()
    };
    storage.set(JSON.stringify(consent));
    window.dispatchEvent(new CustomEvent('sgc:consent-changed', { detail: consent }));
    updateConsentUI(consent);
    hideBanner();
    closePreferences();
    return consent;
  };

  window.SGCConsent = {
    get: readConsent,
    save: writeConsent,
    has: (category) => category === 'necessary' ? true : Boolean(readConsent()[category]),
    reset: () => {
      storage.remove();
      showBanner();
      updateConsentUI(defaults);
    }
  };

  const $ = (id) => document.getElementById(id);
  const banner = $('cookieBanner');
  const modal = $('cookiePreferences');
  const overlay = $('cookieOverlay');

  const showBanner = () => banner?.classList.remove('hidden');
  const hideBanner = () => banner?.classList.add('hidden');

  const openPreferences = () => {
    if (!modal || !overlay) return;
    const c = readConsent();
    ['preferences', 'analytics', 'marketing'].forEach((k) => {
      const input = document.querySelector(`[data-consent-toggle="${k}"]`);
      if (input) input.checked = Boolean(c[k]);
    });
    modal.classList.remove('hidden');
    overlay.classList.remove('hidden');
    document.documentElement.classList.add('overflow-hidden');
    setTimeout(() => modal.querySelector('button, input')?.focus(), 10);
  };

  const closePreferences = () => {
    modal?.classList.add('hidden');
    overlay?.classList.add('hidden');
    document.documentElement.classList.remove('overflow-hidden');
  };

  const updateConsentUI = (consent) => {
    document.querySelectorAll('[data-consent-status]').forEach((el) => {
      const category = el.dataset.consentStatus;
      const enabled = category === 'necessary' ? true : Boolean(consent[category]);
      el.textContent = enabled ? 'Ativo' : 'Inativo';
      el.dataset.enabled = String(enabled);
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    const stored = safeParse(storage.get());
    if (!stored || stored.version !== VERSION) showBanner();
    updateConsentUI(readConsent());

    document.querySelectorAll('[data-open-cookie-preferences]').forEach((el) => {
      el.addEventListener('click', (event) => {
        event.preventDefault();
        openPreferences();
      });
    });

    document.querySelectorAll('[data-cookie-accept-all]').forEach((el) => {
      el.addEventListener('click', () => writeConsent({
        preferences: true,
        analytics: true,
        marketing: true
      }));
    });

    document.querySelectorAll('[data-cookie-reject-optional]').forEach((el) => {
      el.addEventListener('click', () => writeConsent({
        preferences: false,
        analytics: false,
        marketing: false
      }));
    });

    document.querySelectorAll('[data-cookie-save]').forEach((el) => {
      el.addEventListener('click', () => {
        const values = {};
        ['preferences', 'analytics', 'marketing'].forEach((k) => {
          values[k] = Boolean(document.querySelector(`[data-consent-toggle="${k}"]`)?.checked);
        });
        writeConsent(values);
      });
    });

    document.querySelectorAll('[data-cookie-close]').forEach((el) => {
      el.addEventListener('click', closePreferences);
    });

    overlay?.addEventListener('click', closePreferences);

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
        closePreferences();
      }
    });
  });
})();