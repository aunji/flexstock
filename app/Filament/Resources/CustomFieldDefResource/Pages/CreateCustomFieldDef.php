<?php

namespace App\Filament\Resources\CustomFieldDefResource\Pages;

use App\Filament\Resources\CustomFieldDefResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomFieldDef extends CreateRecord
{
    protected static string $resource = CustomFieldDefResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = app('current_company_id');

        return $data;
    }
}
