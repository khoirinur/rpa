<?php

namespace App\Filament\Admin\Resources\SupplierImports\Pages;

use App\Filament\Admin\Resources\SupplierImports\SupplierImportResource;
use App\Models\SupplierImport;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupplierImport extends CreateRecord
{
    protected static string $resource = SupplierImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['file_path'])) {
            throw new \RuntimeException('Berkas supplier.csv wajib diunggah.');
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var SupplierImport $record */
        $record = static::getModel()::create([
            'file_name' => $data['file_name'] ?? 'supplier.csv',
            'file_path' => $data['file_path'],
            'file_disk' => 'public',
            'fallback_supplier_category_id' => $data['fallback_supplier_category_id'] ?? null,
            'default_warehouse_id' => $data['default_warehouse_id'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $record->process();

        if ($record->status === SupplierImport::STATUS_COMPLETED) {
            Notification::make()
                ->title('Import supplier selesai')
                ->success()
                ->body("Berhasil mengimpor {$record->imported_rows} baris supplier.")
                ->send();
        } else {
            Notification::make()
                ->title('Import supplier memiliki kendala')
                ->danger()
                ->body('Periksa log untuk melihat baris yang gagal diproses.')
                ->send();
        }

        return $record;
    }

    public function getTitle(): string
    {
        return 'Import Supplier Baru';
    }
}
