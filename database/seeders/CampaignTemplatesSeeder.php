<?php

namespace Database\Seeders;

use App\Models\CampaignTemplate;
use Illuminate\Database\Seeder;

class CampaignTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Recordatorio de pago',
                'content' => "Hola {nombre},\n\nLe recordamos que tiene un saldo pendiente de {val1}.\n\nFecha límite de pago: {val2}\n\nPara más información, responda a este mensaje.\n\nGracias,\nASESCO BPO",
                'placeholders' => ['nombre', 'val1', 'val2'],
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'Confirmación de pago',
                'content' => "Hola {nombre},\n\nHemos recibido su pago por valor de {val1}.\n\nNúmero de referencia: {val2}\n\nGracias por su pago puntual.\n\nASESCO BPO",
                'placeholders' => ['nombre', 'val1', 'val2'],
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'Notificación general',
                'content' => "Estimado(a) {nombre},\n\n{val1}\n\nPara más información, comuníquese con nosotros.\n\nAtentamente,\nASESCO BPO",
                'placeholders' => ['nombre', 'val1'],
                'is_active' => true,
                'order' => 3,
            ],
        ];

        foreach ($templates as $template) {
            CampaignTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
