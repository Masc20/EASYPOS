<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Domains\PointOfSale\Models\Order;
use Filament\Actions\Action;
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
                    ->default('Floor Staff'),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->default('Global')
                    ->sortable(),

                TextColumn::make('order_type')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'dine_in' => 'primary',
                        'takeout' => 'warning',
                        'delivery' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper(str_replace('_', ' ', $state))),

                TextColumn::make('table_number')
                    ->label('Table')
                    ->placeholder('—'),

                TextColumn::make('total_amount')
                    ->label('Net Total')
                    ->money('PHP')
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
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending', 'in_kitchen' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Branch'),

                SelectFilter::make('order_type')
                    ->label('Dining Mode')
                    ->options([
                        'dine_in' => 'Dine-In',
                        'takeout' => 'Takeout',
                        'delivery' => 'Delivery',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
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
            ]);
    }
}
