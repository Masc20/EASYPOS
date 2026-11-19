<?php

namespace Tests\Feature\E2E\Concerns;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesPosAndKitchenFixtures;

trait E2ETestHelpers
{
    use CreatesPosAndKitchenFixtures;

    protected Branch $branch1;

    protected Branch $branch2;

    protected User $owner;

    protected User $superAdmin;

    protected User $manager1;

    protected User $manager2;

    protected User $cashier1;

    protected User $cashier2;

    protected User $chef1;

    protected User $chef2;

    protected User $cook1;

    protected User $cook2;

    /**
     * Seed canonical roles/permissions and resolve Branch 1 & Branch 2 actors.
     *
     * @return array<string, mixed>
     */
    protected function seedE2EActors(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::where('code', 'B01')->firstOrFail();
        $this->branch2 = Branch::where('code', 'B02')->firstOrFail();

        $this->owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $this->superAdmin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $this->manager1 = User::where('email', 'mgr-b01@easypos.com')->firstOrFail();

        $this->manager2 = User::firstOrCreate(
            ['email' => 'mgr-b02@easypos.com'],
            [
                'emp_id' => 'B02-MGR-001',
                'name' => 'Branch Two Manager',
                'branch_id' => $this->branch2->id,
                'password' => Hash::make('password'),
                'pin_code' => Hash::make('8888'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $this->manager2->syncRoles([StaffRole::BRANCH_MANAGER->value]);

        $this->cashier1 = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cashier2 = User::where('emp_id', 'B02-CSH-001')->firstOrFail();
        $this->chef1 = User::where('emp_id', 'B01-CHF-001')->firstOrFail();
        $this->chef2 = User::where('emp_id', 'B02-CHF-001')->firstOrFail();
        $this->cook1 = User::where('emp_id', 'B01-COK-001')->firstOrFail();
        $this->cook2 = User::where('emp_id', 'B02-COK-001')->firstOrFail();

        return [
            'branch1' => $this->branch1,
            'branch2' => $this->branch2,
            'owner' => $this->owner,
            'superAdmin' => $this->superAdmin,
            'manager1' => $this->manager1,
            'manager2' => $this->manager2,
            'cashier1' => $this->cashier1,
            'cashier2' => $this->cashier2,
            'chef1' => $this->chef1,
            'chef2' => $this->chef2,
            'cook1' => $this->cook1,
            'cook2' => $this->cook2,
        ];
    }

    /**
     * Discover Markdown documentation files in docs/ (excluding scratch PLAN.MD).
     *
     * @return array<int, array{path: string, name: string, content: string}>
     */
    protected function findSecurityDocs(): array
    {
        $files = glob(base_path('docs/*.md')) ?: [];
        $docs = [];

        foreach ($files as $path) {
            $name = basename($path);
            if (strcasecmp($name, 'PLAN.MD') === 0 || ! is_file($path)) {
                continue;
            }

            $content = file_get_contents($path);
            $docs[] = [
                'path' => $path,
                'name' => $name,
                'content' => $content === false ? '' : $content,
            ];
        }

        return $docs;
    }

    /**
     * Opaque-box discovery of the admin log viewer URL from registered routes.
     */
    protected function findAdminLogViewerUrl(): ?string
    {
        foreach (Route::getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');
            if (
                in_array('GET', $route->methods(), true)
                && str_starts_with($uri, 'admin/')
                && str_contains(strtolower($uri), 'log')
                && ! str_contains(strtolower($uri), 'login')
                && ! str_contains(strtolower($uri), 'logout')
                && ! str_contains($uri, '{')
            ) {
                return '/'.$uri;
            }
        }

        return null;
    }

    /**
     * Opaque-box discovery of a dedicated Sales CSV export GET route.
     */
    protected function findSalesCsvExportUrl(): ?string
    {
        foreach (Route::getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');
            $lower = strtolower($uri);
            if (
                in_array('GET', $route->methods(), true)
                && str_starts_with($lower, 'admin/sales-records')
                && (str_contains($lower, 'csv') || str_contains($lower, 'export'))
                && ! str_contains($uri, '{')
            ) {
                return '/'.$uri;
            }
        }

        return null;
    }

    /**
     * Opaque-box discovery of a terminal page lock route/trigger.
     *
     * @return array{uri: string, methods: array<int, string>, name: ?string}|null
     */
    protected function findPageLockTrigger(): ?array
    {
        foreach (Route::getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');
            $name = (string) ($route->getName() ?? '');
            if (str_contains(strtolower($uri), 'lock') || str_contains(strtolower($name), 'lock')) {
                return [
                    'uri' => '/'.$uri,
                    'methods' => $route->methods(),
                    'name' => $route->getName(),
                ];
            }
        }

        return null;
    }
}
