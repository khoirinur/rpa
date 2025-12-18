<?php

namespace App\Filament\Admin\Resources\ChartOfAccountImports\Pages;

use App\Filament\Admin\Resources\ChartOfAccountImports\ChartOfAccountImportResource;
use App\Models\ChartOfAccountImport;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateChartOfAccountImport extends CreateRecord
{
    protected static string $resource = ChartOfAccountImportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var ChartOfAccountImport $record */
        $record = static::getModel()::create([
            'file_name' => $data['file_name'] ?? 'coa-import.csv',
            'file_path' => $data['file_path'] ?? null,
            'file_disk' => 'public',
            'default_warehouse_id' => $data['default_warehouse_id'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $record->process();

        if ($record->status === ChartOfAccountImport::STATUS_COMPLETED) {
            Notification::make()
                ->title('Import COA selesai')
                ->success()
                ->body("Berhasil mengimpor {$record->imported_rows} akun.")
                ->send();
        } else {
            Notification::make()
                ->title('Import COA memiliki kendala')
                ->danger()
                ->body('Periksa log untuk detail baris yang gagal.')
                ->send();
        }

        return $record;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['file_path'])) {
            throw new \RuntimeException('Berkas CSV wajib diunggah.');
        }

        return $data;
    }

    public function getTitle(): string
    {
        return 'Import COA Baru';
    }
}
