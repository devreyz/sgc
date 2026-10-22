@php
    $system_pdf_template = $system_pdf_template ?? null;
    $definition = \App\Models\DocumentTemplate::systemDefinitionForView(\App\Services\Accounting\BillingDossierReportService::TYPES[$report['report_type']]['view']);
    $sections = $visible_sections ?? $definition['default_sections'];
    if ($report['report_type'] === 'members' && in_array($sections, [
        ['context', 'summary', 'members', 'table', 'totals'], ['context', 'summary', 'members', 'table'],
    ], true)) $sections = $definition['default_sections'];
    $columns = $visible_columns ?? $visibleColumns ?? $definition['default_columns'];
    if ($report['report_type'] === 'members' && $columns === ['quantity', 'count']) $columns = $definition['default_columns'];
    $showSection = fn ($section) => in_array($section, $sections, true);
    $showCol = fn ($column) => in_array($column, $columns, true);
    $sectionOrder = collect(array_merge($system_pdf_template?->section_order ?? [], array_keys($definition['sections'])))
        ->unique()->filter(fn ($section) => $showSection($section) && isset($definition['sections'][$section]));
    $fmtQuantity = fn ($quantity) => rtrim(rtrim(number_format((float) $quantity, 4, ',', '.'), '0'), ',');
    $renderContent = fn ($slot) => app(\App\Services\Accounting\BillingReportContentRenderer::class)->render($slot, $report, $system_pdf_template ?? null);
