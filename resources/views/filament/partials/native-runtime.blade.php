@php
    $tenantSlug = session('tenant_slug');
    $runtimeConfig = auth()->check() ? [
        'nativePushStoreUrl' => route('notifications.push.devices.store'),
        'nativePushDestroyUrl' => route('notifications.push.devices.destroy'),
        'nativePushBindingScope' => hash_hmac('sha256', auth()->id().'|'.request()->session()->getId(), (string) config('app.key')),
    ] : [];

    if ($tenantSlug) {
        $runtimeConfig['unreadCountUrl'] = route('notifications.unread-count', ['tenant' => $tenantSlug]);
    }
@endphp
<script>
    window.SgcPwaConfig = @json($runtimeConfig);
</script>
@vite('resources/js/app.js')
