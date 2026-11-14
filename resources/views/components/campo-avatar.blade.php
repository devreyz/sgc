@props(['photo' => null, 'name', 'seed'])

@php
    $colors = ['success', 'info', 'violet', 'cyan', 'warning'];
    $tone = $colors[hexdec(substr(md5((string) $seed), 0, 4)) % count($colors)];
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_substr($parts[0] ?? '', 0, 1);
    if (count($parts) > 1) {
        $initials .= mb_substr($parts[count($parts) - 1], 0, 1);
    }
@endphp

<span {{ $attributes->class('campo-avatar') }} data-avatar-tone="{{ $tone }}" aria-hidden="true">
    @if($initials !== '')
        <span>{{ mb_strtoupper($initials) }}</span>
    @else
        <i class="ph ph-user" aria-hidden="true"></i>
    @endif
    @if($photo)
        <img src="{{ $photo }}" alt="" data-avatar-photo>
    @endif
</span>
