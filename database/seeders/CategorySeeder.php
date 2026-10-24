<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Jobs', 'slug' => 'jobs'],
            ['name' => 'Gigs', 'slug' => 'gigs'],
            ['name' => 'Internships', 'slug' => 'internships'],
            ['name' => 'Scholarships', 'slug' => 'scholarships'],
            ['name' => 'Training', 'slug' => 'training'],
            ['name' => 'Grants', 'slug' => 'grants'],
            ['name' => 'Business Support', 'slug' => 'business-support'],
            ['name' => 'Tenders', 'slug' => 'tenders'],
        ];

        foreach ($categories as $sortOrder => $attributes) {
            Category::updateOrCreate(
                ['slug' => $attributes['slug']],
                [
                    ...$attributes,
                    'description' => null,
                    'icon_key' => null,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }
    }
}
