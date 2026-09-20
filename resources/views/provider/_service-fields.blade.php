@php
    $allFields = collect(
        data_get(
            $execution->catalog_snapshot,
            'fields',
            []
        )
    );

    $isOperator = (bool) (
        $operator
        ?? false
    );

    $canUse = static function (array $field) use ($isOperator): bool {
        if ($isOperator) {
            return (bool) (
                $field['visible_to_management']
                ?? true
            );
        }

        return (bool) (
            ($field['visible_to_provider'] ?? false)
            && ($field['editable_by_provider'] ?? false)
        );
    };

    $phaseFields = $allFields
        ->where('phase', $phase)
        ->filter($canUse);

    $evidenceTypes = [
        'image',
        'file',
        'signature',
    ];

    $evidenceFields = $phaseFields->filter(
        static fn (array $field): bool =>
            in_array(
                $field['type'] ?? null,
                $evidenceTypes,
                true
            )
    );

    $dataFields = $phaseFields->reject(
        static fn (array $field): bool =>
            in_array(
                $field['type'] ?? null,
                $evidenceTypes,
                true
            )
    );

    $linkedEvidence = $evidenceFields
        ->filter(
            static fn (array $field): bool =>
                filled(
                    $field['evidence_for_field']
                    ?? null
                )
        )
        ->keyBy('evidence_for_field');

    $inferredKeys = collect();

    foreach (
        $evidenceFields->filter(
            static fn (array $field): bool =>
                blank(
                    $field['evidence_for_field']
                    ?? null
                )
        )
        as $evidence
    ) {
        $evidenceLabel = str(
            $evidence['label'] ?? ''
        )
            ->ascii()
            ->lower()
            ->toString();

        $keyword = collect(
            [
                'inicial',
                'inicio',
                'final',
                'fim',
            ]
        )->first(
            static fn (string $word): bool =>
                str_contains(
                    $evidenceLabel,
                    $word
                )
        );

        $target = $keyword
            ? $dataFields->first(
                static fn (array $field): bool =>
                    str_contains(
                        str(
                            $field['label'] ?? ''
                        )
                            ->ascii()
                            ->lower()
                            ->toString(),
                        $keyword
                    )
            )
            : null;

        if (
            $target
            && ! $linkedEvidence->has(
                $target['key']
            )
        ) {
            $linkedEvidence->put(
                $target['key'],
                $evidence
            );

            $inferredKeys->push(
                $evidence['key']
            );
        }
    }

    $standaloneEvidence = $evidenceFields
        ->filter(
            static fn (array $field): bool =>
                blank(
                    $field['evidence_for_field']
                    ?? null
                )
                && ! $inferredKeys->contains(
                    $field['key']
                )
        );
@endphp

