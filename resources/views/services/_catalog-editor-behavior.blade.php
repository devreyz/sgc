<style>
    .cc-history { border:1px solid var(--ui-color-border,#dde5e0); border-radius:12px; padding:.85rem; }
    .cc-history>summary { cursor:pointer; font-weight:700; }
    .cc-history[open]>summary { margin-bottom:.8rem; }
    .cc-action:disabled,.ct-open:disabled { opacity:.6; cursor:wait; }
    .cc-edit-notice { padding:.85rem 1rem; border-radius:12px; background:var(--ui-color-surface,#edf8f2); border:1px solid var(--ui-color-border,#cce8d7); }
</style>
@if($isDraft)
    <p class="cc-edit-notice" role="status">Você está editando este serviço. Use <strong>Salvar alterações</strong> para atualizar os valores e a execução dos próximos lançamentos. As ordens existentes mantêm suas tarifas.</p>
@endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const configForms = [...document.querySelectorAll('form')].filter(form => form.elements.namedItem('form_section'));
    const applyForm = [...document.querySelectorAll('form')].find(form => /\/catalog\/versions\/\d+\/publish$/.test(new URL(form.action).pathname));
    if (applyForm && configForms.length) {
        applyForm.id = 'catalog-apply-form';
        configForms.forEach(form => form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.setAttribute('form', applyForm.id);
            button.textContent = 'Salvar alterações';
        }));
        applyForm.addEventListener('submit', event => {
            if (!configForms.every(form => form.reportValidity())) {
                event.preventDefault();
                const invalid = configForms.flatMap(form => [...form.elements]).find(input => input.willValidate && !input.validity.valid);
                const panel = invalid?.closest('[data-tab-panel]');
                if (panel) document.querySelector('[data-tab="' + panel.dataset.tabPanel + '"]')?.click();
                invalid?.focus();
                return;
            }
            applyForm.querySelectorAll('[data-collected-config]').forEach(input => input.remove());
            const addInput = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = name; input.value = value;
                input.dataset.collectedConfig = 'true'; applyForm.append(input);
            };
            configForms.forEach(form => new FormData(form).forEach((value, name) => {
                if (!['_token', '_method', 'form_section', 'intent'].includes(name) && typeof value === 'string') addInput(name, value);
            }));
            addInput('_method', 'PUT'); addInput('form_section', 'all'); addInput('intent', 'apply');
            applyForm.action = configForms[0].action;
        });
    }
    document.querySelectorAll('form').forEach(form => {
        if (!/\/catalog(?:\/|$)/.test(new URL(form.action).pathname)) return;
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (form.dataset.submitting) {event.preventDefault(); return;}
            form.dataset.submitting = 'true'; form.setAttribute('aria-busy', 'true');
            requestAnimationFrame(() => [...form.elements].filter(input => input.type === 'submit').forEach(button => {button.disabled = true; button.textContent = 'Salvando…';}));
        });
    });
});
</script>
