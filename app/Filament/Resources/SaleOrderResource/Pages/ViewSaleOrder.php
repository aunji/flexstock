<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Models\PaymentSlip;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewSaleOrder extends ViewRecord
{
    protected static string $resource = SaleOrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Order Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('tx_id')
                            ->label('Transaction ID')
                            ->weight('bold')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Customer'),

                        Infolists\Components\TextEntry::make('customer.phone')
                            ->label('Phone'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Draft' => 'gray',
                                'Confirmed' => 'success',
                                'Cancelled' => 'danger',
                                default => 'warning',
                            }),

                        Infolists\Components\TextEntry::make('payment_state')
                            ->label('Payment Status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Received' => 'success',
                                'PendingReceipt' => 'warning',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->badge(),

                        Infolists\Components\TextEntry::make('createdBy.name')
                            ->label('Created By'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])->columns(3),

                Infolists\Components\Section::make('Order Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.sku')
                                    ->label('SKU'),

                                Infolists\Components\TextEntry::make('product.name')
                                    ->label('Product'),

                                Infolists\Components\TextEntry::make('qty')
                                    ->label('Qty')
                                    ->suffix(fn($record) => ' ' . $record->uom),

                                Infolists\Components\TextEntry::make('unit_price')
                                    ->label('Price')
                                    ->money('THB'),

                                Infolists\Components\TextEntry::make('discount_value')
                                    ->label('Discount')
                                    ->money('THB'),

                                Infolists\Components\TextEntry::make('line_total')
                                    ->label('Total')
                                    ->money('THB'),
                            ])
                            ->columns(6),
                    ]),

                Infolists\Components\Section::make('Totals')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->money('THB'),

                        Infolists\Components\TextEntry::make('discount_total')
                            ->label('Total Discount')
                            ->money('THB'),

                        Infolists\Components\TextEntry::make('tax_total')
                            ->label('Total Tax')
                            ->money('THB'),

                        Infolists\Components\TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->money('THB')
                            ->weight('bold')
                            ->size('lg'),
                    ])->columns(4),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        // Edit action (only for Draft orders)
        if ($this->getRecord()->status === 'Draft') {
            $actions[] = Actions\EditAction::make();
        }

        // Approve Payment Slip (Admin only, for transfer payments)
        if (
            $this->getRecord()->payment_method === 'transfer' &&
            $this->getRecord()->payment_state === 'PendingReceipt' &&
            auth()->user()->can('company.admin')
        ) {
            $slip = PaymentSlip::where('sale_order_id', $this->getRecord()->id)
                ->where('status', 'Pending')
                ->first();

            if ($slip) {
                $actions[] = Actions\Action::make('approve_payment')
                    ->label('Approve Payment')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Payment')
                    ->modalDescription('Verify the payment slip and approve this payment?')
                    ->form([
                        Forms\Components\Placeholder::make('slip_preview')
                            ->label('Payment Slip')
                            ->content(fn() => view('filament.components.image-preview', ['path' => $slip->slip_path])),

                        Forms\Components\Textarea::make('approval_notes')
                            ->label('Approval Notes')
                            ->rows(2),
                    ])
                    ->action(function (array $data) use ($slip) {
                        try {
                            // Update payment slip status
                            $slip->update([
                                'status' => 'Approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                                'notes' => ($slip->notes ?? '') . "\nApproval: " . ($data['approval_notes'] ?? ''),
                            ]);

                            // Update sale order payment state
                            $this->getRecord()->update([
                                'payment_state' => 'Received',
                                'approved_by' => auth()->id(),
                            ]);

                            Notification::make()
                                ->title('Payment approved successfully')
                                ->success()
                                ->send();

                            $this->refreshFormData([]);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to approve payment')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    });

                $actions[] = Actions\Action::make('reject_payment')
                    ->label('Reject Payment')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data) use ($slip) {
                        try {
                            $slip->update([
                                'status' => 'Rejected',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                                'notes' => ($slip->notes ?? '') . "\nRejection: " . $data['rejection_reason'],
                            ]);

                            Notification::make()
                                ->title('Payment rejected')
                                ->warning()
                                ->send();

                            $this->refreshFormData([]);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to reject payment')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    });
            }
        }

        return $actions;
    }
}
