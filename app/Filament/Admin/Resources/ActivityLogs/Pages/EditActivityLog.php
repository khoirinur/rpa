<?php

namespace App\Filament\Admin\Resources\ActivityLogs\Pages;

use App\Filament\Admin\Resources\ActivityLogs\ActivityLogResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EditActivityLog extends EditRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revert')
                ->label('Revert Data')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->disabled(fn (): bool => ! $this->record->canRevert())
                ->visible(fn (): bool => $this->record->canRevert())
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pembatalan Aktivitas')
                ->modalDescription('Data terkait aktivitas ini akan dikembalikan ke kondisi sebelum perubahan. Pastikan Anda memahami konsekuensinya.')
                ->form([
                    Textarea::make('reason')
                        ->label('Alasan Pembatalan')
                        ->placeholder('Jelaskan kenapa aktivitas ini perlu dikembalikan.')
                        ->rows(4)
                        ->maxLength(500)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->record->revert($data['reason'], auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->title('Data berhasil dikembalikan')
                            ->body('Record terkait telah direstore sesuai log ini.')
                            ->success()
                            ->send();
                    } catch (RuntimeException|Throwable $exception) {
                        Log::error('Gagal revert activity log', [
                            'activity_log_id' => $this->record->getKey(),
                            'message' => $exception->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Gagal membatalkan aktivitas')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Kelola Activity Log';
    }
}
