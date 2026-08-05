<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Customer;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\SalesProject;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class ReceiptConsentRenderer
{
    public const ASSOCIATE = 'project_associate_receipt';

    public const CUSTOMER = 'customer_billing_receipt';

    public const ORGANIZATION = 'customer_organization_receipt';

    public function __construct(
        private readonly NumberInWordsService $numberInWords,
        private readonly SystemPdfConfigurationResolver $systemPdfConfiguration,
    ) {}

    public function render(
        string $kind,
        Tenant $tenant,
        ?SalesProject $project,
        ?Model $receipt,
        array $financial,
        ?Associate $associate = null,
        ?Customer $customer = null,
        ?Organization $organization = null,
        string $position = 'after',
    ): HtmlString {
        $template = $this->systemPdfConfiguration->templateForKey($kind, $tenant->getKey(), $project?->type);

        if ($template && ! $template->consent_enabled) {
            return new HtmlString('');
        }

        if (! $this->supportsPosition($template, $position)) {
            return new HtmlString('');
        }

        $content = trim((string) $this->contentForPosition($template, $kind, $position));
        if ($content === '') {
            return new HtmlString('');
        }

        $content = $this->sanitize($content);
        $showRecipientSignature = $template?->show_recipient_signature ?? true;
        $showRepresentativeSignature = $template?->show_representative_signature ?? true;
        $variables = $this->variables(
            $tenant,
            $project,
            $receipt,
            $financial,
            $associate,
            $customer,
            $organization,
            $showRecipientSignature,
            $showRepresentativeSignature,
        );

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z0-9_.-]+)\s*\}\}/i',
            fn (array $match): string => $variables[$match[1]] ?? '',
            $content,
        ) ?? '';

        $rendered = $this->removeEmptySignatureCells($rendered);
        $rendered .= $this->automaticSignatures(
            $template,
            $kind,
            $position,
            $variables,
            $showRecipientSignature,
            $showRepresentativeSignature,
        );

        if (trim(strip_tags($rendered)) === '') {
            return new HtmlString('');
        }

        return new HtmlString('<div class="receipt-consent">'.$rendered.'</div>');
    }

    private function supportsPosition(?DocumentTemplate $template, string $position): bool
    {
        $configured = $template?->consent_position ?: 'after';

        return $configured === 'both' || $configured === $position;
    }

    private function contentForPosition(?DocumentTemplate $template, string $kind, string $position): string
    {
        if ($position === 'before') {
            if ($template?->consent_content_before) {
                return (string) $template->consent_content_before;
            }

            return $template?->consent_position === 'before'
                ? (string) ($template->consent_content ?: $this->defaultContent($kind))
                : '';
        }

        return (string) ($template?->consent_content ?: $this->defaultContent($kind));
    }

    public static function availableVariables(): array
    {
        return [
            'Organizacao' => [
                '{{tenant.nome}}' => 'Nome da organizacao',
                '{{tenant.cnpj}}' => 'CNPJ da organizacao',
                '{{tenant.cnpj_texto}}' => 'Trecho de CNPJ, ocultado quando nao cadastrado',
                '{{tenant.cidade}}' => 'Cidade',
                '{{tenant.estado}}' => 'Estado',
                '{{tenant.cidade_uf}}' => 'Cidade/UF',
                '{{tenant.termo_associado}}' => 'Nome adotado para associado, no singular',
                '{{tenant.termo_associados}}' => 'Nome adotado para associados, no plural',
            ],
            'Projeto e comprovante' => [
                '{{projeto.nome}}' => 'Nome do projeto',
                '{{projeto.tipo}}' => 'Tipo do projeto',
                '{{projeto.codigo}}' => 'Codigo do projeto',
                '{{projeto.contrato}}' => 'Numero do contrato',
                '{{projeto.ano_referencia}}' => 'Ano de referencia do projeto',
                '{{projeto.ano_referencia_extenso}}' => 'Ano de referencia por extenso',
                '{{projeto.valor_total}}' => 'Valor total do projeto',
                '{{projeto.valor_total_extenso}}' => 'Valor total do projeto por extenso',
                '{{projeto.taxa_admin}}' => 'Taxa administrativa do projeto',
                '{{projeto.taxa_admin_extenso}}' => 'Taxa administrativa por extenso',
                '{{comprovante.numero}}' => 'Numero do comprovante',
                '{{comprovante.numero_extenso}}' => 'Numero sequencial do comprovante por extenso',
                '{{comprovante.ano}}' => 'Ano do comprovante',
                '{{comprovante.ano_extenso}}' => 'Ano do comprovante por extenso',
                '{{comprovante.data}}' => 'Data de emissao',
                '{{comprovante.data_extenso}}' => 'Data de emissao por extenso',
                '{{comprovante.itens}}' => 'Quantidade de itens/distribuicoes',
                '{{comprovante.itens_extenso}}' => 'Quantidade de itens/distribuicoes por extenso',
            ],
            'Valores' => [
                '{{valor.bruto}}' => 'Valor bruto',
                '{{valor.bruto_extenso}}' => 'Valor bruto por extenso (reais e centavos)',
                '{{valor.taxas}}' => 'Taxas e deducoes',
                '{{valor.taxas_extenso}}' => 'Taxas e deducoes por extenso (reais e centavos)',
                '{{valor.liquido}}' => 'Valor liquido',
                '{{valor.liquido_extenso}}' => 'Valor liquido por extenso (reais e centavos)',
            ],
            'Destinatarios' => [
                '{{associado.nome}}' => 'Nome do associado',
                '{{associado.cpf}}' => 'CPF/CNPJ do associado',
                '{{cliente.nome}}' => 'Nome do cliente',
                '{{cliente.documento}}' => 'Documento do cliente',
                '{{cliente.responsavel}}' => 'Responsavel pelo cliente',
                '{{organizacao.nome}}' => 'Nome da organizacao compradora',
                '{{organizacao.documento}}' => 'Documento da organizacao compradora',
                '{{organizacao.responsavel}}' => 'Responsavel pela organizacao compradora',
            ],
            'Data e assinaturas' => [
                '{{data.hoje}}' => 'Data atual',
                '{{data.ano}}' => 'Ano atual',
                '{{data.ano_extenso}}' => 'Ano atual por extenso',
                '{{assinatura.associado}}' => 'Bloco de assinatura do associado',
                '{{assinatura.cliente}}' => 'Bloco de assinatura do cliente',
                '{{assinatura.organizacao}}' => 'Bloco da organizacao compradora',
                '{{assinatura.representante}}' => 'Bloco do representante da tenant',
            ],
        ];
    }

    private function variables(
        Tenant $tenant,
        ?SalesProject $project,
        ?Model $receipt,
        array $financial,
        ?Associate $associate,
        ?Customer $customer,
        ?Organization $organization,
        bool $showRecipientSignature,
        bool $showRepresentativeSignature,
    ): array {
        $receiptDate = $receipt?->issued_at ?? now();
        $receiptNumber = $receipt?->formatted_number
            ?? collect([$receipt?->receipt_number, $receipt?->receipt_year])->filter()->implode('/');
        $receiptYear = $receipt?->receipt_year ?? now()->year;
        $grossValue = $financial['gross'] ?? $financial['gross_value'] ?? 0;
        $feeValue = $financial['fees'] ?? $financial['admin_fee'] ?? 0;
        $netValue = $financial['net'] ?? $financial['net_value'] ?? 0;
        $itemsCount = $financial['items_count'] ?? $financial['deliveries_count'] ?? null;
        $projectValue = $project?->total_value;
        $projectFeePercentage = $project?->admin_fee_percentage;
        $projectReferenceYear = $project?->reference_year;

        $plain = [
            'tenant.nome' => $tenant->name,
            'tenant.cnpj' => $tenant->cnpj,
            'tenant.cidade' => $tenant->city,
            'tenant.estado' => $tenant->state,
            'tenant.cidade_uf' => collect([$tenant->city, $tenant->state])->filter()->implode('/'),
            'tenant.termo_associado' => $tenant->associateTerm(),
            'tenant.termo_associados' => $tenant->associateTerm(plural: true),
            'projeto.nome' => $project?->title,
            'projeto.tipo' => $project?->type_label,
            'projeto.codigo' => $project?->code,
            'projeto.contrato' => $project?->contract_number,
            'projeto.ano_referencia' => $projectReferenceYear,
            'projeto.ano_referencia_extenso' => $this->numberInWords->number($projectReferenceYear),
            'projeto.valor_total' => $projectValue === null ? '' : $this->money($projectValue),
            'projeto.valor_total_extenso' => $this->numberInWords->money($projectValue),
            'projeto.taxa_admin' => $projectFeePercentage === null
                ? ''
                : number_format((float) $projectFeePercentage, 2, ',', '.').'%',
            'projeto.taxa_admin_extenso' => $this->numberInWords->percentage($projectFeePercentage),
            'comprovante.numero' => $receiptNumber,
            'comprovante.numero_extenso' => $this->numberInWords->number($receipt?->receipt_number),
            'comprovante.ano' => $receiptYear,
            'comprovante.ano_extenso' => $this->numberInWords->number($receiptYear),
            'comprovante.data' => $receiptDate?->format('d/m/Y'),
            'comprovante.data_extenso' => $receiptDate?->translatedFormat('d \\d\\e F \\d\\e Y'),
            'comprovante.itens' => $itemsCount,
            'comprovante.itens_extenso' => $this->numberInWords->number($itemsCount),
            'valor.bruto' => $this->money($grossValue),
            'valor.bruto_extenso' => $this->numberInWords->money($grossValue),
            'valor.taxas' => $this->money($feeValue),
            'valor.taxas_extenso' => $this->numberInWords->money($feeValue),
            'valor.liquido' => $this->money($netValue),
            'valor.liquido_extenso' => $this->numberInWords->money($netValue),
            'associado.nome' => $associate?->display_name,
            'associado.cpf' => $associate?->cpf_cnpj,
            'cliente.nome' => $customer?->name,
            'cliente.documento' => $customer?->cnpj,
            'cliente.responsavel' => $customer?->responsible_name,
            'organizacao.nome' => $organization?->name,
            'organizacao.documento' => $organization?->cnpj,
            'organizacao.responsavel' => $organization?->responsible_name,
            'data.hoje' => now()->format('d/m/Y'),
            'data.ano' => now()->year,
            'data.ano_extenso' => $this->numberInWords->number(now()->year),
        ];

        $escaped = collect($plain)
            ->map(fn ($value): string => e((string) ($value ?? '')))
            ->all();

        return $escaped + [
            'tenant.cnpj_texto' => $tenant->cnpj
                ? ', inscrita no CNPJ sob no <strong>'.e($tenant->cnpj).'</strong>'
                : '',
            'assinatura.associado' => $showRecipientSignature
                ? $this->signature(
                    $associate?->display_name ?: $tenant->associateTerm().' nao identificado',
                    $tenant->associateTerm(),
                    $associate?->cpf_cnpj,
                )
                : '',
            'assinatura.cliente' => $showRecipientSignature
                ? $this->signature(
                    $customer?->responsible_name ?: $customer?->name ?: 'Responsavel pelo cliente',
                    $customer?->responsible_role ?: 'Cliente / Recebedor',
                    $customer?->cnpj,
                )
                : '',
            'assinatura.organizacao' => $showRecipientSignature
                ? $this->signature(
                    $organization?->responsible_name ?: $organization?->name ?: 'Responsavel pela organizacao compradora',
                    $organization?->responsible_role ?: 'Organizacao compradora',
                    $organization?->cnpj,
                )
                : '',
            'assinatura.representante' => $showRepresentativeSignature
                ? $this->signature(
                    $tenant->legal_representative_name ?: 'Representante da organizacao',
                    $tenant->legal_representative_role ?: 'Responsavel pela Organizacao',
                    $tenant->legal_representative_cpf,
                )
                : '',
        ];
    }

    private function automaticSignatures(
        ?DocumentTemplate $template,
        string $kind,
        string $position,
        array $variables,
        bool $showRecipientSignature,
        bool $showRepresentativeSignature,
    ): string {
        if (! $this->isAutomaticSignaturePosition($template, $position)) {
            return '';
        }

        $blocks = [];
        $recipientVariable = $this->recipientSignatureVariable($kind);

        if (
            $showRecipientSignature
            && $recipientVariable
            && ! $this->templateContainsVariable($template, $kind, $recipientVariable)
            && ($variables[$recipientVariable] ?? '') !== ''
        ) {
            $blocks[] = $variables[$recipientVariable];
        }

        if (
            $showRepresentativeSignature
            && ! $this->templateContainsVariable($template, $kind, 'assinatura.representante')
            && ($variables['assinatura.representante'] ?? '') !== ''
        ) {
            $blocks[] = $variables['assinatura.representante'];
        }

        if ($blocks === []) {
            return '';
        }

        $cells = implode('', array_map(
            fn (string $block): string => '<td>'.$block.'</td>',
            $blocks,
        ));

        return '<table class="receipt-signatures"><tr>'.$cells.'</tr></table>';
    }

    private function isAutomaticSignaturePosition(?DocumentTemplate $template, string $position): bool
    {
        $configured = $template?->consent_position ?: 'after';

        return $configured === 'before'
            ? $position === 'before'
            : $position === 'after';
    }

    private function recipientSignatureVariable(string $kind): ?string
    {
        return match ($kind) {
            self::ASSOCIATE => 'assinatura.associado',
            self::CUSTOMER => 'assinatura.cliente',
            self::ORGANIZATION => 'assinatura.organizacao',
            default => null,
        };
    }

    private function templateContainsVariable(
        ?DocumentTemplate $template,
        string $kind,
        string $variable,
    ): bool {
        $content = ($template?->consent_content_before ?? '').' '
            .($template?->consent_content ?: $this->defaultContent($kind));

        return (bool) preg_match(
            '/\{\{\s*'.preg_quote($variable, '/').'\s*\}\}/i',
            $content,
        );
    }

    private function removeEmptySignatureCells(string $html): string
    {
        $html = preg_replace('#<td>\s*</td>#i', '', $html) ?? $html;
        $html = preg_replace('#<tr>\s*</tr>#i', '', $html) ?? $html;
        $html = preg_replace('#<tbody>\s*</tbody>#i', '', $html) ?? $html;

        return preg_replace('#<table>\s*</table>#i', '', $html) ?? $html;
    }

    private function defaultContent(string $kind): string
    {
        return match ($kind) {
            self::ASSOCIATE => <<<'HTML'
<p>Recebi da <strong>{{tenant.nome}}</strong>{{tenant.cnpj_texto}}, a quantia liquida de <strong>{{valor.liquido}}</strong>, referente ao pagamento pelas entregas dos produtos relacionados acima, conforme os precos acordados por cliente.</p>
<p>Por ser verdade, firmo o presente recibo.</p>
<p>{{tenant.cidade_uf}}, _______ de ___________________________ de {{comprovante.ano}}.</p>
<table><tr><td>{{assinatura.associado}}</td><td>{{assinatura.representante}}</td></tr></table>
HTML,
            self::CUSTOMER => <<<'HTML'
<p>Declaro que as distribuicoes relacionadas neste comprovante foram destinadas ao cliente <strong>{{cliente.nome}}</strong>, no valor total de <strong>{{valor.liquido}}</strong>, referente ao projeto <strong>{{projeto.nome}}</strong>.</p>
<p>{{tenant.cidade_uf}}, _______ de ___________________________ de {{comprovante.ano}}.</p>
<table><tr><td>{{assinatura.cliente}}</td><td>{{assinatura.representante}}</td></tr></table>
HTML,
            self::ORGANIZATION => <<<'HTML'
<p>Declaro que as distribuicoes relacionadas neste comprovante foram destinadas a <strong>{{organizacao.nome}}</strong> e suas unidades, no valor total de <strong>{{valor.liquido}}</strong>, referente ao projeto <strong>{{projeto.nome}}</strong>.</p>
<p>{{tenant.cidade_uf}}, _______ de ___________________________ de {{comprovante.ano}}.</p>
<table><tr><td>{{assinatura.organizacao}}</td><td>{{assinatura.representante}}</td></tr></table>
HTML,
            default => '',
        };
    }

    private function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $allowed = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><table><thead><tbody><tfoot><tr><th><td>';
        $html = strip_tags($html, $allowed);

        return preg_replace('/<([a-z][a-z0-9]*)(?:\s[^>]*)?>/i', '<$1>', $html) ?? '';
    }

    private function signature(string $name, string $role, ?string $document): string
    {
        $documentLine = $document ? '<div class="sig-doc">Documento: '.e($document).'</div>' : '';

        return '<div class="receipt-signature"><div class="sig-line">'.e($name).'</div>'
            .'<div class="sig-role">'.e($role).'</div>'.$documentLine.'</div>';
    }

    private function money(mixed $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }
}
