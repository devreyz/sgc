@extends('layouts.bento')

@section('title', 'Preferencias de notificacao')
@section('page-title', 'Preferencias de notificacao')
@section('user-role', 'Administracao')

@section('content')
<style>
    .settings-shell{max-width:1080px;margin:0 auto}.settings-form{display:grid;gap:1rem}.event-group{display:grid;gap:.6rem}.event-group-title{font-size:.72rem;text-transform:uppercase;color:var(--color-text-secondary);font-weight:800;margin:.7rem 0 0}.event-row{display:grid;grid-template-columns:minmax(210px,1.3fr) auto auto 140px minmax(220px,1fr);align-items:center;gap:1rem;padding:1rem}.event-row h3{font-size:.86rem;margin:0 0 .18rem}.event-row p{font-size:.72rem;color:var(--color-text-secondary);margin:0;line-height:1.35}.event-switch{display:flex;align-items:center;gap:.4rem;font-size:.75rem;font-weight:650}.event-switch input{width:1.05rem;height:1.05rem;accent-color:var(--color-primary)}.event-select{width:100%;border:1px solid var(--color-border);background:var(--color-bg);color:var(--color-text);border-radius:7px;padding:.55rem;font-size:.76rem}.roles-list{display:flex;gap:.35rem;flex-wrap:wrap}.role-check{font-size:.68rem;display:flex;align-items:center;gap:.25rem;padding:.3rem .42rem;border:1px solid var(--color-border);border-radius:6px}.role-check input{accent-color:var(--color-primary)}.settings-save{position:sticky;bottom:calc(var(--app-bottom-nav-height,0px) + .75rem);display:flex;justify-content:flex-end;padding:.8rem;background:color-mix(in srgb,var(--color-surface) 94%,transparent);backdrop-filter:blur(10px);z-index:5}.settings-save button{border:0;background:var(--color-primary);color:#fff;padding:.7rem 1rem;border-radius:7px;font-weight:750}@media(max-width:850px){.event-row{grid-template-columns:1fr 1fr}.event-copy,.roles-list{grid-column:1/-1}.event-row .event-select{grid-column:1/-1}} 
</style>

@php($grouped = collect($catalog)->groupBy('group'))
<div class="settings-shell">
    <form method="POST" action="{{ route('notifications.settings.update', ['tenant' => $tenant]) }}" class="settings-form">
        @csrf @method('PUT')
        @foreach($grouped as $group => $events)
            <section class="event-group">
                <h2 class="event-group-title">{{ $group }}</h2>
                @foreach($events as $key => $event)
                    @php($saved = $preferences->get($key))
                    <article class="bento-card event-row">
                        <div class="event-copy"><h3>{{ $event['label'] }}</h3><p>{{ $event['description'] }}</p></div>
                        <label class="event-switch"><input type="hidden" name="events[{{ $key }}][database_enabled]" value="0"><input type="checkbox" name="events[{{ $key }}][database_enabled]" value="1" @checked($saved?->database_enabled ?? $event['databaseDefault'])> Central</label>
                        <label class="event-switch" title="{{ $event['pushAllowed'] ? 'Enviar ao dispositivo' : 'Push bloqueado para eventos editaveis' }}"><input type="hidden" name="events[{{ $key }}][push_enabled]" value="0"><input type="checkbox" name="events[{{ $key }}][push_enabled]" value="1" @checked($event['pushAllowed'] && ($saved?->push_enabled ?? $event['pushDefault'])) @disabled(!$event['pushAllowed'])> Push</label>
                        <select class="event-select" name="events[{{ $key }}][priority]" aria-label="Prioridade de {{ $event['label'] }}">@foreach(\App\Support\NotificationEventCatalog::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(($saved?->priority ?? $event['priority']) === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select>
                        <div class="roles-list">@foreach($roleOptions as $role => $label)<label class="role-check"><input type="checkbox" name="events[{{ $key }}][roles][]" value="{{ $role }}" @checked(in_array($role, $saved?->recipient_roles ?? $event['roles'], true))>{{ $label }}</label>@endforeach</div>
                    </article>
                @endforeach
            </section>
        @endforeach
        <div class="settings-save"><button type="submit">Salvar preferencias</button></div>
    </form>
</div>
@endsection
