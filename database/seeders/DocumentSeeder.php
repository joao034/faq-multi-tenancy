<?php

namespace Database\Seeders;

use App\Models\Document;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Fictional knowledge base for the MVP. No external files (PDF/DOCX/JSON)
     * are used on purpose; everything the assistant can answer lives here.
     * `embedding` starts as null and is filled later by the embeddings
     * backfill command (Phase 3), once EmbeddingService exists.
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
                'business_id' => 2,
                'title' => 'Horario de atención',
                'question' => '¿Cuál es el horario de atención?',
                'answer' => 'Atendemos todos los días de 06:00 a 22:00.',
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