<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Navigation entry for Custom Documents — scoped to template_category = 'custom'.
 * Reuses all form/table logic from DocumentTemplateResource.
 */
class CustomDocumentResource extends DocumentTemplateResource
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Documentos Personalizados';
    protected static ?string $modelLabel = 'Documento Personalizado';
    protected static ?string $pluralModelLabel = 'Documentos Personalizados';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 11;
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $slug = 'custom-documents';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('template_category', 'custom');
    }

    public static function form(Form $form): Form
    {
        return parent::form($form);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomDocuments::route('/'),
            'create' => Pages\CreateCustomDocument::route('/create'),
            'view'   => Pages\ViewDocumentTemplate::route('/{record}'),
            'edit'   => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('template_category', 'custom')->count() ?: null;
    }
}
