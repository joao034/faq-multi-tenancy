<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class GenerateDocumentEmbeddings extends Command
{
    protected $signature = 'documents:embed';

    protected $description = 'Generate embeddings for documents that do not have one';

    public function handle(EmbeddingService $embeddingService): int
    {
        $documents = Document::query()
            ->whereNull('embedding')
            ->get();

        if ($documents->isEmpty()) {
            $this->info('All documents already have embeddings.');

            return self::SUCCESS;
        }

        $embeddings = $embeddingService->generate(
            $documents->map(fn (Document $document): string => $document->embeddingSourceText())->all()
        );

        foreach ($documents as $index => $document) {
            $document->update(['embedding' => $embeddings[$index]]);
        }

        $this->info("Generated embeddings for {$documents->count()} documents.");

        return self::SUCCESS;
    }
}
