<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Branches\Models\Branch;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Support\Enums\StaffRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $slug = 'activity-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Staff & Access Control';

    protected static ?string $navigationLabel = 'Logs';

    protected static ?string $modelLabel = 'Activity Log';

    protected static ?string $pluralModelLabel = 'Activity Logs';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->canAccessAdmin()) {
            return false;
        }

        if ($user->isOwner() || $user->hasRole(StaffRole::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->isBranchScoped() && $user->branch_id === null) {
            return false;
        }

        return $user->hasRole(StaffRole::BRANCH_MANAGER->value)
            || $user->can('reports.view')
            || $user->can('users.manage');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user:id,name,emp_id', 'branch:id,name']);
        $user = auth()->user();

        if ($user && $user->isBranchScoped()) {
            $query->where('branch_id', $user->effectiveBranchId());
        } elseif (request()->filled('branch_id') && is_numeric(request()->query('branch_id'))) {
            $query->where('branch_id', (int) request()->query('branch_id'));
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();
        $isBranchScoped = (bool) ($user && $user->isBranchScoped());

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('actor_name')
                    ->label('Actor')
                    ->searchable(['actor_name', 'actor_emp_id'])
                    ->description(fn (ActivityLog $record): ?string => $record->actor_emp_id),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('Global / HQ')
                    ->toggleable(),
                TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'attendance' => 'info',
                        'auth', 'security' => 'warning',
                        'pos' => 'success',
                        'kitchen', 'menu' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('event')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed', 'denied' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('description')
                    ->searchable()
                    ->wrap()
                    ->limit(80),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily('mono')
                    ->toggleable(),
            ])
            ->filters([
                ...($isBranchScoped ? [] : [
                    SelectFilter::make('branch_id')
                        ->label('Branch')
                        ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all()),
                ]),
                SelectFilter::make('category')
                    ->options([
                        'attendance' => 'Clock In / Out',
                        'auth' => 'Authentication',
                        'pos' => 'POS & Orders',
                        'kitchen' => 'Kitchen Display',
                        'menu' => 'Menu Catalog',
                        'security' => 'Security & Locks',
                        'admin' => 'Back-Office',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'denied' => 'Denied',
                    ]),
                SelectFilter::make('event')
                    ->options([
                        'clock_in' => 'Clock In',
                        'clock_out' => 'Clock Out',
                        'login_failed' => 'Failed Login',
                        'pos_checkout' => 'POS Checkout',
                        'order_void' => 'Order Void',
                        'order_void_failed' => 'Failed Order Void',
                        'kitchen_ticket_claimed' => 'Kitchen Ticket Claimed',
                        'kitchen_ticket_status' => 'Kitchen Status Updated',
                        'kitchen_ticket_recalled' => 'Kitchen Ticket Recalled',
                        'menu_availability_toggled' => 'Menu Availability Toggled',
                        'menu_portions_updated' => 'Menu Portions Updated',
                        'terminal_locked' => 'Terminal Locked',
                        'terminal_unlocked' => 'Terminal Unlocked',
                        'terminal_unlock_failed' => 'Terminal Unlock Failed',
                        'access_denied' => 'Access Denied (403)',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
