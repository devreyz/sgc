@php
    $type = $field->type ?? 'text';
    $name = 'order_data['.$field->key.']';
    $value = old('order_data.'.$field->key, is_array($field->default_value) ? null : $field->default_value);
    $numeric = in_array($type, ['integer', 'decimal', 'money', 'quantity', 'meter'], true);
@endphp
<label style="display:grid;gap:.35rem">
    <span>{{ $field->label }} @if($field->required)*@endif</span>
    @if($type === 'textarea')
        <textarea name="{{ $name }}" @required($field->required) placeholder="{{ $field->placeholder }}">{{ $value }}</textarea>
    @elseif($type === 'select')
        <select name="{{ $name }}" @required($field->required)>
            <option value="">Selecione</option>
            @foreach(($field->options ?? []) as $key => $option)
                @php($optionValue = is_int($key) ? $option : $key)
                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $option }}</option>
            @endforeach
        </select>
    @elseif($type === 'boolean')
        <select name="{{ $name }}" @required($field->required)><option value="0">Não</option><option value="1" @selected($value)>Sim</option></select>
    @elseif(in_array($type, ['image', 'file', 'signature'], true))
        <span style="color:#64748b">Esta evidência será anexada durante a execução.</span>
    @else
        <input type="{{ $numeric ? 'number' : ($type === 'date' ? 'date' : ($type === 'datetime' ? 'datetime-local' : 'text')) }}" name="{{ $name }}" value="{{ $value }}" @required($field->required) @if($numeric) step="{{ $type === 'integer' ? '1' : 'any' }}" min="{{ $field->minimum ?? 0 }}" @if($field->maximum !== null) max="{{ $field->maximum }}" @endif @endif placeholder="{{ $field->placeholder }}">
    @endif
    @if($field->help)<small style="color:#64748b">{{ $field->help }}</small>@endif
</label>
