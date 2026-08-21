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
        el.setAttribute('title', 'Preencha este dado em assets/legal-config.js antes de publicar.');
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
              Nenhum fornecedor foi configurado ainda. Cadastre somente os subprocessadores efetivamente utilizados em <code>assets/legal-config.js</code>.
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
      const output = form.querySelector('[data-request-output]');
      const copy = form.querySelector('[data-copy-request]');
      const emailButton = form.querySelector('[data-email-request]');

      const build = () => {
        const data = new FormData(form);
        const kind = data.get('requestType') || 'Solicitação relacionada a dados pessoais';
        const body = [
          `Assunto: ${kind}`,
          '',
          `Nome: ${data.get('name') || ''}`,
          `E-mail de cadastro/contato: ${data.get('email') || ''}`,
          `Organização/tenant (se aplicável): ${data.get('organization') || ''}`,
          '',
          'Descrição da solicitação:',
          `${data.get('details') || ''}`,
          '',
          'Declaro que as informações acima são suficientes para permitir a identificação segura da minha solicitação. Estou ciente de que poderão ser solicitadas informações adicionais para verificação de identidade.'
        ].join('\n');

        if (output) output.value = body;
        return { body, kind };
      };

      form.addEventListener('input', build);
      build();

      copy?.addEventListener('click', async () => {
        const { body } = build();
        try {
          await navigator.clipboard.writeText(body);
          const old = copy.textContent;
          copy.textContent = 'Copiado';
          setTimeout(() => copy.textContent = old, 1800);
        } catch {
          output?.select();
        }
      });

      emailButton?.addEventListener('click', (event) => {
        event.preventDefault();
        const email = cfg.privacyEmail || '';
        if (isMissing(email)) {
          alert('Configure privacyEmail em assets/legal-config.js antes de usar o envio por e-mail.');
          return;
        }
        const { body, kind } = build();
        location.href = `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent(`[SGC] ${kind}`)}&body=${encodeURIComponent(body)}`;
      });
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    setText();
    buildSubprocessors();
    setupRequestForms();
  });
})();