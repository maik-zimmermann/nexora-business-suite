<?php

namespace Database\Seeders;

use App\Models\Module;
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
                'stripe_monthly_price_id' => env('MODULE_CRM_MONTHLY_PRICE_ID', 'price_crm_monthly'),
                'stripe_annual_price_id' => env('MODULE_CRM_ANNUAL_PRICE_ID', 'price_crm_annual'),
                'monthly_price_cents' => 2999,
                'annual_price_cents' => 29990,
                'sort_order' => 1,
            ],
            [
                'name' => 'Projects',
                'slug' => 'projects',
                'description' => 'Project management with tasks, milestones, and team collaboration.',
                'stripe_monthly_price_id' => env('MODULE_PROJECTS_MONTHLY_PRICE_ID', 'price_projects_monthly'),
                'stripe_annual_price_id' => env('MODULE_PROJECTS_ANNUAL_PRICE_ID', 'price_projects_annual'),
                'monthly_price_cents' => 1999,
                'annual_price_cents' => 19990,
                'sort_order' => 2,
            ],
            [
                'name' => 'Invoicing',
                'slug' => 'invoicing',
                'description' => 'Create and send invoices, track payments, and manage billing.',
                'stripe_monthly_price_id' => env('MODULE_INVOICING_MONTHLY_PRICE_ID', 'price_invoicing_monthly'),
                'stripe_annual_price_id' => env('MODULE_INVOICING_ANNUAL_PRICE_ID', 'price_invoicing_annual'),
                'monthly_price_cents' => 2499,
                'annual_price_cents' => 24990,
                'sort_order' => 3,
            ],
            [
                'name' => 'HR',
                'slug' => 'hr',
                'description' => 'Human resources management with employee records and time tracking.',
                'stripe_monthly_price_id' => env('MODULE_HR_MONTHLY_PRICE_ID', 'price_hr_monthly'),
                'stripe_annual_price_id' => env('MODULE_HR_ANNUAL_PRICE_ID', 'price_hr_annual'),
                'monthly_price_cents' => 3499,
                'annual_price_cents' => 34990,
                'sort_order' => 4,
            ],
            [
                'name' => 'Support',
                'slug' => 'support',
                'description' => 'Customer support with ticket management and knowledge base.',
                'stripe_monthly_price_id' => env('MODULE_SUPPORT_MONTHLY_PRICE_ID', 'price_support_monthly'),
                'stripe_annual_price_id' => env('MODULE_SUPPORT_ANNUAL_PRICE_ID', 'price_support_annual'),
                'monthly_price_cents' => 1999,
                'annual_price_cents' => 19990,
                'sort_order' => 5,
            ],
        ];

        foreach ($modules as $module) {
            Module::query()->updateOrCreate(
                ['slug' => $module['slug']],
                $module,
            );
        }
    }
}
