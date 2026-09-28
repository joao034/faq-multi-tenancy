<?php

namespace App\Services;

use Throwable;

class ChatService
{
    private const NO_CONTEXT_ANSWER = 'Lo siento, no encuentro información suficiente para responder esa pregunta.';

    public function __construct(
        private BusinessResolver $businessResolver,
        private UsageService $usageService,
        private EmbeddingService $embeddingService,
        private RetrievalService $retrievalService,
        private LLMService $llmService,
        private FallbackService $fallbackService,
    ) {}

    /**
     * @param  array{to: string, from: string, message: string}  $data
     * @return array{message: string, http_status?: int}|array{answer: string, source: string}
     */
    public function handle(array $data): array
    {
        $business = $this->businessResolver->resolve($data['to']);

        if ($business === null) {
            return ['message' => 'Business not found.'];
        }

        $chatRequest = $this->usageService->startRequest($business, $data['from'], $data['message']);

        if ($chatRequest === null) {
            return [
                'message' => 'Monthly request limit exceeded.',
                'http_status' => 429,
            ];
        }

        try {
            $queryEmbedding = $this->embeddingService->generate([$data['message']])[0];
            $matches = $this->retrievalService->retrieve($business->id, $queryEmbedding);

            if ($matches->isEmpty()) {
                $chatRequest->update(['status' => 'no_context']);

                return [
                    'answer' => self::NO_CONTEXT_ANSWER,
                    'source' => 'no_context',
                ];
            }

            try {
                $response = $this->llmService->answer($data['message'], $matches);
            } catch (Throwable $exception) {
                report($exception);

                $fallbackAnswer = $this->fallbackService->answer($matches);

                if ($fallbackAnswer === null) {
                    $chatRequest->update(['status' => 'no_context']);

                    return [
                        'answer' => self::NO_CONTEXT_ANSWER,
                        'source' => 'no_context',
                    ];
                }

                $chatRequest->update(['status' => 'fallback']);

                return [
                    'answer' => $fallbackAnswer,
                    'source' => 'fallback',
                ];
            }

            $chatRequest->update([
                'model' => $response['model'],
                'tokens' => $response['tokens'],
                'status' => 'completed',
            ]);

            return [
                'answer' => $response['answer'],
                'source' => 'llm',
            ];
        } catch (Throwable $exception) {
            $chatRequest->update(['status' => 'failed']);

            throw $exception;
        }
    }
}
