<?php

namespace App\Filament\Admin\Resources\ActivityLogs;

use App\Filament\Admin\Resources\ActivityLogs\Pages\EditActivityLog;
use App\Filament\Admin\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Admin\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Filament\Admin\Resources\ActivityLogs\Schemas\ActivityLogForm;
use App\Filament\Admin\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Audit & Monitoring';

    protected static ?string $recordTitleAttribute = 'description';

    public static function getNavigationLabel(): string
    {
        return 'Activity Log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Activity Log';
    }

    public static function form(Schema $schema): Schema
    {
        return ActivityLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
            'edit' => EditActivityLog::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
