@php
    $props = $record->properties ?? null;
@endphp

@if (empty($props))
    <div class="text-sm text-gray-500">N/D</div>
@else
    <pre style="white-space:pre-wrap;word-break:break-word;">{{ json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
@endif
