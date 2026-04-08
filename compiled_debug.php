<?php
    /**
     * Comprovante de Entrega do Associado
     *
     * Variáveis esperadas:
     *   $receipt         - AssociateReceipt|null
     *   $tenant          - Tenant
     *   $project         - SalesProject
     *   $associate       - Associate (com associate->user)
     *   $summary         - array: gross_value, admin_fee, net_value, deliveries_count, total_quantity
     *   $productsSummary - array of arrays: product_name, unit, quantity, gross, admin_fee, net
     */
    $logoPath = $tenant && $tenant->logo ? public_path('storage/' . $tenant->logo) : null;
    $hasLogo  = $logoPath && file_exists($logoPath);

    $receiptLabel = isset($receipt) ? $receipt->formatted_number : '—';
    $issuedAt     = isset($receipt) ? $receipt->issued_at->format('d/m/Y') : now()->format('d/m/Y');

    $primaryColor = '#1a3a5c';
    $lineColor    = '#c0c8d4';
    $textColor    = '#000000';

    $isSecondCopy = $isSecondCopy ?? false;
    $isStandalone = empty($project);

    $hasContract = !$isStandalone && !empty($project->contract_number);
    $hasProcess  = !$isStandalone && !empty($project->process_number);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 11px;
    color: <?php echo e($textColor); ?>;
    background: #fff;
    padding: 16mm 18mm 14mm 18mm;
}
.hdr { display: table; width: 100%; padding-bottom: 10px; border-bottom: 2px solid <?php echo e($primaryColor); ?>; margin-bottom: 16px; }
.hdr-logo { display: table-cell; width: 70px; vertical-align: middle; }
.hdr-logo img { width: 60px; height: 60px; object-fit: contain; }
.hdr-org  { display: table-cell; vertical-align: middle; padding-left: 12px; }
.hdr-org .org-name { font-size: 13px; font-weight: bold; color: <?php echo e($textColor); ?>; text-transform: uppercase; line-height: 1.3; }
.hdr-org .org-meta { font-size: 9.5px; color: #444; margin-top: 3px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: middle; white-space: nowrap; }
.hdr-right .doc-type { font-size: 9px; font-weight: bold; color: <?php echo e($textColor); ?>; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.hdr-right .doc-num  { font-size: 13px; font-weight: bold; color: <?php echo e($textColor); ?>; display: block; }
.hdr-right .doc-date { font-size: 9.5px; color: #555; display: block; margin-top: 2px; }
.assoc-row { display: table; width: 100%; margin-bottom: 14px; border-bottom: 1px solid <?php echo e($lineColor); ?>; padding-bottom: 10px; }
.assoc-col  { display: table-cell; vertical-align: top; padding-right: 20px; }
.assoc-col-last { display: table-cell; vertical-align: top; }
.field-label { font-size: 8.5px; color: #777; text-transform: uppercase; letter-spacing: 0.3px; display: block; margin-bottom: 2px; }
.field-value { font-size: 12px; font-weight: bold; color: #111; }
.proj-strip { background: #f4f6f8; border-left: 3px solid <?php echo e($primaryColor); ?>; padding: 8px 12px; margin-bottom: 14px; display: table; width: 100%; }
.proj-cell { display: table-cell; vertical-align: top; padding-right: 20px; }
.proj-cell-last { display: table-cell; vertical-align: top; }
.proj-label { font-size: 8.5px; color: #666; display: block; }
.proj-value { font-size: 10.5px; font-weight: bold; color: #111; }
.decl { margin-bottom: 14px; padding: 10px 14px; border: 1px solid <?php echo e($lineColor); ?>; background: #fafbfc; }
.decl p { font-size: 11px; line-height: 1.7; color: #222; text-align: justify; }
.decl strong { color: <?php echo e($textColor); ?>; }
.sec-label { font-size: 10px; font-weight: bold; color: <?php echo e($textColor); ?>; text-transform: uppercase; letter-spacing: 0.3px; border-left: 3px solid <?php echo e($primaryColor); ?>; padding-left: 7px; margin: 0 0 8px; }
table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 10px; }
table.tbl thead th { background: <?php echo e($primaryColor); ?>; color: #fff; padding: 6px 7px; text-align: left; font-size: 12px;  font-family: 'DejaVu Sans', Arial, sans-serif; }
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { padding: 6px 7px; border-bottom: 1px solid #e8ecf0; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f7f9fb; }
table.tbl tfoot td { padding: 7px 7px; font-weight: bold; background: #eef1f5; border-top: 2px solid <?php echo e($primaryColor); ?>; }
table.tbl tfoot td.r { text-align: right; color: <?php echo e($textColor); ?>; font-size: 12px; }
.sig-area { margin-top: 30px; display: table; width: 55%; page-break-inside: avoid; }
.sig-block { display: table-cell; text-align: center; }
.sig-line { border-top: 1px solid #333; padding-top: 6px; margin-top: 40px; font-size: 11px; font-weight: bold; }
.sig-role { font-size: 9px; color: #555; margin-top: 3px; }
.sig-doc  { font-size: 9px; color: #888; margin-top: 1px; }
.ftr { margin-top: 20px; border-top: 1px solid <?php echo e($lineColor); ?>; padding-top: 6px; text-align: center; font-size: 8.5px; color: #999; }
</style>
</head>
<body>


<div class="hdr">
    <div class="hdr-logo">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasLogo): ?>
            <img src="<?php echo e($logoPath); ?>" alt="Logo">
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="hdr-org">
        <div class="org-name"><?php echo e($tenant->name ?? ''); ?></div>
        <div class="org-meta">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant?->cnpj): ?>
                CNPJ: <?php echo e($tenant->cnpj); ?><br>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant?->city): ?>
                <?php echo e($tenant->city); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant?->state): ?>
                    / <?php echo e($tenant->state); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <div class="hdr-right">
        <span class="doc-type"><?php echo e($isStandalone ? 'Comprovante de Entrega' : 'Comprovante de Entrega'); ?><?php echo e($isSecondCopy ? ' — 2ª VIA' : ''); ?></span>
        <span class="doc-num">Nº <?php echo e($receiptLabel); ?></span>
        <span class="doc-date">Emitido em: <?php echo e($issuedAt); ?></span>
    </div>
</div>


<div class="assoc-row">
    <div class="assoc-col" style="width:55%;">
        <span class="field-label">Produtor / Associado</span>
        <span class="field-value" style="font-size:13px;"><?php echo e($associate->user->name ?? '—'); ?></span>
    </div>
    <div class="assoc-col" style="width:30%;">
        <span class="field-label">CPF</span>
        <span class="field-value"><?php echo e($associate->cpf_cnpj ?? '—'); ?></span>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($associate->registration_number)): ?>
    <div class="assoc-col-last" style="width:15%;">
        <span class="field-label">Matrícula</span>
        <span class="field-value"><?php echo e($associate->registration_number); ?></span>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>


<div class="proj-strip">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isStandalone): ?>
        <div class="proj-cell" style="width:50%;">
            <span class="proj-label">Referente</span>
            <span class="proj-value">Entrega de Produtos</span>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($receipt) && $receipt->from_date): ?>
        <div class="proj-cell" style="width:25%;">
            <span class="proj-label">Período De</span>
            <span class="proj-value"><?php echo e($receipt->from_date->format('d/m/Y')); ?></span>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(isset($receipt) && $receipt->to_date): ?>
        <div class="proj-cell-last" style="width:25%;">
            <span class="proj-label">Até</span>
            <span class="proj-value"><?php echo e($receipt->to_date->format('d/m/Y')); ?></span>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php else: ?>
        <div class="proj-cell" style="width:<?php echo e(($hasContract || $hasProcess) ? '55%' : '80%'); ?>;">
            <span class="proj-label">Referente</span>
            <span class="proj-value"><?php echo e($project->title); ?></span>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasContract): ?>
        <div class="proj-cell" style="width:25%;">
            <span class="proj-label">Nº Contrato / CPR</span>
            <span class="proj-value"><?php echo e($project->contract_number); ?></span>
        </div>
        <?php elseif($hasProcess): ?>
        <div class="proj-cell" style="width:25%;">
            <span class="proj-label">Nº Processo</span>
            <span class="proj-value"><?php echo e($project->process_number); ?></span>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="proj-cell-last" style="width:20%;">
            <span class="proj-label">Taxa Adm.</span>
            <span class="proj-value"><?php echo e(number_format($project->admin_fee_percentage ?? 0, 1)); ?>%</span>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>


<div class="decl">
    <p>
    Recebi da <strong><?php echo e($tenant->name ?? ''); ?></strong>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant?->cnpj): ?>
        , inscrita no CNPJ sob nº <strong><?php echo e($tenant->cnpj); ?></strong>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>,
    referente ao pagamento pela entrega dos produtos relacionados abaixo
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isStandalone): ?>
        , vinculados ao projeto <strong><?php echo e($project->title); ?></strong>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>,
    a quantia líquida de
    <strong>R$ <?php echo e(number_format($summary['net_value'], 2, ',', '.')); ?></strong>,
    já deduzida a taxa administrativa no valor de
    <strong>R$ <?php echo e(number_format($summary['admin_fee'], 2, ',', '.')); ?></strong>.
</p>
</div>


<div class="sec-label">Produtos Entregues</div>
<table class="tbl">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="r" style="width:15%;">Qtd.</th>
            <th class="r" style="width:13%;">Vlr. Unit.</th>
            <th class="r" style="width:15%;">Vlr. Bruto</th>
            <th class="r" style="width:13%;">Taxa Adm.</th>
            <th class="r" style="width:18%;">Vlr. Líquido</th>
        </tr>
    </thead>
    <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $productsSummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ps): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><strong><?php echo e($ps['product_name']); ?></strong></td>
            <td class="r"><?php echo e(number_format($ps['quantity'], 3, ',', '.')); ?> <?php echo e($ps['unit']); ?></td>
            <td class="r">R$ <?php echo e(number_format($ps['unit_price'] ?? 0, 2, ',', '.')); ?></td>
            <td class="r">R$ <?php echo e(number_format($ps['gross'], 2, ',', '.')); ?></td>
            <td class="r" style="color:#c0392b;">- R$ <?php echo e(number_format($ps['admin_fee'], 2, ',', '.')); ?></td>
            <td class="r" style="color:#1a5c3a;">R$ <?php echo e(number_format($ps['net'], 2, ',', '.')); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td><strong>TOTAL</strong></td>
            <td class="r"><?php echo e(number_format($summary['total_quantity'], 3, ',', '.')); ?></td>
            <td class="r"></td>
            <td class="r">R$ <?php echo e(number_format($summary['gross_value'], 2, ',', '.')); ?></td>
            <td class="r" style="color:#c0392b;">- R$ <?php echo e(number_format($summary['admin_fee'], 2, ',', '.')); ?></td>
            <td class="r">R$ <?php echo e(number_format($summary['net_value'], 2, ',', '.')); ?></td>
        </tr>
    </tfoot>
</table>


<div style="display: table; width: 100%; margin-bottom: 20px; border: 1px solid <?php echo e($lineColor); ?>;">
    <div style="display: table-cell; width: 33%; text-align: center; padding: 9px 8px; border-right: 1px solid <?php echo e($lineColor); ?>;">
        <div style="font-size: 8px; color: #666; text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif;">Valor Bruto Total</div>
        <div style="font-size: 13px; font-weight: bold; color: #333; margin-top: 3px;">R$ <?php echo e(number_format($summary['gross_value'], 2, ',', '.')); ?></div>
    </div>
    <div style="display: table-cell; width: 33%; text-align: center; padding: 9px 8px; border-right: 1px solid <?php echo e($lineColor); ?>;">
        <div style="font-size: 8px; color: #666; text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif;">
            Taxa Adm.
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isStandalone): ?>
                (<?php echo e(number_format($project->admin_fee_percentage ?? 0, 1)); ?>%)
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div style="font-size: 13px; font-weight: bold; color: #c0392b; margin-top: 3px;">- R$ <?php echo e(number_format($summary['admin_fee'], 2, ',', '.')); ?></div>
    </div>
    <div style="display: table-cell; width: 34%; text-align: center; padding: 9px 8px; background: <?php echo e($primaryColor); ?>;">
        <div style="font-size: 8px; color: rgba(255,255,255,0.75); text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif;">Valor Líquido a Receber</div>
        <div style="font-size: 15px; font-weight: bold; color: #fff; margin-top: 3px;">R$ <?php echo e(number_format($summary['net_value'], 2, ',', '.')); ?></div>
    </div>
</div>


<p style="text-align: center; font-size: 11px; color: #333; margin: 22px 0 14px;">Por ser verdade, firmo o presente recibo.</p>

<p style="text-align: center; font-size: 10.5px; color: #444; margin-bottom: 0; margin-top: 4px;">
    <?php echo e($tenant->city ?? '________________'); ?><?php echo e($tenant->state ? '/' . $tenant->state : ''); ?>,&nbsp;&nbsp;
    _______ de ___________________________ de <?php echo e(isset($receipt) ? $receipt->receipt_year : date('Y')); ?>.
</p>

<table style="margin: 28px auto 0; page-break-inside: avoid; width: 80%; border-collapse: collapse;">
    <tr>
        <td style="text-align: center; padding: 0 30px;">
            <div class="sig-line"><?php echo e($associate->user->name ?? '—'); ?></div>
            <div class="sig-role">Produtor / Associado</div>
            <div class="sig-doc">CPF: <?php echo e($associate->cpf_cnpj ?? '___.___.___-__'); ?></div>
        </td>
    </tr>
</table>


    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSecondCopy): ?>
        <div style="position: fixed; top: 50%; left: 0; width: 100%; text-align: center; transform: translateY(-50%) rotate(-35deg); color: rgba(180,0,0,0.12); font-size: 72px; font-weight: bold; letter-spacing: 6px; font-family: 'DejaVu Sans', Arial, sans-serif; pointer-events: none; z-index: 100;">
            2ª VIA
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<div class="ftr">
    <?php echo e($tenant->name ?? ''); ?>

    &nbsp;&nbsp;|&nbsp;&nbsp; Comprovante gerado em <?php echo e(now()->format('d/m/Y H:i')); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSecondCopy): ?>
        &nbsp;&nbsp;|&nbsp;&nbsp; <strong>2ª VIA</strong>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

</body>
</html>
