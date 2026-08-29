@extends('layouts.bento')

@section('title', 'Notificações')
@section('page-title', 'Notificações')
@section('user-role', 'Central de avisos')

@php
    $pageUnreadCount = $notifications
        ->getCollection()
        ->whereNull('read_at')
        ->count();
@endphp

@section('content')
<style>
    .notifications-shell {
        --notify-green: var(--color-primary, #22c55e);
        --notify-green-dark: var(--color-primary-dark, #16a34a);
        --notify-surface: var(--color-surface, #ffffff);
        --notify-soft: var(--color-surface-soft, #f8faf9);
        --notify-muted: var(--color-surface-muted, #eef4f0);
        --notify-border: var(--color-border, #dce6df);
        --notify-border-strong: var(--color-border-strong, #c8d6cd);
        --notify-text: var(--color-text, #102018);
        --notify-secondary: var(--color-text-secondary, #52645a);
        --notify-faded: var(--color-text-muted, #809087);
        --notify-danger: var(--color-danger, #dc2626);
        --notify-warning: var(--color-warning, #d97706);
        --notify-info: var(--color-info, #0284c7);
        --notify-shadow-sm: 0 5px 18px rgba(15, 35, 24, .055);
        --notify-shadow: 0 12px 34px rgba(15, 35, 24, .075);

        display: grid;
        width: min(100%, 1040px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .78rem;
        margin: 0 auto;
        padding-bottom: 1.25rem;
        color: var(--notify-text);
    }

    .notifications-shell *,
    .notifications-shell *::before,
    .notifications-shell *::after {
        box-sizing: border-box;
    }

    .notifications-tools {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .72rem;
        align-items: stretch;
    }

    .push-control,
    .notifications-actions,
    .notifications-list-card {
        border: 1px solid var(--notify-border);
        background: var(--notify-surface);
        box-shadow: var(--notify-shadow-sm);
    }

    .push-control {
        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .68rem;
        align-items: center;
        padding: .7rem .75rem;
        overflow: hidden;
        border-left: 4px solid var(--notify-green-dark);
        border-radius: 13px;
        background:
            linear-gradient(
                90deg,
                rgba(236, 253, 245, .72),
                rgba(255, 255, 255, .98) 42%
            ),
            var(--notify-surface);
    }

    .push-control-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 11px;
        background: #ecfdf5;
        color: var(--notify-green-dark);
    }

    .push-control-icon svg {
        width: 18px;
        height: 18px;
    }

    .push-control-copy {
        min-width: 0;
    }

    .push-control-copy strong,
    .push-control-copy span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .push-control-copy strong {
        color: var(--notify-text);
        font-size: .75rem;
        font-weight: 810;
    }

    .push-control-copy span {
        margin-top: .12rem;
        color: var(--notify-faded);
        font-size: .61rem;
    }

    .push-status-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        margin-right: .28rem;
        border-radius: 50%;
        background: var(--notify-faded);
        vertical-align: middle;
    }

    .push-control.is-enabled .push-status-dot {
        background: var(--notify-green);
        box-shadow: 0 0 0 4px rgba(34, 197, 94, .11);
    }

    .push-primary-action,
    .notifications-button,
    .notification-filter,
    .notification-open,
    .push-disable-action {
        font: inherit;
    }

    .push-primary-action {
        display: inline-flex;
        min-height: 36px;
        align-items: center;
        justify-content: center;
        gap: .34rem;
        padding: .4rem .58rem;
        border: 1px solid var(--notify-green-dark);
        border-radius: 9px;
        background: linear-gradient(
            135deg,
            var(--notify-green),
            var(--notify-green-dark)
        );
        color: #fff;
        cursor: pointer;
        font-size: .61rem;
        font-weight: 790;
        white-space: nowrap;
        box-shadow: 0 6px 14px rgba(22, 163, 74, .14);
    }

    .push-primary-action svg {
        width: 14px;
        height: 14px;
    }

    .push-primary-action:disabled {
        cursor: not-allowed;
        opacity: .55;
    }

    .push-disable-action {
        position: absolute;
        right: .72rem;
        bottom: .24rem;
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .16rem .22rem;
        border: 0;
        background: transparent;
        color: var(--notify-faded);
        cursor: pointer;
        font-size: .52rem;
        font-weight: 670;
        text-decoration: underline;
        text-decoration-color: transparent;
        text-underline-offset: 2px;
    }

    .push-disable-action:hover,
    .push-disable-action:focus-visible {
        color: var(--notify-danger);
        outline: none;
        text-decoration-color: currentColor;
    }

    .push-disable-action svg {
        width: 11px;
        height: 11px;
    }

    .notifications-actions {
        display: flex;
        align-items: center;
        gap: .38rem;
        padding: .48rem;
        border-radius: 13px;
    }

    .notification-filter,
    .notifications-button {
        display: inline-flex;
        min-height: 36px;
        align-items: center;
        justify-content: center;
        gap: .32rem;
        padding: .4rem .52rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: transparent;
        color: var(--notify-secondary);
        cursor: pointer;
        font-size: .6rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .notification-filter svg,
    .notifications-button svg {
        width: 14px;
        height: 14px;
    }

    .notification-filter:hover,
    .notification-filter:focus-visible,
    .notifications-button:hover,
    .notifications-button:focus-visible {
        border-color: var(--notify-border);
        background: var(--notify-soft);
        color: var(--notify-text);
        outline: none;
    }

    .notification-filter.active {
        border-color: rgba(34, 197, 94, .24);
        background: #ecfdf5;
        color: var(--notify-green-dark);
    }

    .notification-filter-count {
        display: inline-grid;
        min-width: 19px;
        height: 19px;
        place-items: center;
        padding: 0 .23rem;
        border-radius: 999px;
        background: var(--notify-muted);
        color: var(--notify-secondary);
        font-size: .52rem;
        font-weight: 850;
    }

    .notification-filter.active .notification-filter-count {
        background: rgba(34, 197, 94, .13);
        color: var(--notify-green-dark);
    }

    .notifications-button {
        border-color: var(--notify-border);
        background: var(--notify-surface);
    }

    .notifications-button:disabled {
        cursor: not-allowed;
        opacity: .5;
    }

    .notifications-list-card {
        overflow: hidden;
        border-radius: 15px;
        box-shadow: var(--notify-shadow);
    }

    .notifications-list-head {
        display: flex;
        min-height: 62px;
        align-items: center;
        justify-content: space-between;
        gap: .7rem;
        padding: .68rem .78rem;
        border-bottom: 1px solid var(--notify-border);
        background: linear-gradient(
            180deg,
            var(--notify-soft),
            var(--notify-surface)
        );
    }

    .notifications-list-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .58rem;
    }

    .notifications-list-icon {
        display: grid;
        width: 36px;
        height: 36px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 10px;
        background: #ecfdf5;
        color: var(--notify-green-dark);
    }

    .notifications-list-icon svg {
        width: 17px;
        height: 17px;
    }

    .notifications-list-head h2 {
        margin: 0;
        color: var(--notify-text);
        font-size: .88rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .notifications-list-head p {
        margin: .14rem 0 0;
        color: var(--notify-faded);
        font-size: .59rem;
    }

    .notifications-list {
        display: grid;
        gap: .52rem;
        padding: .68rem;
    }

    .notification-item {
        --notification-tone: var(--notify-info);
        --notification-soft: #eff6ff;

        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .68rem;
        align-items: start;
        padding: .72rem;
        overflow: hidden;
        border: 1px solid var(--notify-border);
        border-radius: 12px;
        background: var(--notify-surface);
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .notification-item:hover {
        border-color: color-mix(
            in srgb,
            var(--notification-tone) 28%,
            var(--notify-border)
        );
        box-shadow: 0 9px 22px rgba(15, 35, 24, .065);
        transform: translateY(-1px);
    }

    .notification-item.unread {
        border-left: 4px solid var(--notification-tone);
        background:
            linear-gradient(
                90deg,
                color-mix(
                    in srgb,
                    var(--notification-soft) 72%,
                    #fff
                ),
                #fff 42%
            );
    }

    .notification-item.unread::after {
        position: absolute;
        top: .62rem;
        right: .62rem;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--notification-tone);
        content: "";
        box-shadow: 0 0 0 4px color-mix(
            in srgb,
            var(--notification-tone) 11%,
            transparent
        );
    }

    .notification-item.priority-critical,
    .notification-item.priority-high {
        --notification-tone: var(--notify-danger);
        --notification-soft: #fef2f2;
    }

    .notification-item.type-payment,
    .notification-item.type-financial,
    .notification-item.type-wallet {
        --notification-tone: #7c3aed;
        --notification-soft: #f5f3ff;
    }

    .notification-item.type-delivery,
    .notification-item.type-distribution,
    .notification-item.type-project {
        --notification-tone: var(--notify-green-dark);
        --notification-soft: #ecfdf5;
    }

    .notification-item.type-security,
    .notification-item.type-permission,
    .notification-item.type-approval,
    .notification-item.type-request {
        --notification-tone: var(--notify-warning);
        --notification-soft: #fffbeb;
    }

    .notification-item.type-document,
    .notification-item.type-report {
        --notification-tone: #475569;
        --notification-soft: #f1f5f9;
    }

    .notification-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 11px;
        background: var(--notification-soft);
        color: var(--notification-tone);
    }

    .notification-icon svg {
        width: 18px;
        height: 18px;
    }

    .notification-copy {
        min-width: 0;
        padding-right: .25rem;
    }

    .notification-copy h3 {
        margin: 0;
        overflow: hidden;
        color: var(--notify-text);
        font-size: .77rem;
        font-weight: 810;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .notification-copy p {
        display: -webkit-box;
        margin: .22rem 0 0;
        overflow: hidden;
        color: var(--notify-secondary);
        font-size: .65rem;
        line-height: 1.48;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .notification-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .3rem .48rem;
        margin-top: .45rem;
        color: var(--notify-faded);
        font-size: .56rem;
        font-weight: 650;
    }

    .notification-meta > span {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
    }

    .notification-meta svg {
        width: 11px;
        height: 11px;
    }

    .notification-priority {
        padding: .16rem .3rem;
        border-radius: 999px;
        background: var(--notification-soft);
        color: var(--notification-tone);
        font-size: .5rem;
        font-weight: 840;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .notification-delivery {
        padding: .16rem .34rem;
        border-radius: 999px;
        background: var(--notify-muted);
    }

    .notification-delivery.is-delivered { color: var(--notify-green-dark); }
    .notification-delivery.is-failed,
    .notification-delivery.is-no_device { color: var(--notify-danger); }
    .notification-delivery.is-pending { color: var(--notify-warning); }

    .notification-retry {
        border: 0;
        background: transparent;
        color: inherit;
        cursor: pointer;
        font: inherit;
        font-weight: 800;
        text-decoration: underline;
    }

    .notification-open {
        display: inline-flex;
        min-height: 34px;
        align-items: center;
        justify-content: center;
        gap: .28rem;
        align-self: center;
        padding: .38rem .48rem;
        border: 1px solid color-mix(
            in srgb,
            var(--notification-tone) 23%,
            var(--notify-border)
        );
        border-radius: 9px;
        background: var(--notify-surface);
        color: var(--notification-tone);
        font-size: .57rem;
        font-weight: 790;
        text-decoration: none;
        white-space: nowrap;
    }

    .notification-open:hover,
    .notification-open:focus-visible {
        background: var(--notification-tone);
        color: #fff;
        outline: none;
    }

    .notification-open svg {
        width: 13px;
        height: 13px;
    }

    .notifications-empty,
    .notifications-filter-empty {
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.8rem .9rem;
        color: var(--notify-secondary);
        text-align: center;
    }

    .notifications-empty-icon {
        display: grid;
        width: 52px;
        height: 52px;
        place-items: center;
        margin: 0 auto .58rem;
        border-radius: 15px;
        background: var(--notify-muted);
        color: var(--notify-faded);
    }

    .notifications-empty-icon svg {
        width: 24px;
        height: 24px;
    }

    .notifications-empty strong,
    .notifications-filter-empty strong {
        display: block;
        color: var(--notify-text);
        font-size: .78rem;
        font-weight: 820;
    }

    .notifications-empty p,
    .notifications-filter-empty p {
        max-width: 340px;
        margin: .2rem auto 0;
        color: var(--notify-faded);
        font-size: .62rem;
        line-height: 1.5;
    }

    .notifications-filter-empty {
        min-height: 180px;
        border: 1px dashed var(--notify-border-strong);
        border-radius: 11px;
        background: var(--notify-soft);
    }

    .notifications-pagination {
        padding: 0 .68rem .68rem;
    }

    @media (max-width: 820px) {
        .notifications-tools {
            grid-template-columns: 1fr;
        }

        .notifications-actions {
            justify-content: space-between;
        }
    }

    @media (max-width: 620px) {
        .notifications-shell {
            gap: .66rem;
        }

        .push-control {
            grid-template-columns: auto minmax(0, 1fr);
            padding: .64rem;
            border-radius: 12px;
        }

        .push-primary-action {
            grid-column: 1 / -1;
            width: 100%;
        }

        .push-disable-action {
            position: static;
            grid-column: 1 / -1;
            justify-self: end;
            margin-top: -.18rem;
        }

        .notifications-actions {
            overflow-x: auto;
            justify-content: flex-start;
            padding: .42rem;
            scrollbar-width: none;
        }

        .notifications-actions::-webkit-scrollbar {
            display: none;
        }

        .notifications-list-card {
            border-radius: 13px;
        }

        .notifications-list-head {
            min-height: 0;
            align-items: flex-start;
            flex-direction: column;
            padding: .62rem;
        }

        .notifications-list {
            gap: .46rem;
            padding: .58rem;
        }

        .notification-item {
            grid-template-columns: auto minmax(0, 1fr);
            gap: .58rem;
            padding: .64rem;
            border-radius: 11px;
        }

        .notification-open {
            grid-column: 1 / -1;
            width: 100%;
        }

        .notification-item.unread::after {
            top: .52rem;
            right: .52rem;
        }

        .notifications-pagination {
            padding: 0 .58rem .58rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<div class="notifications-shell">
    <div class="notifications-tools">
        <section
            class="notifications-actions"
            aria-label="Filtros e ações das notificações"
        >
            <button
                type="button"
                class="notification-filter active"
                data-notification-filter="all"
                aria-pressed="true"
            >
                <i data-lucide="inbox"></i>
                Todas

                <span class="notification-filter-count">
                    {{ $notifications->total() }}
                </span>
            </button>

            <button
                type="button"
                class="notification-filter"
                data-notification-filter="unread"
                aria-pressed="false"
            >
                <i data-lucide="mail"></i>
                Não lidas

                <span
                    class="notification-filter-count"
                    id="unread-filter-count"
                >
                    {{ $pageUnreadCount }}
                </span>
            </button>

            <button type="button" class="notification-filter" data-notification-filter="important" aria-pressed="false">
                <i data-lucide="badge-alert"></i>
                Importantes
            </button>

            <button type="button" class="notification-filter" data-notification-filter="read" aria-pressed="false">
                <i data-lucide="mail-check"></i>
                Lidas
            </button>

            @if($pageUnreadCount > 0)
                <button
                    type="button"
                    class="notifications-button"
                    id="mark-all-read"
                >
                    <i data-lucide="mail-check"></i>
                    Marcar como lidas
                </button>
            @endif
        </section>
    </div>

    <section class="notifications-list-card">
        <header class="notifications-list-head">
            <div class="notifications-list-title">
                <span class="notifications-list-icon">
                    <i data-lucide="bell"></i>
                </span>

                <div>
                    <h2>Avisos recentes</h2>

                    <p>
                        {{ $notifications->total() }}
                        {{ $notifications->total() === 1 ? 'registro' : 'registros' }}
                        nesta organização
                    </p>
                </div>
            </div>
        </header>

        <div
            class="notifications-list"
            id="notification-list"
            aria-live="polite"
        >
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $rawType = strtolower((string) (
                        $data['type']
                        ?? $data['category']
                        ?? $data['display_icon']
                        ?? 'default'
                    ));

                    $notificationType = preg_replace(
                        '/[^a-z0-9_-]+/',
                        '-',
                        $rawType
                    ) ?: 'default';

                    $priority = strtolower(
                        (string) ($data['priority'] ?? 'normal')
                    );

                    $priorityLabel = match ($priority) {
                        'critical' => 'Crítica',
                        'high' => 'Alta',
                        'low' => 'Baixa',
                        default => 'Normal',
                    };

                    $action = match (true) {
                        in_array($notificationType, ['approval', 'request', 'permission'], true)
                            => ['label' => 'Revisar', 'icon' => 'clipboard-check'],

                        in_array($notificationType, ['delivery', 'truck', 'distribution'], true)
                            => ['label' => 'Ver entrega', 'icon' => 'truck'],

                        in_array($notificationType, ['payment', 'financial', 'wallet'], true)
                            => ['label' => 'Ver detalhes', 'icon' => 'wallet-cards'],

                        in_array($notificationType, ['document', 'file-text', 'report'], true)
                            => ['label' => 'Abrir documento', 'icon' => 'file-text'],

                        in_array($notificationType, ['security', 'key-round', 'shield-alert'], true)
                            => ['label' => 'Ver segurança', 'icon' => 'shield-check'],

                        in_array($notificationType, ['project', 'folder-kanban'], true)
                            => ['label' => 'Ver projeto', 'icon' => 'folder-kanban'],

                        default
                            => ['label' => 'Abrir', 'icon' => 'arrow-up-right'],
                    };

                    if (filled($data['action_label'] ?? null)) {
                        $action['label'] = $data['action_label'];
                    }
                    if (filled($data['action_icon'] ?? null)) {
                        $action['icon'] = $data['action_icon'];
                    }
                @endphp

                <article
                    class="
                        notification-item
                        {{ $notification->read_at ? '' : 'unread' }}
                        type-{{ $notificationType }}
                        priority-{{ $priority }}
                    "
                    data-notification-id="{{ $notification->id }}"
                    data-notification-state="{{ $notification->read_at ? 'read' : 'unread' }}"
                    data-notification-priority="{{ $priority }}"
                >
                    <span class="notification-icon" aria-hidden="true">
                        <i data-lucide="{{ $data['display_icon'] ?? 'bell' }}"></i>
                    </span>

                    <div class="notification-copy">
                        <h3>
                            {{ $data['title'] ?? 'Notificação' }}
                        </h3>

                        <p>
                            {{ $data['body'] ?? '' }}
                        </p>

                        <div class="notification-meta">
                            <span>
                                <i data-lucide="clock-3"></i>
                                {{ $notification->created_at->diffForHumans() }}
                            </span>

                            @if($priority !== 'normal')
                                <span class="notification-priority">
                                    {{ $priorityLabel }}
                                </span>
                            @endif

                        </div>
                    </div>

                    <a
                        class="notification-open"
                        href="{{ route('notifications.open', [
                            'tenant' => $tenant,
                            'notification' => $notification->id,
                        ]) }}"
                        aria-label="{{ $action['label'] }}: {{ $data['title'] ?? 'Notificação' }}"
                    >
                        <i data-lucide="{{ $action['icon'] }}"></i>
                        {{ $action['label'] }}
                    </a>
                </article>
            @empty
                <section class="notifications-empty">
                    <div>
                        <span class="notifications-empty-icon">
                            <i data-lucide="bell-off"></i>
                        </span>

                        <strong>Nenhuma notificação</strong>

                        <p>
                            Os avisos importantes da organização aparecerão aqui.
                        </p>
                    </div>
                </section>
            @endforelse

            <section
                class="notifications-filter-empty"
                id="notification-filter-empty"
                hidden
            >
                <div>
                    <strong>Nenhuma notificação não lida</strong>

                    <p>
                        Você já visualizou todos os avisos desta página.
                    </p>
                </div>
            </section>
        </div>

        @if($notifications->hasPages())
            <div class="notifications-pagination">
                {{ $notifications->links('vendor.pagination.bento') }}
            </div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const list = document.getElementById('notification-list');
    const markAllButton = document.getElementById('mark-all-read');
    const unreadCount = document.getElementById('unread-filter-count');
    const filterEmpty = document.getElementById('notification-filter-empty');
    const filters = Array.from(
        document.querySelectorAll('[data-notification-filter]')
    );

    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    function toast(message, type = 'success') {
        if (window.appToast) {
            window.appToast(message, type);
        }
    }

    function unreadItems() {
        return Array.from(
            document.querySelectorAll(
                '.notification-item[data-notification-state="unread"]'
            )
        );
    }

    function updateUnreadCount() {
        const count = unreadItems().length;

        if (unreadCount) {
            unreadCount.textContent = String(count);
        }

        window.dispatchEvent(
            new CustomEvent('notifications:changed', {
                detail: {
                    count,
                },
            })
        );

        return count;
    }

    function applyFilter(filter) {
        let visible = 0;

        document
            .querySelectorAll('.notification-item')
            .forEach(item => {
                const show =
                    filter === 'all'
                    || (filter === 'unread' && item.dataset.notificationState === 'unread')
                    || (filter === 'read' && item.dataset.notificationState === 'read')
                    || (filter === 'important' && ['high', 'critical'].includes(item.dataset.notificationPriority));

                item.hidden = !show;

                if (show) {
                    visible += 1;
                }
            });

        filters.forEach(button => {
            const active =
                button.dataset.notificationFilter === filter;

            button.classList.toggle('active', active);
            button.setAttribute(
                'aria-pressed',
                active ? 'true' : 'false'
            );
        });

        if (filterEmpty) {
            filterEmpty.hidden =
                !['unread', 'read', 'important'].includes(filter)
                || visible > 0;
        }
    }

    filters.forEach(button => {
        button.addEventListener('click', () => {
            applyFilter(button.dataset.notificationFilter);
        });
    });

    markAllButton?.addEventListener(
        'click',
        async function () {
            const originalContent = this.innerHTML;

            this.disabled = true;
            this.innerHTML = `
                <i data-lucide="loader-circle"></i>
                Atualizando...
            `;

            window.lucide?.createIcons();

            try {
                const response = await fetch(
                    @json(route('notifications.read-all', [
                        'tenant' => $tenant,
                    ])),
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }
                );

                const body = await response
                    .json()
                    .catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        body.message
                        || 'Não foi possível atualizar as notificações.'
                    );
                }

                unreadItems().forEach(item => {
                    item.classList.remove('unread');
                    item.dataset.notificationState = 'read';
                });

                updateUnreadCount();
                this.remove();

                const activeFilter =
                    document.querySelector(
                        '[data-notification-filter].active'
                    )?.dataset.notificationFilter || 'all';

                applyFilter(activeFilter);

                toast('Notificações marcadas como lidas.');
            } catch (error) {
                this.disabled = false;
                this.innerHTML = originalContent;
                window.lucide?.createIcons();

                toast(error.message, 'error');
            }
        }
    );

    updateUnreadCount();
})();
</script>
@endpush
