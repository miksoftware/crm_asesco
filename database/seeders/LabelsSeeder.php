<?php

namespace Database\Seeders;

use App\Models\Label;
use Illuminate\Database\Seeder;

class LabelsSeeder extends Seeder
{
    public function run(): void
    {
        $systemLabels = [
            ['name' => 'Pagó', 'slug' => 'paid', 'color' => '#22c55e', 'is_system' => true, 'order' => 1],
            ['name' => 'Promesa de pago', 'slug' => 'promise', 'color' => '#f59e0b', 'is_system' => true, 'order' => 2],
            ['name' => 'No contesta', 'slug' => 'no_answer', 'color' => '#6b7280', 'is_system' => true, 'order' => 3],
            ['name' => 'Número equivocado', 'slug' => 'wrong_number', 'color' => '#ef4444', 'is_system' => true, 'order' => 4],
            ['name' => 'Rechaza pago', 'slug' => 'rejected', 'color' => '#dc2626', 'is_system' => true, 'order' => 5],
            ['name' => 'En negociación', 'slug' => 'negotiating', 'color' => '#3b82f6', 'is_system' => true, 'order' => 6],
        ];

        foreach ($systemLabels as $label) {
            Label::updateOrCreate(
                ['slug' => $label['slug']],
                $label
            );
        }
    }
}
