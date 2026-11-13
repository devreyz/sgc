@php
    $type = $field->type ?? 'text';
    $prefix = $fieldPrefix ?? 'order_data';
    $name = $prefix.'['.$field->key.']';
    $value = $fieldValue ?? old($prefix.'.'.$field->key, is_array($field->default_value) ? null : $field->default_value);
    if (in_array($type, ['time', 'date', 'datetime'], true) && is_string($value)) {
        $value = $type === 'time' ? substr($value, 0, 5) : ($type === 'date' ? substr($value, 0, 10) : str_replace(' ', 'T', substr($value, 0, 16)));
    }
    $numeric = in_array($type, ['integer', 'decimal', 'money', 'quantity', 'meter'], true);
    $required = $field->required && ! $field->conditional_rule;
@endphp
<label style="display:grid;gap:.35rem">
    <span>{{ $field->label }} @if($field->required)*@endif</span>
    @if($type === 'textarea')
        <textarea name="{{ $name }}" @required($required) placeholder="{{ $field->placeholder }}">{{ $value }}</textarea>
    @elseif($type === 'select')
        <select name="{{ $name }}" @required($required)>
            <option value="">Selecione</option>
            @foreach(($field->options ?? []) as $key => $option)
                @php($optionValue = is_int($key) ? $option : $key)
                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $option }}</option>
            @endforeach
        </select>
    @elseif($type === 'boolean')
        <select name="{{ $name }}" @required($required)><option value="0">Não</option><option value="1" @selected($value)>Sim</option></select>
    @elseif(in_array($type, ['image', 'file', 'signature'], true))
        <span style="color:var(--ui-color-text-secondary, #64748b)">Esta evidência será anexada durante a execução.</span>
    @else
        <input type="{{ $numeric ? 'number' : match($type) { 'date', 'time' => $type, 'datetime' => 'datetime-local', default => 'text' } }}" name="{{ $name }}" value="{{ $value }}" @required($required) @if($numeric) step="{{ $type === 'integer' ? '1' : 'any' }}" min="{{ $field->minimum ?? 0 }}" @if($field->maximum !== null) max="{{ $field->maximum }}" @endif @endif placeholder="{{ $field->placeholder }}">
    @endif
    @if($field->help)<small style="color:var(--ui-color-text-secondary, #64748b)">{{ $field->help }}</small>@endif
</label>
