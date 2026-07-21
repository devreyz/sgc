@extends('layouts.bento')

@section('title', 'Minhas Entregas')
@section('page-title', 'Minhas Entregas')
@section('user-role', 'Associado')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug   = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
    $tenantSlug  = $currentTenant?->slug ?? session('tenant_slug') ?? $routeSlug ?? null;
@endphp

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('associate', 'deliveries', $tenantSlug);
@endphp

@section('content')
<div class="bento-grid">

    {{-- ── STATS ───────────────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;">
            <div>
                <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem;">Total</div>
                <div style="font-size:1.6rem;font-weight:700;line-height:1.1;">{{ $deliveryStats['total'] }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem;">Aprovadas</div>
                <div style="font-size:1.6rem;font-weight:700;line-height:1.1;color:var(--color-success);">{{ $deliveryStats['approved'] }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem;">Pendentes</div>
                <div style="font-size:1.6rem;font-weight:700;line-height:1.1;color:var(--color-warning);">{{ $deliveryStats['pending'] }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem;">Valor Total</div>
                <div style="font-size:1.35rem;font-weight:700;line-height:1.1;color:var(--color-success);">R$ {{ number_format($deliveryStats['total_value'],2,',','.') }}</div>
            </div>
        </div>
    </div>

    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:1rem;">
            <div>
                <h2 style="font-weight:700;font-size:1rem;">Resumo financeiro das distribuicoes</h2>
                <p style="font-size:.8rem;color:var(--color-text-muted);margin-top:.2rem;">Os valores financeiros usam distribuicoes aprovadas como fonte da verdade.</p>
            </div>
            <span class="badge badge-success">{{ $financialSummary['distribution_count'] }} distribuicao(oes)</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.85rem;">
            <div>
                <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:700;text-transform:uppercase;">Liquido distribuido</div>
                <div style="font-size:1.25rem;font-weight:800;color:var(--color-success);">R$ {{ number_format($financialSummary['total_net'],2,',','.') }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:700;text-transform:uppercase;">Taxas</div>
                <div style="font-size:1.25rem;font-weight:800;">R$ {{ number_format($financialSummary['total_fees'],2,',','.') }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:700;text-transform:uppercase;">A receber</div>
                <div style="font-size:1.25rem;font-weight:800;color:var(--color-warning);">R$ {{ number_format($financialSummary['receivable'],2,',','.') }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:700;text-transform:uppercase;">Pago</div>
                <div style="font-size:1.25rem;font-weight:800;color:var(--color-success);">R$ {{ number_format($financialSummary['paid'],2,',','.') }}</div>
            </div>
        </div>
    </div>

    {{-- ── FILTROS ──────────────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1rem 1.25rem;">
        <form method="GET" style="display:flex;flex-direction:column;gap:.625rem;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.625rem;">
                <div>
                    <label class="form-label" style="font-size:.7rem;">Status</label>
                    <select name="status" class="form-select" style="font-size:.775rem;" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pendente</option>
                        <option value="approved"  {{ request('status') === 'approved'  ? 'selected' : '' }}>Aprovado</option>
                        <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Rejeitado</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.7rem;">Projeto</label>
                    <select name="project_id" class="form-select" style="font-size:.775rem;" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach($myProjects as $proj)
                        <option value="{{ $proj->id }}" {{ request('project_id') == $proj->id ? 'selected' : '' }}>{{ Str::limit($proj->title, 30) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.7rem;">De</label>
                    <input type="date" name="start_date" class="form-input" value="{{ request('start_date') }}" style="font-size:.775rem;" onchange="this.form.submit()">
                </div>
                <div>
                    <label class="form-label" style="font-size:.7rem;">Até</label>
                    <input type="date" name="end_date" class="form-input" value="{{ request('end_date') }}" style="font-size:.775rem;" onchange="this.form.submit()">
                </div>
            </div>
            @if(request()->hasAny(['status','project_id','start_date','end_date']))
            <a href="{{ $tenantSlug ? route('associate.deliveries',['tenant'=>$tenantSlug]) : url('/') }}" class="btn btn-outline" style="font-size:.775rem;padding:.45rem .875rem;align-self:flex-start;">
                Limpar filtros
            </a>
            @endif
        </form>
    </div>

    {{-- ── LISTA DE ENTREGAS ────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        @if($deliveries->isEmpty())
            <p style="color:var(--color-text-muted);font-size:.875rem;text-align:center;padding:2rem 0;">Nenhuma entrega encontrada.</p>
        @else
        <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;min-width:520px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-border);text-align:left;">
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);">DATA</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);">PRODUTO</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);">PROJETO</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);text-align:right;">QTD</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);text-align:right;">VALOR</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);">STATUS</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);">FATURAMENTO</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deliveries as $dl)
                    <tr style="border-bottom:1px solid var(--color-border);">
                        <td style="padding:.625rem .625rem;white-space:nowrap;font-size:.775rem;">{{ $dl->delivery_date?->format('d/m/Y') ?? '—' }}</td>
                        <td style="padding:.625rem .625rem;font-size:.775rem;">{{ $dl->product?->name ?? '—' }}</td>
                        <td style="padding:.625rem .625rem;font-size:.725rem;color:var(--color-text-muted);max-width:140px;">{{ Str::limit($dl->salesProject?->title ?? '—', 25) }}</td>
                        <td style="padding:.625rem .625rem;text-align:right;font-weight:600;font-size:.775rem;">{{ rtrim(rtrim(number_format((float)$dl->quantity,3,',','.'), '0'),',') }}</td>
                        <td style="padding:.625rem .625rem;text-align:right;font-weight:700;color:var(--color-success);font-size:.8rem;">
                            R$ {{ number_format((float)$dl->quantity * (float)$dl->unit_price, 2,',','.') }}
                        </td>
                        <td style="padding:.625rem .625rem;">
                            <span class="badge badge-{{ $dl->status->value === 'approved' ? 'success' : ($dl->status->value === 'cancelled' ? 'danger' : ($dl->status->value === 'rejected' ? 'danger' : 'warning')) }}" style="font-size:.65rem;">
                                {{ $dl->status->getLabel() }}
                            </span>
                        </td>
                        <td style="padding:.625rem .625rem;">
                            @if($dl->billing_status instanceof \App\Enums\BillingStatus && $dl->billing_status !== \App\Enums\BillingStatus::UNBILLED)
                                @php $bColor = match($dl->billing_status) {
                                    \App\Enums\BillingStatus::PAID   => ['bg' => 'rgba(16,185,129,.15)', 'txt' => '#059669'],
                                    \App\Enums\BillingStatus::BILLED => ['bg' => 'rgba(99,102,241,.12)', 'txt' => '#4f46e5'],
                                    default                          => ['bg' => 'rgba(245,158,11,.12)', 'txt' => '#d97706'],
                                }; @endphp
                                <span style="display:inline-flex;align-items:center;padding:.15rem .45rem;border-radius:99px;font-size:.65rem;font-weight:600;background:{{ $bColor['bg'] }};color:{{ $bColor['txt'] }};">
                                    {{ $dl->billing_status->getLabel() }}
                                </span>
                            @else
                                <span style="font-size:.7rem;color:var(--color-text-muted);">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($deliveries->hasPages())
        <div style="margin-top:1.25rem;display:flex;justify-content:center;">
            {{ $deliveries->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection

