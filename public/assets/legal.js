(() => {
  const cfg = window.SGC_LEGAL_CONFIG || {};
  const isMissing = (value) => !value || String(value).startsWith('PREENCHER:');

  const setText = () => {
    document.querySelectorAll('[data-legal]').forEach((el) => {
      const key = el.dataset.legal;
      const value = cfg[key] ?? '';
      el.textContent = value;
      if (isMissing(value)) {
        el.classList.add('legal-missing');
        el.setAttribute('title', 'Informação indisponível.');
      }
    });

    document.querySelectorAll('[data-legal-mailto]').forEach((el) => {
      const key = el.dataset.legalMailto;
      const value = cfg[key] ?? '';
      el.textContent = value;
      if (isMissing(value)) {
        el.removeAttribute('href');
        el.classList.add('legal-missing');
      } else {
        el.href = `mailto:${value}`;
      }
    });

    const unresolved = Object.entries(cfg).filter(([_, v]) => isMissing(v)).length;
    const notice = document.getElementById('legalConfigNotice');
    if (notice && unresolved === 0) notice.classList.add('hidden');
  };

  const buildSubprocessors = () => {
    const target = document.getElementById('subprocessorRows');
    if (!target) return;

    const rows = Array.isArray(cfg.subprocessors) ? cfg.subprocessors : [];
    if (!rows.length) {
      target.innerHTML = `
        <tr>
          <td colspan="4">
            <div class="legal-empty">
              A relação de fornecedores está em validação. Antes de qualquer atualização relevante da infraestrutura, esta página será revisada para identificar os fornecedores efetivamente utilizados e sua finalidade.
            </div>
          </td>
        </tr>`;
      return;
    }

    target.innerHTML = rows.map((item) => `
      <tr>
        <td><strong>${escapeHtml(item.name || '—')}</strong></td>
        <td>${escapeHtml(item.purpose || '—')}</td>
        <td>${escapeHtml(item.country || '—')}</td>
        <td>${escapeHtml(item.data || '—')}</td>
      </tr>`).join('');
  };

  const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const setupRequestForms = () => {
    document.querySelectorAll('[data-legal-request-form]').forEach((form) => {
      const button = form.querySelector('[type="submit"]');
      const feedback = form.querySelector('[data-request-feedback]');

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;

        const data = Object.fromEntries(new FormData(form));
        data.request_type = data.request_type || 'Solicitação relacionada a dados pessoais';
        data.message = data.message || data.details || '';
        delete data.details;

        if (button) {
          button.disabled = true;
          button.textContent = 'Enviando...';
        }

        try {
          const response = await fetch('/solicitacoes-privacidade', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(data),
          });
          const payload = await response.json();

          if (!response.ok) {
            throw new Error(payload.message || 'Não foi possível enviar sua solicitação.');
          }

          form.reset();
          if (feedback) {
            feedback.textContent = payload.message;
            feedback.className = 'legal-note';
          }
        } catch (error) {
          if (feedback) {
            feedback.textContent = error.message || 'Não foi possível enviar sua solicitação. Tente novamente.';
            feedback.className = 'legal-warning';
          }
        } finally {
          if (button) {
            button.disabled = false;
            button.textContent = 'Enviar solicitação';
          }
        }
      });
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    setText();
    buildSubprocessors();
    setupRequestForms();
  });
})();
