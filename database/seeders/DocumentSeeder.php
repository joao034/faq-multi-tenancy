<?php

namespace Database\Seeders;

use App\Models\Document;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Fictional knowledge base for the MVP. No external files (PDF/DOCX/JSON)
     * are used on purpose; everything the assistant can answer lives here.
     * `embedding` starts as null and is filled by the `documents:embed`
     * command after seeding.
     */
    public function run(): void
    {
        $documents = [
            [
                'business_id' => 1,
                'title' => 'Horario de atención',
                'question' => '¿Cuál es el horario de atención?',
                'answer' => 'Atendemos de lunes a sábado de 15:00 a 22:00.',
            ],
            [
                'business_id' => 1,
                'title' => 'Delivery',
                'question' => '¿Realizan entregas?',
                'answer' => 'Realizamos entregas dentro de Ambato con un costo de $2.',
            ],
            [
                'business_id' => 1,
                'title' => 'Bebidas sin café',
                'question' => '¿Tienen bebidas sin café?',
                'answer' => 'Sí, ofrecemos chocolate caliente e infusiones.',
            ],
            [
                'business_id' => 1,
                'title' => 'Reservas',
                'question' => '¿Puedo reservar una mesa?',
                'answer' => 'No tomamos reservas; atendemos por orden de llegada.',
            ],
            [
                'business_id' => 1,
                'title' => 'Métodos de pago',
                'question' => '¿Qué métodos de pago aceptan?',
                'answer' => 'Aceptamos efectivo y transferencias bancarias.',
            ],
            [
                'business_id' => 2,
                'title' => 'Horario de atención',
                'question' => '¿Cuál es el horario de atención?',
                'answer' => 'Atendemos todos los días de 06:00 a 22:00.',
            ],
            [
                'business_id' => 2,
                'title' => 'Variedad de pan',
                'question' => '¿Qué tipos de pan ofrecen?',
                'answer' => 'Ofrecemos pan de sal, pan de dulce y productos de pastelería. La disponibilidad puede variar durante el día.',
            ],
            [
                'business_id' => 2,
                'title' => 'Pasteles por encargo',
                'question' => '¿Puedo pedir un pastel personalizado?',
                'answer' => 'Sí, recibimos pedidos de pasteles personalizados con al menos 24 horas de anticipación.',
            ],
            [
                'business_id' => 2,
                'title' => 'Pedidos para retirar',
                'question' => '¿Puedo reservar pan para retirarlo después?',
                'answer' => 'Sí, puedes hacer un pedido por adelantado y coordinar su retiro en el local.',
            ],
            [
                'business_id' => 2,
                'title' => 'Métodos de pago',
                'question' => '¿Qué métodos de pago aceptan?',
                'answer' => 'Aceptamos efectivo y transferencias bancarias.',
            ],
        ];

        foreach ($documents as $document) {
            Document::query()->firstOrCreate(
                [
                    'business_id' => $document['business_id'],
                    'title' => $document['title'],
                ],
                $document
            );
        }
    }
}
