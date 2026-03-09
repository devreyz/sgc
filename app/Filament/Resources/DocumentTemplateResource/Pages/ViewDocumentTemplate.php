<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use App\Models\PdfLayoutTemplate;
use App\Services\TemplatedPdfService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Response;

class ViewDocumentTemplate extends ViewRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        $record  = $this->record;
        $actions = [Actions\EditAction::make()];

        if ($record->template_category === "custom") {
            $neededVars = $this->detectVarsNeedingInput($record);
            $formSchema  = $this->buildPdfFormSchema($record, $neededVars);

            $action = Actions\Action::make("generate_pdf")
                ->label("Gerar PDF")
                ->icon("heroicon-o-document-arrow-down")
                ->color("success")
                ->action(function (array $data) use ($record) {
                    ["custom" => $customVars, "context" => $contextVars] = $this->extractPdfVars($data);

                    $service = app(TemplatedPdfService::class);
                    $pdf     = $service->generateCustomTemplate($record, $customVars, $contextVars);

                    return Response::streamDownload(
                        fn () => print($pdf->output()),
                        \Illuminate\Support\Str::slug($record->name) . "-" . now()->format("Ymd-His") . ".pdf",
                        ["Content-Type" => "application/pdf"]
                    );
                });

            if (!empty($formSchema)) {
                $action = $action
                    ->form($formSchema)
                    ->modalHeading("Preencher Dados para o PDF")
                    ->modalDescription("Os campos abaixo são necessários para gerar o documento. Campos opcionais podem ser deixados em branco.")
                    ->modalSubmitActionLabel("Gerar PDF");
            }

            $actions[] = $action;
        }

        if ($record->template_category === "system") {
            $actions[] = Actions\Action::make("info_system")
                ->label("Como usar")
                ->icon("heroicon-o-information-circle")
                ->color("info")
                ->modalHeading("Como usar este modelo do sistema")
                ->modalContent(function () use ($record) {
                    $def      = $record->getSystemDefinition();
                    $sections = implode(", ", $record->visible_sections ?? array_keys($def["sections"] ?? []));
                    $cols     = implode(", ", $record->visible_columns  ?? array_keys($def["columns"]   ?? []));

                    return \Illuminate\Support\HtmlString::fromString(
                        "<div class=\"space-y-3 text-sm\">"
                        . "<p><strong>PDF gerado:</strong> " . e($def["label"] ?? $record->name) . "</p>"
                        . "<p><strong>View Blade:</strong> <code class=\"bg-gray-100 px-1 rounded\">" . e($def["blade_view"] ?? "N/A") . "</code></p>"
                        . "<p><strong>Seções ativas:</strong> " . e($sections) . "</p>"
                        . "<p><strong>Colunas ativas:</strong> " . e($cols ?: "(todas)") . "</p>"
                        . "<p class=\"text-gray-500\">Este modelo é aplicado automaticamente ao gerar este tipo de PDF no sistema.</p>"
                        . "</div>"
                    );
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel("Fechar");
        }

        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }

    private function detectVarsNeedingInput(DocumentTemplate $record): array
    {
        $systemPrefixes = ["cooperativa.", "data.", "pagina.", "cor.", "documento.", "financeiro."];

        $contentParts = array_filter([
            $record->content ?? "",
            $record->header_layout_id
                ? (PdfLayoutTemplate::find($record->header_layout_id)?->content ?? "") : "",
            $record->footer_layout_id
                ? (PdfLayoutTemplate::find($record->footer_layout_id)?->content ?? "") : "",
            $record->cover_layout_id
                ? (PdfLayoutTemplate::find($record->cover_layout_id)?->content  ?? "") : "",
            $record->back_cover_layout_id
                ? (PdfLayoutTemplate::find($record->back_cover_layout_id)?->content ?? "") : "",
        ]);

        if (empty($contentParts)) {
            return [];
        }

        preg_match_all("/\{\{([a-z_]+\.[a-z_]+)\}\}/i", implode(" ", $contentParts), $matches);

        $vars = array_unique($matches[0] ?? []);

        return array_values(array_filter($vars, function (string $var) use ($systemPrefixes): bool {
            $inner = substr($var, 2, -2);
            foreach ($systemPrefixes as $prefix) {
                if (str_starts_with($inner, $prefix)) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function buildPdfFormSchema(DocumentTemplate $record, array $neededVars): array
    {
        if (empty($neededVars)) {
            return [];
        }

        $allVarLabels = PdfLayoutTemplate::getAvailableVariables();
        $customFields = collect($record->custom_fields ?? []);

        $groups = [];
        foreach ($neededVars as $var) {
            $inner = substr($var, 2, -2);
            $ns    = (string) strstr($inner, ".", true);
            $groups[$ns][] = $var;
        }

        $sectionOrder  = ["custom", "associado", "financeiro"];
        $orderedGroups = [];
        foreach ($sectionOrder as $ns) {
            if (isset($groups[$ns])) {
                $orderedGroups[$ns] = $groups[$ns];
            }
        }
        foreach ($groups as $ns => $vars) {
            if (!isset($orderedGroups[$ns])) {
                $orderedGroups[$ns] = $vars;
            }
        }

        $schema = [];

        foreach ($orderedGroups as $ns => $vars) {
            $fields = [];

            foreach ($vars as $var) {
                $inner   = substr($var, 2, -2);
                $safeKey = str_replace(".", "_", $inner);
                $label   = $allVarLabels[$var] ?? $inner;

                if ($ns === "custom") {
                    $fieldKey    = substr($inner, 7);
                    $fieldConfig = $customFields->firstWhere("key", $fieldKey) ?? [];
                    $label       = $fieldConfig["label"]   ?? $fieldKey;
                    $defaultVal  = $fieldConfig["default"] ?? null;
                    $Required    = (bool) ($fieldConfig["required"] ?? true);

                    $fields[] = match ($fieldConfig["type"] ?? "text") {
                        "textarea" => Forms\Components\Textarea::make("var_{$safeKey}")
                            ->label($label)->required($Required)->default($defaultVal)->rows(3),
                        "number"   => Forms\Components\TextInput::make("var_{$safeKey}")
                            ->label($label)->required($Required)->default($defaultVal)->numeric(),
                        "currency" => Forms\Components\TextInput::make("var_{$safeKey}")
                            ->label($label)->required($Required)->default($defaultVal)->prefix("R$")->numeric(),
                        "date"     => Forms\Components\DatePicker::make("var_{$safeKey}")
                            ->label($label)->required($Required)->default($defaultVal)->displayFormat("d/m/Y"),
                        "select"   => Forms\Components\Select::make("var_{$safeKey}")
                            ->label($label)->required($Required)->default($defaultVal)
                            ->options(
                                collect(explode("\n", $fieldConfig["options"] ?? ""))
                                    ->filter()
                                    ->mapWithKeys(fn ($v) => [trim($v) => trim($v)])
                                    ->toArray()
                            ),
                        default    => Forms\Components\TextInput::make("var_{$safeKey}")
                            ->label($label)->required($Required)->default($defaultVal),
                    };
                } elseif ($ns === "financeiro") {
                    $fields[] = Forms\Components\TextInput::make("var_{$safeKey}")
                        ->label($label)
                        ->required(false)
                        ->numeric()
                        ->prefix("R$");
                } else {
                    $fields[] = Forms\Components\TextInput::make("var_{$safeKey}")
                        ->label($label)
                        ->required(false);
                }
            }

            if (!empty($fields)) {
                $sectionLabel = match ($ns) {
                    "custom"     => "Campos Personalizados",
                    "associado"  => "Dados do Associado",
                    "financeiro" => "Dados Financeiros",
                    default      => ucfirst($ns),
                };

                $schema[] = Forms\Components\Section::make($sectionLabel)
                    ->schema($fields)
                    ->collapsed($ns !== "custom");
            }
        }

        return $schema;
    }

    private function extractPdfVars(array $data): array
    {
        $customVars  = [];
        $contextVars = [];

        foreach ($data as $formKey => $value) {
            if (!str_starts_with($formKey, "var_") || $value === null || $value === "") {
                continue;
            }

            $inner       = substr($formKey, 4);
            $dotted      = preg_replace("/_/", ".", $inner, 1);
            $originalVar = "{{" . $dotted . "}}";

            if (str_starts_with($dotted, "custom.")) {
                $customVars[$originalVar] = $value;
            } else {
                $contextVars[$originalVar] = $value;
            }
        }

        return ["custom" => $customVars, "context" => $contextVars];
    }
}
