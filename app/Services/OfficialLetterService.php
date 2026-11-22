<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\PdfLayoutTemplate;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OfficialLetterService
{
    public const TYPE = 'official_letter';
    public const STANDARD_TEMPLATE = 'Ofício institucional';
    public const FIELDS = [
        'recipient' => 'Destinatário', 'recipient_position' => 'Cargo do destinatário',
        'institution' => 'Instituição', 'recipient_address' => 'Endereço do destinatário',
        'subject' => 'Assunto', 'city' => 'Local', 'date' => 'Data',
        'salutation' => 'Vocativo', 'closing' => 'Fecho', 'signatory' => 'Signatário',
        'signatory_position' => 'Cargo do signatário',
    ];
    public const LAYOUT = [
        'top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 30,
        'font' => 'sans', 'font_size' => 12, 'line_height' => 1, 'spacing' => 6, 'indent' => 25,
        'logo' => true, 'logo_width' => 18, 'header_spacing' => 7, 'header_alignment' => 'left',
        'header_name' => '', 'acronym' => '', 'sector' => '', 'department' => '', 'repeat_header' => false,
        'separator' => false, 'footer' => '', 'address' => false, 'registration' => false,
        'phone' => false, 'email' => false, 'website' => false, 'page_numbers' => true,
        'default' => false,
    ];
    public const VARIABLES = [
        'organization.name', 'organization.acronym', 'organization.logo', 'organization.address',
        'organization.document_number', 'document.number', 'document.date_long', 'document.subject',
        'recipient.name', 'recipient.position', 'recipient.institution', 'signatory.name', 'signatory.position',
    ];

    public function __construct(private readonly SecretaryDocumentAssets $assets) {}

    public function template(Tenant $tenant, int $userId): DocumentTemplate
    {
        return DB::transaction(function () use ($tenant, $userId) {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            return DocumentTemplate::query()->firstOrCreate([
                'tenant_id' => $tenant->id, 'type' => self::TYPE, 'template_category' => 'custom', 'name' => self::STANDARD_TEMPLATE,
            ], [
                'description' => 'Modelo de ofício para correspondência institucional.',
                'content' => '<p><br></p>', 'paper_size' => 'a4', 'paper_orientation' => 'portrait',
                'is_active' => false, 'created_by' => $userId, 'institutional_settings' => self::LAYOUT,
            ]);
        });
    }

    public function defaults(Tenant $tenant): array
    {
        return [
            'date' => now('America/Sao_Paulo')->toDateString(),
            'city' => implode(' – ', array_filter([$tenant->city, $tenant->state])),
            'closing' => 'Atenciosamente,',
            'signatory' => $tenant->legal_representative_name,
            'signatory_position' => $tenant->legal_representative_role,
        ];
    }

    public function branding(Tenant $tenant): array
    {
        return [
            'name' => $tenant->legal_name ?: $tenant->name,
            'acronym' => strtoupper($tenant->slug),
            'address' => implode(', ', array_filter([
                trim($tenant->address.' '.$tenant->address_number), $tenant->address_complement,
                $tenant->neighborhood, $tenant->city, $tenant->state, $tenant->zip_code,
            ])),
            'registration' => $tenant->cnpj ? 'CNPJ '.$tenant->cnpj : '',
            'documentNumber' => $tenant->cnpj,
            'phone' => $tenant->phone ?: $tenant->mobile,
            'email' => $tenant->email,
            'website' => $tenant->website,
        ];
    }

    public function rules(): array
    {
        $rules = ['letter' => ['nullable', 'array:'.implode(',', array_keys(self::FIELDS))],
            'layout' => ['nullable', 'array:'.implode(',', array_keys(self::LAYOUT))]];
        foreach (self::FIELDS as $field => $label) {
            $rules['letter.'.$field] = $field === 'date'
                ? ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:2200-01-01']
                : ['nullable', 'string', 'max:'.($field === 'recipient_address' ? 360 : 220)];
        }
        $ranges = ['top' => [15, 45], 'right' => [10, 45], 'bottom' => [20, 55], 'left' => [10, 45],
            'font_size' => [9, 18], 'line_height' => [1, 2], 'spacing' => [0, 18], 'indent' => [0, 35],
            'logo_width' => [8, 24], 'header_spacing' => [0, 15]];
        foreach (self::LAYOUT as $field => $value) {
            $rules['layout.'.$field] = match ($field) {
                'font' => ['required_with:layout', 'in:sans,serif'],
                'header_alignment' => ['required_with:layout', 'in:left,center,right'],
                'acronym', 'sector' => ['nullable', 'string', 'max:24', 'regex:/^[A-Za-z0-9-]+$/D'],
                default => match (true) {
                    is_bool($value) => ['sometimes', 'boolean'],
                    isset($ranges[$field]) => ['required_with:layout', 'numeric', 'between:'.implode(',', $ranges[$field])],
                    default => ['nullable', 'string', 'max:'.($field === 'footer' ? 240 : 120)],
                },
            };
        }

        return $rules;
    }

    public function setDefault(DocumentTemplate $template, array $attributes, Tenant $tenant): void
    {
        DB::transaction(function () use ($template, $attributes, $tenant): void {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (! empty($attributes['institutional_settings']['default']) && ! empty($attributes['is_active'])) {
                DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('type', self::TYPE)
                    ->where('institutional_settings->default', true)->whereKeyNot($template->getKey())
                    ->update(['institutional_settings->default' => false]);
            }
            $template->fill($attributes)->save();
        });
    }

    public function snapshot(GeneratedDocument $document, string $event, int $userId): void
    {
        DB::table('generated_document_revisions')->insert([
            'generated_document_id' => $document->id, 'revision' => (int) $document->document_settings['revision'],
            'event' => $event, 'user_id' => $userId, 'created_at' => now(),
            'snapshot' => json_encode($document->only(['title', 'content', 'status', 'document_settings', 'variables_used']), JSON_THROW_ON_ERROR),
        ]);
    }

    public function assertRevision(GeneratedDocument $document, ?int $revision): void
    {
        abort_unless($document->status === 'draft' && $document->signed_at === null, 409, 'Este documento está fechado para edição.');
        abort_unless($revision === (int) ($document->document_settings['revision'] ?? 0), 409, 'O rascunho foi atualizado em outra janela. Reabra o documento antes de salvar.');
    }

    public function issue(Tenant $tenant, GeneratedDocument $record, int $userId, int $revision): GeneratedDocument
    {
        $path = null;
        try {
            return DB::transaction(function () use ($tenant, $record, $userId, $revision, &$path): GeneratedDocument {
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $document = GeneratedDocument::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($record->id);
                if ($document->status === 'issued') {
                    return $document;
                }
                $this->assertRevision($document, $revision);
                $settings = $document->document_settings;
                $letter = $settings['letter'];
                $errors = [];
                foreach (['subject', 'city', 'date', 'salutation', 'closing', 'signatory', 'signatory_position'] as $key) {
                    if (blank($letter[$key] ?? null)) {
                        $errors['letter.'.$key] = 'Preencha '.$this::FIELDS[$key].' para emitir.';
                    }
                }
                if (blank($letter['recipient'] ?? null) && blank($letter['institution'] ?? null)) {
                    $errors['letter.recipient'] = 'Informe o destinatário ou sua instituição.';
                }
                if (blank(trim(html_entity_decode(strip_tags($document->content))))) {
                    $errors['content'] = 'Escreva o corpo do ofício para emitir.';
                }
                if ($errors) {
                    throw ValidationException::withMessages($errors);
                }
                $year = (int) substr($letter['date'], 0, 4);
                $layout = $settings['layout'];
                $acronym = strtoupper($layout['acronym'] ?: Str::limit(Str::slug($tenant->slug), 24, ''));
                $sector = strtoupper($layout['sector'] ?: $acronym);
                $last = GeneratedDocument::withTrashed()->where('tenant_id', $tenant->id)
                    ->where('institutional_year', $year)->where('institutional_sector', $sector)
                    ->orderByDesc('institutional_sequence')->lockForUpdate()->value('institutional_sequence');
                $sequence = (int) $last + 1;
                $number = sprintf('%03d/%d/%s', $sequence, $year, $sector);
                $rendered = $this->render($tenant, $document, $number);
                $path = 'tenants/'.$tenant->id.'/secretary/issued/'.$document->id.'-'.Str::ulid().'.pdf';
                abort_unless(Storage::disk('local')->put($path, $rendered['bytes']), 507, 'Não foi possível arquivar o PDF. Tente emitir novamente.');

                $document->update([
                    'institutional_year' => $year, 'institutional_sector' => $sector, 'institutional_sequence' => $sequence,
                    'status' => 'issued', 'last_edited_by' => $userId, 'variables_used' => $rendered['variables'],
                    'document_settings' => array_merge($settings, [
                        'number' => $number, 'pdf' => $path, 'issued_at' => now()->toIso8601String(),
                        'revision' => $revision + 1, 'issued_by' => $userId,
                    ]),
                ]);
                $this->snapshot($document, 'issued', $userId);

                return $document;
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    public function render(Tenant $tenant, GeneratedDocument $document, ?string $number = null): array
    {
        $settings = $document->document_settings ?? [];
        $letter = $settings['letter'] ?? [];
        $layout = array_replace(self::LAYOUT, $settings['layout'] ?? []);
        $date = ! empty($letter['date']) ? Carbon::parse($letter['date'])->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y') : '';
        $branding = $this->branding($tenant);
        $name = $layout['header_name'] ?: $branding['name'];
        $logo = $layout['logo'] ? $this->assets->logo($tenant) : null;
        $variables = [
            'organization.name' => $name, 'organization.acronym' => $layout['acronym'] ?: $branding['acronym'],
            'organization.logo' => (string) $tenant->logo, 'organization.address' => $branding['address'],
            'organization.document_number' => $branding['documentNumber'], 'document.number' => $number,
            'document.date_long' => $date, 'document.subject' => $letter['subject'] ?? '',
            'recipient.name' => $letter['recipient'] ?? '', 'recipient.position' => $letter['recipient_position'] ?? '',
            'recipient.institution' => $letter['institution'] ?? '', 'signatory.name' => $letter['signatory'] ?? '',
            'signatory.position' => $letter['signatory_position'] ?? '',
        ];
        $resolve = fn (string $html) => $this->resolveVariables($html, $variables + [
            'cooperativa.nome' => $name, 'cooperativa.cnpj' => $branding['documentNumber'],
        ], $logo);
        $content = $resolve($this->assets->clean($tenant, $document->content, true));
        $partials = [];
        foreach (['header', 'footer'] as $kind) {
            $id = $settings[$kind.'_layout_id'] ?? null;
            $partial = $id ? PdfLayoutTemplate::query()->where('tenant_id', $tenant->id)->whereIn('layout_type', [$kind, 'both'])->find($id) : null;
            $partials[$kind] = $partial ? $resolve($this->assets->clean($tenant, $partial->content, true, $partial->content)) : null;
        }
        $contacts = array_filter(array_intersect_key($branding, array_filter(array_intersect_key($layout, array_flip(['address', 'registration', 'phone', 'email', 'website'])))));
        $pdf = Pdf::loadView('pdf.secretary-official-letter', compact('document', 'layout', 'letter', 'number', 'date', 'name', 'logo', 'contacts', 'content', 'partials'))
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])
            ->setPaper($settings['paper_size'] ?? 'a4', $settings['paper_orientation'] ?? 'portrait');
        $pdf->render();
        if ($layout['page_numbers']) {
            $pdf->getDomPDF()->get_canvas()->page_script(function ($page, $total, $canvas, $fonts): void {
                if ($page > 1) {
                    $font = $fonts->getFont('DejaVu Sans', 'normal');
                    $text = (string) $page;
                    $canvas->text(($canvas->get_width() - $fonts->getTextWidth($text, $font, 9)) / 2, $canvas->get_height() - 23, $text, $font, 9);
                }
            });
        }

        return ['bytes' => $pdf->output(), 'variables' => $variables];
    }

    private function resolveVariables(string $html, array $variables, ?string $logo): string
    {
        if (! str_contains($html, '{{')) {
            return $html;
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $root = $dom->getElementsByTagName('div')->item(0);
        foreach (iterator_to_array((new DOMXPath($dom))->query('.//text()[contains(., "{{")]', $root)) as $node) {
            $parts = preg_split('/(\{\{[a-z._]+\}\})/', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts as $index => $part) {
                $key = $index % 2 === 1 ? substr($part, 2, -2) : null;
                if (in_array($key, ['organization.logo', 'cooperativa.logo_img'], true)) {
                    if ($logo) {
                        $image = $dom->createElement('img');
                        $image->setAttribute('src', $logo);
                        $image->setAttribute('width', '64');
                        $image->setAttribute('alt', '');
                        $node->parentNode->insertBefore($image, $node);
                    }
                } else {
                    $text = $key !== null && array_key_exists($key, $variables) ? (string) $variables[$key] : $part;
                    $node->parentNode->insertBefore($dom->createTextNode($text), $node);
                }
            }
            $node->parentNode->removeChild($node);
        }

        return implode('', array_map(fn ($child) => $dom->saveHTML($child), iterator_to_array($root->childNodes)));
    }
}
