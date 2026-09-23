@php
    $prefix = "financial_config[rules][{$index}]";
    $method = $rule['method'] ?? 'fixed_addition';
    $effect = $rule['effect'] ?? (str_contains($method, 'deduction') ? 'subtract' : 'add');
@endphp

<fieldset class="financial-rule" data-rule style="border:1px solid var(--cc-border);border-radius:12px;padding:.8rem;margin-bottom:.7rem;background:#fff">
    <legend style="padding:0 .35rem"><strong data-rule-title>{{ $rule['description'] ?? 'Novo termo financeiro' }}</strong></legend>
    <div class="cc-inline-note" style="margin-bottom:.65rem"><i class="ph-fill ph-calculator"></i><span>Este termo altera somente o lado selecionado. O campo criado aqui aparecerá na execução e nos documentos.</span></div>
    <div class="cc-grid">
        <label class="cc-field full"><span class="cc-label">Nome do termo</span><input class="cc-control" data-rule-description name="{{ $prefix }}[description]" value="{{ $rule['description'] ?? '' }}" required placeholder="Ex.: Desconto do óleo fornecido"></label>
        <label class="cc-field"><span class="cc-label">Quem este termo altera</span><select class="cc-control" data-rule-direction name="{{ $prefix }}[direction]" required><option value="receivable" @selected(($rule['direction'] ?? '') === 'receivable')>Cobrança da organização</option><option value="payable" @selected(($rule['direction'] ?? '') === 'payable')>Remuneração do prestador</option></select></label>
        <label class="cc-field"><span class="cc-label">Efeito no total</span><select class="cc-control" data-rule-effect name="{{ $prefix }}[effect]" required><option value="add" @selected($effect === 'add')>Acréscimo</option><option value="subtract" @selected($effect === 'subtract')>Desconto</option></select></label>
        <label class="cc-field full"><span class="cc-label">Como calcular</span><select class="cc-control" data-rule-method name="{{ $prefix }}[method]" required><option value="fixed_addition" @selected(in_array($method, ['fixed_addition','fixed_deduction'], true))>Valor fixo ou informado na execução</option><option value="quantity_x_rate" @selected($method === 'quantity_x_rate')>Quantidade × tarifa</option><option value="percent_addition" @selected(in_array($method, ['percent_addition','percent_deduction'], true))>Percentual do valor base</option></select></label>
        <label class="cc-field" data-rule-setting="value"><span class="cc-label">Valor fixo ou tarifa</span><input class="cc-control" type="number" step="0.0001" min="0" name="{{ $prefix }}[value]" value="{{ $rule['value'] ?? 0 }}" inputmode="decimal"></label>
        <label class="cc-field" data-rule-setting="percentage"><span class="cc-label">Percentual</span><input class="cc-control" type="number" step="0.0001" min="0" max="100" name="{{ $prefix }}[percentage]" value="{{ $rule['percentage'] ?? 0 }}" inputmode="decimal"></label>
        <label class="cc-field" data-rule-setting="value-field"><span class="cc-label">Usar valor de um campo existente</span><select class="cc-control" name="{{ $prefix }}[value_field]"><option value="">Não usar</option>@foreach($numericFields as $field)<option value="{{ $field->key }}" @selected(($rule['value_field'] ?? '') === $field->key)>{{ $field->label }}</option>@endforeach</select></label>
        <label class="cc-field" data-rule-setting="quantity-field"><span class="cc-label">Campo que informa a quantidade</span><select class="cc-control" name="{{ $prefix }}[quantity_field]"><option value="">Selecionar depois ou criar abaixo</option>@foreach($numericFields as $field)<option value="{{ $field->key }}" @selected(($rule['quantity_field'] ?? '') === $field->key)>{{ $field->label }}</option>@endforeach</select></label>
        <div class="cc-field full" style="padding:.65rem;border:1px dashed var(--cc-border);border-radius:10px" data-rule-input>
            <span class="cc-label">Campo preenchido durante o serviço (opcional)</span>
            <div class="cc-grid" style="margin-top:.45rem">
                <label class="cc-field"><span class="cc-label">Identificador</span><input class="cc-control" name="{{ $prefix }}[input_key]" value="{{ $rule['input_key'] ?? '' }}" pattern="[a-z][a-z0-9_]*" placeholder="litros_oleo"></label>
                <label class="cc-field"><span class="cc-label">Nome mostrado</span><input class="cc-control" name="{{ $prefix }}[input_label]" value="{{ $rule['input_label'] ?? '' }}" placeholder="Litros de óleo fornecido"></label>
                <label class="cc-field"><span class="cc-label">O campo informa</span><select class="cc-control" name="{{ $prefix }}[input_role]"><option value="quantity" @selected(($rule['input_role'] ?? 'quantity') === 'quantity')>Quantidade</option><option value="value" @selected(($rule['input_role'] ?? '') === 'value')>Valor ou percentual</option></select></label>
                <label class="cc-field"><span class="cc-label">Unidade</span><input class="cc-control" name="{{ $prefix }}[input_unit]" value="{{ $rule['input_unit'] ?? '' }}" placeholder="litro, km, hora"></label>
                <label class="cc-field"><span class="cc-label">Quando preencher</span><select class="cc-control" name="{{ $prefix }}[input_phase]"><option value="start" @selected(($rule['input_phase'] ?? '') === 'start')>Ao iniciar</option><option value="execution" @selected(($rule['input_phase'] ?? '') === 'execution')>Durante a execução</option><option value="finish" @selected(($rule['input_phase'] ?? 'finish') === 'finish')>Ao concluir</option></select></label>
                <label class="cc-check"><input type="checkbox" name="{{ $prefix }}[input_required]" value="1" @checked($rule['input_required'] ?? false)><span>Preenchimento obrigatório</span></label>
            </div>
        </div>
        <div class="cc-field full" style="padding:.65rem;border:1px dashed var(--cc-border);border-radius:10px">
            <label class="cc-check"><input type="checkbox" data-rule-evidence-toggle name="{{ $prefix }}[evidence_required]" value="1" @checked($rule['evidence_required'] ?? false)><span>Exigir comprovante quando este termo produzir valor</span></label>
            <div class="cc-grid" data-rule-evidence style="margin-top:.45rem">
                <label class="cc-field"><span class="cc-label">Identificador do comprovante</span><input class="cc-control" name="{{ $prefix }}[evidence_key]" value="{{ $rule['evidence_key'] ?? '' }}" pattern="[a-z][a-z0-9_]*" placeholder="comprovante_oleo"></label>
                <label class="cc-field"><span class="cc-label">Nome do comprovante</span><input class="cc-control" name="{{ $prefix }}[evidence_label]" value="{{ $rule['evidence_label'] ?? '' }}" placeholder="Nota ou foto do óleo"></label>
                <label class="cc-field full"><span class="cc-label">Ou usar comprovante cadastrado</span><select class="cc-control" name="{{ $prefix }}[evidence_field]"><option value="">Criar automaticamente</option>@foreach($evidenceFields as $field)<option value="{{ $field->key }}" @selected(($rule['evidence_field'] ?? '') === $field->key)>{{ $field->label }}</option>@endforeach</select></label>
            </div>
        </div>
    </div>
    <button type="button" class="cc-action danger" data-remove-rule><i class="ph-fill ph-trash"></i><span>Remover termo</span></button>
</fieldset>
