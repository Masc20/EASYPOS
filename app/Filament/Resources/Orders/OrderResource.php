<?php

namespace App\Filament\Resources\Orders;

use App\Domains\PointOfSale\Models\Order;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'Sales & Transactions';

    protected static ?string $navigationLabel = 'Transaction History';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transaction History';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
