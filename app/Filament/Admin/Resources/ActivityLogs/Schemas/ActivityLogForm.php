<?php

namespace App\Filament\Admin\Resources\ActivityLogs\Schemas;

use App\Models\ActivityLog;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ActivityLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Aktivitas')
                    ->schema([
                        TextInput::make('module')
                            ->label('Modul')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('action_type')
                            ->label('Tipe Aktivitas')
                            ->formatStateUsing(fn ($state) => ActivityLog::formatActionType($state))
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        TextInput::make('user.name')
                            ->label('Pengguna')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('warehouse.name')
                            ->label('Gudang')
                            ->placeholder('Semua Gudang')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('subject_type')
                            ->label('Tipe Data')
                            ->formatStateUsing(fn (?string $state) => $state ? Str::headline(class_basename($state)) : '-')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('subject_id')
                            ->label('ID Data')
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('performed_at')
                            ->label('Dilakukan Pada')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
                Section::make('Payload Aktivitas')
                    ->schema([
                        Textarea::make('changes')
                            ->label('Perubahan Data')
                            ->rows(8)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                            ->placeholder('Tidak ada perubahan terekam')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Textarea::make('metadata')
                            ->label('Metadata')
                            ->rows(6)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                            ->placeholder('Tidak ada metadata tambahan')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Textarea::make('revert_payload')
                            ->label('Payload Revert')
                            ->rows(6)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                            ->placeholder('Belum tersedia data revert')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
                Section::make('Status Pembatalan')
                    ->schema([
                        DateTimePicker::make('reverted_at')
                            ->label('Dibatalkan Pada')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('revertedBy.name')
                            ->label('Dibatalkan Oleh')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('revert_reason')
                            ->label('Alasan Pembatalan')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
