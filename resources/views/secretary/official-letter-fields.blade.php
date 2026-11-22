@php
    $layoutValues = array_replace(\App\Services\OfficialLetterService::LAYOUT, $record['layout'] ?? []);
    $locked = (bool) ($record['signed'] ?? false);
    $layoutSections = [
        'Formato do texto' => [
            'top' => ['Margem superior (mm)', 15, 45], 'bottom' => ['Margem inferior (mm)', 20, 55],
            'left' => ['Margem esquerda (mm)', 10, 45], 'right' => ['Margem direita (mm)', 10, 45],
            'font' => ['Fonte', ['sans' => 'Sem serifa', 'serif' => 'Com serifa']],
            'font_size' => ['Tamanho (pt)', 9, 18], 'line_height' => ['Entrelinhas', 1, 2, 0.1],
            'spacing' => ['Após parágrafo (pt)', 0, 18], 'indent' => ['Recuo inicial (mm)', 0, 35],
        ],
        'Cabeçalho institucional' => [
            'header_name' => ['Nome institucional'], 'acronym' => ['Sigla da organização', 24], 'sector' => ['Sigla do setor para numeração', 24],
            'department' => ['Setor ou departamento'], 'logo' => ['Usar logo cadastrada'],
            'logo_width' => ['Largura máxima da logo (mm)', 8, 24], 'header_spacing' => ['Espaço abaixo (mm)', 0, 15],
            'header_alignment' => ['Alinhar', ['left' => 'À esquerda', 'center' => 'Ao centro', 'right' => 'À direita']],
            'repeat_header' => ['Repetir em todas as páginas'], 'separator' => ['Exibir linha separadora'],
        ],
        'Rodapé e paginação' => [
            'footer' => ['Texto institucional', 240], 'address' => ['Endereço'], 'registration' => ['CNPJ'],
            'phone' => ['Telefone'], 'email' => ['E-mail'], 'website' => ['Site'], 'page_numbers' => ['Numerar a partir da segunda página'],
        ],
    ];
@endphp

@if (!empty($letterFieldsOnly))
    <details class="se-panel se-letter-details" @if (!$editing) open @endif>
        <summary>Dados do ofício <i class="ph ph-caret-down" aria-hidden="true"></i></summary>
        <div class="se-panel-body se-field-grid">
            @foreach (\App\Services\OfficialLetterService::FIELDS as $field => $label)
                <div @class(['se-field', 'se-full' => in_array($field, ['subject', 'recipient_address'])])>
                    <label for="letter-{{ $field }}">{{ $label }}</label>
                    @if ($field === 'recipient_address')
                        <textarea id="letter-{{ $field }}" class="se-control" maxlength="360" rows="2" data-letter="{{ $field }}" @disabled($locked)>{{ $record['letter'][$field] ?? '' }}</textarea>
                    @else
                        <input id="letter-{{ $field }}" class="se-control" type="{{ $field === 'date' ? 'date' : 'text' }}" maxlength="220"
                            data-letter="{{ $field }}" value="{{ $record['letter'][$field] ?? '' }}" @disabled($locked)>
                    @endif
                </div>
            @endforeach
            @if (!$locked && $tenant->legal_representative_name)
                <button class="se-btn se-full" type="button" id="se-signatory" data-name="{{ $tenant->legal_representative_name }}"
                    data-position="{{ $tenant->legal_representative_role }}">Usar representante cadastrado</button>
            @endif
        </div>
    </details>
@else
<div id="se-institutional-layout" @if (!$isOfficialDocument && ($record['type'] ?? null) !== 'official_letter') hidden @endif>
    @foreach ($layoutSections as $section => $fields)
        <details class="se-panel">
            <summary>{{ $section }} <i class="ph ph-caret-down" aria-hidden="true"></i></summary>
            <div class="se-panel-body se-field-grid">
                @foreach ($fields as $field => [$label])
                    @php($options = $fields[$field])
                    @if (is_bool($layoutValues[$field]))
                        <label class="se-check se-full"><input type="checkbox" data-layout="{{ $field }}" @checked($layoutValues[$field]) @disabled($locked)> {{ $label }}</label>
                    @else
                        <div @class(['se-field', 'se-full' => !isset($options[2])])>
                            <label for="letter-layout-{{ $field }}">{{ $label }}</label>
                            @if (is_array($options[1] ?? null))
                                <select id="letter-layout-{{ $field }}" class="se-control" data-layout="{{ $field }}" @disabled($locked)>
                                    @foreach ($options[1] as $value => $name)
                                        <option value="{{ $value }}" @selected($layoutValues[$field] === $value)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            @elseif (isset($options[2]))
                                <input id="letter-layout-{{ $field }}" type="number" class="se-control" data-layout="{{ $field }}"
                                    value="{{ $layoutValues[$field] }}" min="{{ $options[1] }}" max="{{ $options[2] }}" step="{{ $options[3] ?? 1 }}" required @disabled($locked)>
                            @else
                                <input id="letter-layout-{{ $field }}" class="se-control" data-layout="{{ $field }}"
                                    value="{{ $layoutValues[$field] }}" maxlength="{{ $options[1] ?? 120 }}" @disabled($locked)>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </details>
    @endforeach
    @if ($isTemplate)
        <label class="se-check"><input type="checkbox" data-layout="default" @checked($layoutValues['default'])> Usar como modelo padrão de novos ofícios</label>
    @endif
</div>
@endif