@foreach($dataFields as $field)
    @php
        $fieldKey = (string) (
            $field['key']
            ?? ''
        );

        $fieldType = (string) (
            $field['type']
            ?? 'text'
        );

        $fieldLabel = (string) (
            $field['label']
            ?? $fieldKey
        );

        $fieldRequired = (bool) (
            $field['required']
            ?? false
        );

        $fieldUnit =
            $field['unit']
            ?? null;

        $fieldHelp =
            $field['help']
            ?? null;

        $fieldOptions =
            $field['options']
            ?? [];

        $value = data_get(
            $execution->values,
            $fieldKey
        );

        if (
            $fieldType === 'datetime'
            && filled($value)
        ) {
            try {
                $value = \Illuminate\Support\Carbon::parse(
                    $value
                )->format('Y-m-d\TH:i');
            } catch (\Throwable $exception) {
                // Mantém o valor original.
            }
        } elseif (
            $fieldType === 'date'
            && filled($value)
        ) {
            try {
                $value = \Illuminate\Support\Carbon::parse(
                    $value
                )->format('Y-m-d');
            } catch (\Throwable $exception) {
                // Mantém o valor original.
            }
        }

        $proof = $linkedEvidence->get(
            $fieldKey
        );

        $conditionJson = json_encode(
            $field['conditional_rule']
            ?? null,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        if ($conditionJson === false) {
            $conditionJson = 'null';
        }

        $numericTypes = [
            'integer',
            'decimal',
            'money',
            'quantity',
            'meter',
        ];

        $decimalTypes = [
            'decimal',
            'money',
            'quantity',
            'meter',
        ];

        $inputType = in_array(
            $fieldType,
            $numericTypes,
            true
        )
            ? 'number'
            : (
                $fieldType === 'date'
                    ? 'date'
                    : (
                        $fieldType === 'datetime'
                            ? 'datetime-local'
                            : 'text'
                    )
            );

        $inputStep = in_array(
            $fieldType,
            $decimalTypes,
            true
        )
            ? 'any'
            : '1';
    @endphp

    <div
        class="svc-field"
        data-field-key="{{ $fieldKey }}"
        data-condition="{{ $conditionJson }}"
    >
        <label>
            <span>
                {{ $fieldLabel }}

                @if($fieldRequired)
                    <b class="svc-required">*</b>
                @endif

                @if($fieldUnit)
                    <small class="svc-field-unit">
                        ({{ $fieldUnit }})
                    </small>
                @endif
            </span>

            @if($fieldType === 'textarea')
                <textarea
                    name="values[{{ $fieldKey }}]"
                    @required($fieldRequired)
                >{{ $value }}</textarea>
            @elseif($fieldType === 'boolean')
                <select
                    name="values[{{ $fieldKey }}]"
                    @required($fieldRequired)
                >
                    @unless($fieldRequired)
                        <option value="">
                            Não informado
                        </option>
                    @endunless

                    <option
                        value="0"
                        @selected((string) $value === '0')
                    >
                        Não
                    </option>

                    <option
                        value="1"
                        @selected((string) $value === '1')
                    >
                        Sim
                    </option>
                </select>
            @elseif(
                in_array(
                    $fieldType,
                    [
                        'select',
                        'member',
                        'associate',
                        'provider',
                        'asset',
                    ],
                    true
                )
                && ! empty($fieldOptions)
            )
                <select
                    name="values[{{ $fieldKey }}]"
                    @required($fieldRequired)
                >
                    <option value="">
                        Selecione
                    </option>

                    @foreach(
                        $fieldOptions
                        as $optionKey => $option
                    )
                        @php
                            $optionValue = is_int(
                                $optionKey
                            )
                                ? $option
                                : $optionKey;
                        @endphp

                        <option
                            value="{{ $optionValue }}"
                            @selected(
                                (string) $value
                                === (string) $optionValue
                            )
                        >
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            @else
                <input
                    type="{{ $inputType }}"
                    name="values[{{ $fieldKey }}]"
                    value="{{ $value }}"
                    placeholder="{{ $field['placeholder'] ?? '' }}"
                    @if(isset($field['minimum']))
                        min="{{ $field['minimum'] }}"
                    @endif
                    @if(isset($field['maximum']))
                        max="{{ $field['maximum'] }}"
                    @endif
                    step="{{ $inputStep }}"
                    @if($fieldType === 'money')
                        inputmode="decimal"
                    @endif
                    @required($fieldRequired)
                >
            @endif

            @if($fieldHelp)
                <small class="svc-field-help">
                    {{ $fieldHelp }}
                </small>
            @endif
        </label>

        @if($proof)
            @include(
                'provider._service-evidence-input',
                [
                    'field' => $proof,
                    'execution' => $execution,
                    'tenantSlug' => $tenantSlug ?? null,
                ]
            )
        @endif
    </div>
@endforeach

@foreach($standaloneEvidence as $field)
    <div class="svc-field">
        @include(
            'provider._service-evidence-input',
            [
                'field' => $field,
                'execution' => $execution,
                'tenantSlug' => $tenantSlug ?? null,
            ]
        )
    </div>
@endforeach
