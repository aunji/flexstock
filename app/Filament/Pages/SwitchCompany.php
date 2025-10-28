<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SwitchCompany extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Switch Company';

    protected static ?int $navigationSort = 999;

    protected static ?string $navigationGroup = 'Settings';

    protected static string $view = 'filament.pages.switch-company';

    public ?int $company_id = null;

    public function mount(): void
    {
        $this->company_id = session('current_company_id');
    }

    public function form(Form $form): Form
    {
        $user = auth()->user();
        $companies = $user->companies()
            ->where('is_active', true)
            ->get()
            ->pluck('name', 'id')
            ->toArray();

        return $form
            ->schema([
                Select::make('company_id')
                    ->label('Select Company')
                    ->options($companies)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            session(['current_company_id' => $state]);

                            Notification::make()
                                ->title('Company switched successfully')
                                ->success()
                                ->send();

                            // Redirect to refresh the page with new tenant scope
                            redirect()->to('/admin');
                        }
                    }),
            ])
            ->statePath('data');
    }

    public function getCurrentCompanyName(): ?string
    {
        return app('current_company')?->name;
    }
}
