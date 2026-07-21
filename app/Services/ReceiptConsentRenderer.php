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

    public function render(
        string $kind,
        Tenant $tenant,
        ?SalesProject $project,
        ?Model $receipt,
        array $financial,
        ?Associate $associate = null,
        ?Customer $customer = null,
        ?Organization $organization = null,
    ): HtmlString {
        $template = $this->resolveTemplate($tenant->getKey(), $kind, $project?->type);

        if ($template && ! $template->consent_enabled) {
            return new HtmlString('');
        }

        $content = trim((string) ($template?->consent_content ?: $this->defaultContent($kind)));
        if ($content === '') {
            return new HtmlString('');
        }

        $content = $this->sanitize($content);
        $variables = $this->variables($tenant, $project, $receipt, $financial, $associate, $customer, $organization);

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z0-9_.-]+)\s*\}\}/i',
            fn (array $match): string => $variables[$match[1]] ?? '',
            $content,
        ) ?? '';

        return new HtmlString('<div class="receipt-consent">'.$rendered.'</div>');
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
            ],
            'Projeto e comprovante' => [
                '{{projeto.nome}}' => 'Nome do projeto',
                '{{projeto.tipo}}' => 'Tipo do projeto',
                '{{projeto.codigo}}' => 'Codigo do projeto',
                '{{projeto.contrato}}' => 'Numero do contrato',
                '{{comprovante.numero}}' => 'Numero do comprovante',
                '{{comprovante.ano}}' => 'Ano do comprovante',
                '{{comprovante.data}}' => 'Data de emissao',
            ],
            'Valores' => [
                '{{valor.bruto}}' => 'Valor bruto',
                '{{valor.taxas}}' => 'Taxas e deducoes',
                '{{valor.liquido}}' => 'Valor liquido',
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
                '{{assinatura.associado}}' => 'Bloco de assinatura do associado',
                '{{assinatura.cliente}}' => 'Bloco de assinatura do cliente',
                '{{assinatura.organizacao}}' => 'Bloco da organizacao compradora',
                '{{assinatura.representante}}' => 'Bloco do representante da tenant',
            ],
        ];
    }

    private function resolveTemplate(int $tenantId, string $kind, ?string $projectType): ?DocumentTemplate
    {
        $base = DocumentTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('template_category', 'system')
            ->where('system_template_key', $kind)
            ->where('is_active', true);

        if ($projectType) {
            $exact = (clone $base)->where('project_type', $projectType)->latest('id')->first();
            if ($exact) {
                return $exact;
            }
        }

        return $base->whereNull('project_type')->latest('id')->first();
    }

    private function variables(
        Tenant $tenant,
        ?SalesProject $project,
        ?Model $receipt,
        array $financial,
        ?Associate $associate,
        ?Customer $customer,
        ?Organization $organization,
    ): array {
        $receiptDate = $receipt?->issued_at ?? now();
        $receiptNumber = $receipt?->formatted_number
            ?? collect([$receipt?->receipt_number, $receipt?->receipt_year])->filter()->implode('/');

        $plain = [
            'tenant.nome' => $tenant->name,
            'tenant.cnpj' => $tenant->cnpj,
            'tenant.cidade' => $tenant->city,
            'tenant.estado' => $tenant->state,
            'tenant.cidade_uf' => collect([$tenant->city, $tenant->state])->filter()->implode('/'),
            'projeto.nome' => $project?->title,
            'projeto.tipo' => $project?->type_label,
            'projeto.codigo' => $project?->code,
            'projeto.contrato' => $project?->contract_number,
            'comprovante.numero' => $receiptNumber,
            'comprovante.ano' => $receipt?->receipt_year ?? now()->year,
            'comprovante.data' => $receiptDate?->format('d/m/Y'),
            'valor.bruto' => $this->money($financial['gross'] ?? $financial['gross_value'] ?? 0),
            'valor.taxas' => $this->money($financial['fees'] ?? $financial['admin_fee'] ?? 0),
            'valor.liquido' => $this->money($financial['net'] ?? $financial['net_value'] ?? 0),
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
        ];

        $escaped = collect($plain)
            ->map(fn ($value): string => e((string) ($value ?? '')))
            ->all();

        return $escaped + [
            'tenant.cnpj_texto' => $tenant->cnpj
                ? ', inscrita no CNPJ sob no <strong>'.e($tenant->cnpj).'</strong>'
                : '',
            'assinatura.associado' => $this->signature(
                $associate?->display_name ?: 'Associado nao identificado',
                'Produtor / Associado',
                $associate?->cpf_cnpj,
            ),
            'assinatura.cliente' => $this->signature(
                $customer?->responsible_name ?: $customer?->name ?: 'Responsavel pelo cliente',
                $customer?->responsible_role ?: 'Cliente / Recebedor',
                $customer?->cnpj,
            ),
            'assinatura.organizacao' => $this->signature(
                $organization?->responsible_name ?: $organization?->name ?: 'Responsavel pela organizacao compradora',
                $organization?->responsible_role ?: 'Organizacao compradora',
                $organization?->cnpj,
            ),
            'assinatura.representante' => $tenant->legal_representative_name
                ? $this->signature(
                    $tenant->legal_representative_name,
                    $tenant->legal_representative_role ?: 'Responsavel pela Organizacao',
                    $tenant->legal_representative_cpf,
                )
                : '',
        ];
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
