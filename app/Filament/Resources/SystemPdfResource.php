<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Navigation entry for System PDFs — scoped to template_category = 'system'.
 * Reuses all form/table logic from DocumentTemplateResource.
 */
class SystemPdfResource extends DocumentTemplateResource
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'PDFs do Sistema';
    protected static ?string $modelLabel = 'PDF do Sistema';
    protected static ?string $pluralModelLabel = 'PDFs do Sistema';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 10;
    protected static bool $shouldRegisterNavigation = true;

    // Slug único para não colidir com DocumentTemplateResource
    protected static ?string $slug = 'system-pdfs';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('template_category', 'system');
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
            'index'  => Pages\ListSystemPdfs::route('/'),
            'create' => Pages\CreateSystemPdf::route('/create'),
            'view'   => Pages\ViewDocumentTemplate::route('/{record}'),
            'edit'   => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('template_category', 'system')->count() ?: null;
    }
}
