<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Models\Customer;
use App\Services\SaleOrderService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSaleOrder extends CreateRecord
{
    protected static string $resource = SaleOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(SaleOrderService::class);
        $customer = Customer::findOrFail($data['customer_id']);

        // Extract items from data
        $items = $data['items'] ?? [];

        // Create draft order using service
        return $service->createDraft(
            customer: $customer,
            items: $items,
            attributes: null,
            createdBy: auth()->id()
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
