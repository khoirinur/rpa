<?php

namespace App\Filament\Admin\Resources\CustomerImports\Pages;

use App\Filament\Admin\Resources\CustomerImports\CustomerImportResource;
use App\Models\CustomerImport;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomerImport extends CreateRecord
{
    protected static string $resource = CustomerImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['file_path'])) {
            throw new \RuntimeException('Berkas customer.csv wajib diunggah.');
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var CustomerImport $record */
        $record = static::getModel()::create([
            'file_name' => $data['file_name'] ?? 'customer.csv',
            'file_path' => $data['file_path'],
            'file_disk' => 'public',
            'fallback_customer_category_id' => $data['fallback_customer_category_id'] ?? null,
            'default_warehouse_id' => $data['default_warehouse_id'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $record->process();

        if ($record->status === CustomerImport::STATUS_COMPLETED) {
            Notification::make()
                ->title('Import customer selesai')
                ->success()
                ->body("Berhasil mengimpor {$record->imported_rows} baris customer.")
                ->send();
        } else {
            Notification::make()
                ->title('Import customer memiliki kendala')
                ->danger()
                ->body('Periksa log untuk melihat baris yang gagal diproses.')
                ->send();
        }

        return $record;
    }

    public function getTitle(): string
    {
        return 'Import Customer Baru';
    }
}