@endphp
<div class="billing-dossier-report">
    @if(trim((string) $renderContent('cover')) !== '')<div class="br-custom br-cover">{{ $renderContent('cover') }}</div>@endif
    @foreach($report['warnings'] as $warning)<div class="br-notice">Atenção: {{ $warning }}</div>@endforeach
    @if(!$showSection('context'))<div class="br-custom">{{ $renderContent('intro') }}</div>@endif
    @foreach($sectionOrder as $section)
        @if($section === 'context')
            @if($report['report_type'] === 'members')
            <div class="br-muted"><strong>Referência:</strong> {{ $report['billing']['number'] }} · {{ $report['billing']['projects'] }}<br><strong>Período:</strong> {{ $report['period'] }}</div>
            @else
            <div class="br-context"><strong>Referência:</strong> {{ $report['billing']['number'] }}<br>
                <strong>Destinatário:</strong> {{ $report['billing']['recipient'] }}<br>
                <strong>Projeto(s):</strong> {{ $report['billing']['projects'] }}<br><strong>Período:</strong> {{ $report['period'] }}</div>
            @endif
            <div class="br-custom">{{ $renderContent('intro') }}</div>
        @elseif($section === 'summary')
            <h2>Resumo da captura</h2><p>{{ $report['distribution_count'] }} distribuição(ões) · {{ $report['member_count'] }} associado(s) · {{ count($report['documents']) }} documento(s) relacionado(s).</p>
        @elseif($section === 'declaration')
            <h2>Recebimento e conferência</h2><p>A relação abaixo apresenta os produtos destinados a {{ $report['billing']['recipient'] }} no período de {{ $report['period'] }}, conforme o escopo identificado. O responsável poderá conferir as quantidades e registrar manualmente o recebimento, a aceitabilidade e eventuais ressalvas. A emissão deste termo, por si só, não confirma o recebimento nem a aprovação dos produtos.</p>
        @elseif($section === 'members' && !$showSection('table'))
            @foreach($report['members'] as $member)<h3>{{ $member['name'] }}</h3><p class="br-member-meta">@if($member['document'])CPF/CNPJ: {{ $member['document'] }} · @endif Matrícula: {{ $member['registration'] ?: 'Não informada' }}</p>@endforeach
        @elseif($section === 'table')
            <div class="br-custom">{{ $renderContent('before_table') }}</div>
            @if($report['report_type'] === 'members')
                @foreach($report['customers'] as $customer)
                <div class="br-client-heading {{ !$loop->first ? 'br-next-client' : '' }}"><strong>{{ $customer['name'] }}</strong>
                </div>
                @foreach($customer['members'] as $member)
                    @include('pdf.partials.billing-member-product-table', ['reportProducts' => $member['products']])
                @endforeach
                @if($showCol('gross'))<div class="br-totals"><strong>Total do cliente: R$ {{ number_format((float) $customer['totals']['gross'], 2, ',', '.') }}</strong>
                        @if($showCol('adjustments') && (bccomp($customer['totals']['discounts'], '0', 2) !== 0 || bccomp($customer['totals']['accruals'], '0', 2) !== 0))
                            · Descontos: R$ {{ number_format((float) $customer['totals']['discounts'], 2, ',', '.') }} · Acréscimos: R$ {{ number_format((float) $customer['totals']['accruals'], 2, ',', '.') }}<br><strong>Total ajustado: R$ {{ number_format((float) $customer['totals']['net'], 2, ',', '.') }}</strong>
                        @endif
                    </div>@endif
                @endforeach
            @elseif($report['report_type'] === 'acceptance')
                <h2>Relação de produtos entregues</h2>
                @include('pdf.partials.billing-report-product-table', ['reportProducts' => $report['commercial_products']])
            @else
                <h2>Documentos relacionados e seus titulares</h2>
                <table class="report-table"><thead><tr><th>Documento / referência</th><th>Pertence a</th>
                    @if($showCol('status'))<th>Situação</th>@endif @if($showCol('date'))<th>Data</th>@endif @if($showCol('quantity'))<th>Nesta folha</th>@endif<th class="right">Valor líquido</th>
                </tr></thead><tbody>@foreach($report['documents'] as $document)<tr><td><strong>{{ $document['type'] }}</strong><div>{{ $document['number'] }}</div></td><td>{{ $document['owner'] }}</td>
                    @if($showCol('status'))<td>{{ $document['status'] }}</td>@endif @if($showCol('date'))<td class="br-date">{{ $document['date'] ?: 'Não informada' }}</td>@endif @if($showCol('quantity'))<td>{{ $document['count'] ?? '—' }}</td>@endif
                    <td class="right">{{ $document['amount'] !== null ? 'R$ '.number_format((float) $document['amount'], 2, ',', '.') : 'Não informado' }}</td>
                </tr>@endforeach</tbody></table>
                <h2>Totais por tipo de documento</h2>
                <table class="report-table"><thead><tr><th>Tipo de documento</th><th class="right">Documentos</th><th class="right">Valor líquido total</th></tr></thead>
                    <tbody>@foreach($report['document_totals'] as $total)<tr><td>{{ $total['type'] }}@if($total['missing'])<div class="br-muted">{{ $total['missing'] }} sem valor informado</div>@endif</td>
                        <td class="right">{{ $total['count'] }}</td><td class="right">{{ $total['amount'] !== null ? 'R$ '.number_format((float) $total['amount'], 2, ',', '.') : 'Não informado' }}</td></tr>@endforeach</tbody>
                </table>
                <p class="br-muted">Valores integrais dos documentos listados, inclusive quando abrangem outras folhas. Cópias e anexos não repetem o valor do comprovante.</p>
            @endif
            @if($report['distribution_count'] === 0 && $report['report_type'] !== 'documents')<p>Nenhuma distribuição aprovada disponível neste contexto.</p>@endif
            <div class="br-custom">{{ $renderContent('after_table') }}</div>
        @elseif($section === 'signature')
            <div class="br-custom">{{ $renderContent('closing') }}</div>
            @if($report['report_type'] === 'acceptance')<h2>Conferência pelo destinatário</h2><p>Data do recebimento: ____/____/________ &nbsp; Local: __________________________________</p><p>Resultado: ( &nbsp; ) aceito &nbsp; ( &nbsp; ) aceito com ressalvas &nbsp; ( &nbsp; ) não aceito</p><p>Ressalvas: ______________________________________________________________________<br>_______________________________________________________________________________</p>@endif
            <table class="br-signature" style="width:100%"><tr>
                @if($system_pdf_template?->show_recipient_signature ?? true)<td><div class="br-signature-line">{{ $report['billing']['recipient'] }}<br>Responsável: __________________________<br>Documento: ___________________________</div></td>@endif
                @if($system_pdf_template?->show_representative_signature ?? true)<td><div class="br-signature-line">{{ $report['tenant']->name }}<br>Responsável pela relação</div></td>@endif
            </tr></table>
        @endif
    @endforeach
    @if(!$showSection('signature'))<div class="br-custom">{{ $renderContent('closing') }}</div>@endif
    @if(trim((string) $renderContent('back_cover')) !== '')<div class="br-custom br-back-cover">{{ $renderContent('back_cover') }}</div>@endif
</div>
