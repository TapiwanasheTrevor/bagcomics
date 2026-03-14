<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'interval' => 'forever',
                'price' => 0,
                'description' => 'Read free comics and preview paid titles.',
                'features' => [
                    'Access to all free comics',
                    'Preview first 2 pages of paid comics',
                    'Save reading progress',
                    'Community comments',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 0,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'monthly'],
            [
                'name' => 'Monthly',
                'interval' => 'monthly',
                'price' => 9.99,
                'description' => 'Unlimited access to the full BAG Comics library.',
                'features' => [
                    'Unlimited access to all comics',
                    'New releases on day one',
                    'Ad-free reading experience',
                    'Save & sync progress across devices',
                    'Priority support',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'yearly'],
            [
                'name' => 'Annual',
                'interval' => 'yearly',
                'price' => 99.99,
                'original_price' => 119.88, // 12 * 9.99
                'description' => 'Best value — save 17% with an annual plan.',
                'features' => [
                    'Everything in Monthly',
                    'Save 17% vs monthly billing',
                    'Exclusive early access to new series',
                    'Annual subscriber badge',
                ],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ]
        );
    }
}
