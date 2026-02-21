<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Services\StripeProductSync;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            [
                'name' => 'CRM',
                'slug' => 'crm',
                'description' => 'Customer relationship management with contacts, deals, and pipelines.',
                'monthly_price_cents' => 2999,
                'annual_price_cents' => 29990,
                'sort_order' => 1,
            ],
            [
                'name' => 'Projects',
                'slug' => 'projects',
                'description' => 'Project management with tasks, milestones, and team collaboration.',
                'monthly_price_cents' => 1999,
                'annual_price_cents' => 19990,
                'sort_order' => 2,
            ],
            [
                'name' => 'Invoicing',
                'slug' => 'invoicing',
                'description' => 'Create and send invoices, track payments, and manage billing.',
                'monthly_price_cents' => 2499,
                'annual_price_cents' => 24990,
                'sort_order' => 3,
            ],
            [
                'name' => 'HR',
                'slug' => 'hr',
                'description' => 'Human resources management with employee records and time tracking.',
                'monthly_price_cents' => 3499,
                'annual_price_cents' => 34990,
                'sort_order' => 4,
            ],
            [
                'name' => 'Support',
                'slug' => 'support',
                'description' => 'Customer support with ticket management and knowledge base.',
                'monthly_price_cents' => 1999,
                'annual_price_cents' => 19990,
                'sort_order' => 5,
            ],
        ];

        $sync = app(StripeProductSync::class);

        foreach ($modules as $moduleData) {
            $module = Module::query()->updateOrCreate(
                ['slug' => $moduleData['slug']],
                $moduleData,
            );

            $sync->sync($module);
        }
    }
}
