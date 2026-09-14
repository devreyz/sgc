<?php

namespace App\Filament\Support;

use Filament\Forms\Components as Fields;

class ServiceExecutionForm
{
    public static function fields(array $snapshot, array $phases, string $root = 'values'): array
    {
        $schema = [];
        foreach ($snapshot['fields'] ?? [] as $field) {
            if (! in_array($field['phase'], $phases, true) || ($field['visible_to_management'] ?? true) === false || in_array($field['type'], ['image', 'file', 'signature'], true)) {
                continue;
            }
            $name = $root.'.'.$field['key'];
            $component = match ($field['type']) {
                'textarea' => Fields\Textarea::make($name),
                'boolean' => Fields\Toggle::make($name),
                'date' => Fields\DatePicker::make($name),
                'datetime' => Fields\DateTimePicker::make($name),
                'select' => Fields\Select::make($name)->options($field['options'] ?? []),
                default => Fields\TextInput::make($name),
            };
            if (in_array($field['type'], ['integer', 'decimal', 'quantity', 'money', 'meter'], true)) {
                $component->numeric()->minValue($field['minimum'] ?? 0)->maxValue($field['maximum'] ?? 999999999.99)->suffix($field['unit'] ?? null);
            }
            $schema[] = $component->label($field['label'])->required($field['required'] ?? false)->helperText($field['help'] ?? null);
        }

        return $schema;
    }
}
