<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),

                TextColumn::make('cashier.name')
                    ->label('Cashier')
                    ->searchable()
                    ->sortable()
                    ->default('Floor Staff')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->default('Global')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order_type')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state): string => OrderType::colorFor($state))
                    ->formatStateUsing(fn (string $state): string => strtoupper(OrderType::labelFor($state))),

                TextColumn::make('table_number')
                    ->label('Table')
                    ->placeholder('—'),

                TextColumn::make('total_amount')
                    ->label('Net Total')
                    ->money(SystemConstants::CURRENCY_CODE)
                    ->sortable()
                    ->alignment('right')
                    ->weight('bold'),

                TextColumn::make('payments.payment_method')
                    ->label('Payment')
                    ->badge()
                    ->default('Unpaid')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ucwords(str_replace('_', ' ', implode(', ', $state))) : ucwords(str_replace('_', ' ', (string) $state))),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => OrderStatus::colorFor($state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Branch')
                    ->visible(fn () => ! auth()->user()?->isBranchScoped()),

                SelectFilter::make('order_type')
                    ->label('Dining Mode')
                    ->options(OrderType::options()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrderStatus::options()),
            ])
            ->defaultPaginationPageOption(SystemConstants::DEFAULT_TABLE_PAGE_SIZE)
            ->recordAction(null)
            ->recordActions([
                Action::make('view_receipt')
                    ->label('View Receipt')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->color('primary')
                    ->modalHeading(fn (Order $record): string => "Official Receipt: {$record->order_number}")
                    ->modalWidth('md')
                    ->modalContent(fn (Order $record) => view('filament.modals.order-receipt', [
                        'order' => $record->loadMissing(['items.meal', 'payments', 'cashier', 'branch', 'kitchenTicket']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('void_order')
                    ->label('Void / Cancel Order')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Order $record): bool {
                        $user = auth()->user();
                        if (! $user || $record->status === OrderStatus::CANCELLED->value) {
                            return false;
                        }

                        if ($user->isOwner()) {
                            return true;
                        }

                        if (! $user->can('pos.refund')) {
                            return false;
                        }

                        if ($user->isBranchScoped() && (int) $record->branch_id !== (int) $user->branch_id) {
                            return false;
                        }

                        return true;
                    })
                    ->authorize(function (Order $record): bool {
                        $user = auth()->user();
                        if (! $user || $record->status === OrderStatus::CANCELLED->value) {
                            return false;
                        }

                        if ($user->isOwner()) {
                            return true;
                        }

                        if (! $user->can('pos.refund')) {
                            return false;
                        }

                        if ($user->isBranchScoped() && (int) $record->branch_id !== (int) $user->branch_id) {
                            return false;
                        }

                        return true;
                    })
                    ->requiresConfirmation()
                    ->modalHeading(fn (Order $record): string => "Void Order #{$record->order_number}")
                    ->modalDescription('Voiding this order will mark payments as refunded and evict any linked ticket from the kitchen queue. This action cannot be undone.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->placeholder('e.g., Customer changed mind, wrong items entered')
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data, VoidOrderAction $action) {
                        $user = auth()->user();
                        if ($user && $user->isBranchScoped() && (int) $record->branch_id !== (int) $user->branch_id) {
                            Notification::make()
                                ->title('Unauthorized')
                                ->body('You cannot void orders belonging to other branches.')
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            $action->execute([
                                'order_id' => $record->id,
                                'manager_id' => auth()->id(),
                                'reason' => $data['reason'],
                                'skip_pin_check' => true,
                            ]);

                            Notification::make()
                                ->title('Order Voided')
                                ->body("Order #{$record->order_number} has been cancelled and refunded.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Void Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}