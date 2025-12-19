<?php

namespace App\Filament\Admin\Resources\ProductImports\Pages;

use App\Filament\Admin\Resources\ProductImports\ProductImportResource;
use App\Models\ProductImport;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductImport extends CreateRecord
{
    protected static string $resource = ProductImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['file_path'])) {
            throw new \RuntimeException('Berkas products.csv wajib diunggah.');
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var ProductImport $record */
        $record = static::getModel()::create([
            'file_name' => $data['file_name'] ?? 'products.csv',
            'file_path' => $data['file_path'],
            'file_disk' => 'public',
            'default_warehouse_id' => $data['default_warehouse_id'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $record->process();

        if ($record->status === ProductImport::STATUS_COMPLETED) {
            Notification::make()
                ->title('Import produk selesai')
                ->success()
                ->body("Berhasil mengimpor {$record->imported_rows} baris produk.")
                ->send();
        } else {
            Notification::make()
                ->title('Import produk memiliki kendala')
                ->danger()
                ->body('Periksa log untuk melihat baris yang gagal diproses.')
                ->send();
        }

        return $record;
    }

    public function getTitle(): string
    {
        return 'Import Produk Baru';
    }
}
