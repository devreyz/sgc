<?php

namespace App\Services\Services;

use App\Models\ServiceExecution;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ServiceFieldValidator
{
    public function validate(ServiceExecution $execution, string $phase, array $values): array
    {
        $fields = collect(data_get($execution->catalog_snapshot, 'fields', []))->where('phase', $phase);
        $errors = [];
        foreach ($fields as $field) {
            if (! $this->isVisible($field, $values + ($execution->values ?? []))) {
                continue;
            }
            $key = $field['key'];
            $value = Arr::get($values, $key, Arr::get($execution->values ?? [], $key));
            if (in_array($field['type'] ?? '', ['image', 'file', 'signature'], true)) {
                if (($field['required'] ?? false) && ! $execution->evidences()->where('field_key', $key)->exists()) {
                    $errors[$key] = "A evidência {$field['label']} é obrigatória.";
                }

                continue;
            }
            if (($field['required'] ?? false) && ($value === null || $value === '')) {
                $errors[$key] = "O campo {$field['label']} é obrigatório.";

                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (in_array($field['type'], ['integer', 'decimal', 'money', 'quantity', 'meter'], true) && ! is_numeric($value)) {
                $errors[$key] = "O campo {$field['label']} deve ser numérico.";
            }
            if (isset($field['minimum']) && is_numeric($value) && (float) $value < (float) $field['minimum']) {
                $errors[$key] = "O campo {$field['label']} deve ser no mínimo {$field['minimum']}.";
            }
            if (isset($field['maximum']) && is_numeric($value) && (float) $value > (float) $field['maximum']) {
                $errors[$key] = "O campo {$field['label']} deve ser no máximo {$field['maximum']}.";
            }
            if ($field['type'] === 'select' && ! in_array($value, array_keys($field['options'] ?? []), true) && ! in_array($value, $field['options'] ?? [], true)) {
                $errors[$key] = "Valor inválido para {$field['label']}.";
            }
        }
        $config = data_get($execution->catalog_snapshot, 'execution_config', []);
        foreach (($config['meters'] ?? []) as $meter) {
            $start = Arr::get($values + ($execution->values ?? []), $meter['start_key'] ?? '');
            $end = Arr::get($values + ($execution->values ?? []), $meter['end_key'] ?? '');
            if (($meter['increasing'] ?? true) && is_numeric($start) && is_numeric($end) && (float) $end < (float) $start) {
                $errors[$meter['end_key']] = 'A medição final não pode ser menor que a inicial.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    private function isVisible(array $field, array $values): bool
    {
        $rule = $field['conditional_rule'] ?? null;
        if (! $rule) {
            return true;
        }
        $actual = Arr::get($values, $rule['field'] ?? '');
        $expected = $rule['value'] ?? null;

        return match ($rule['operator'] ?? 'equals') {
            'equals' => $actual == $expected, 'not_equals' => $actual != $expected,
            'is_true' => (bool) $actual, 'is_false' => ! (bool) $actual,
            'greater_than' => $actual > $expected, 'less_than' => $actual < $expected,
            default => false,
        };
    }
}
