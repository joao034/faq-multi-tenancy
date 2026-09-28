<?php

use App\Models\Business;
use App\Models\ChatRequest;
use App\Models\Document;
use App\Models\WhatsAppNumber;
use App\Services\EmbeddingService;
use App\Services\LLMService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Mockery\MockInterface;

it('retrieves only relevant documents from the business addressed by the request', function (): void {
    config([
        'chat.retrieval_top_k' => 1,
        'chat.retrieval_similarity_threshold' => 0.75,
    ]);

    $business = createChatBusiness('+593981111111');
    $otherBusiness = createChatBusiness('+593982222222');

    $document = createChatDocument($business, [0.8, 0.6], 'Abrimos de 09:00 a 18:00.');
    createChatDocument($otherBusiness, [1.0, 0.0], 'Respuesta del otro negocio.');

    $question = '¿A qué hora abren?';

    $this->mock(EmbeddingService::class, function (MockInterface $mock) use ($question): void {
        $mock->shouldReceive('generate')
            ->once()
            ->with([$question])
            ->andReturn([[1.0, 0.0]]);
    });

    $this->mock(LLMService::class, function (MockInterface $mock) use ($business, $document, $question): void {
        $mock->shouldReceive('answer')
            ->once()
            ->withArgs(function (string $receivedQuestion, Collection $matches) use ($business, $document, $question): bool {
                $match = $matches->first();

                return $receivedQuestion === $question
                    && $matches->count() === 1
                    && $match['document']->getKey() === $document->getKey()
                    && (int) $match['document']->business_id === $business->getKey();
            })
            ->andReturn([
                'answer' => 'Abrimos de 09:00 a 18:00.',
                'model' => 'test-model',
                'tokens' => 12,
            ]);
    });

    $response = $this->postJson('/api/v1/chat', [
        'to' => '+593981111111',
        'from' => '+593990000000',
        'message' => $question,
    ]);

    $response->assertOk()->assertExactJson([
        'answer' => 'Abrimos de 09:00 a 18:00.',
        'source' => 'llm',
    ]);

    $this->assertDatabaseHas('chat_requests', [
        'business_id' => $business->getKey(),
        'from' => '+593990000000',
        'message' => $question,
        'model' => 'test-model',
        'tokens' => 12,
        'status' => 'completed',
    ]);
});

it('returns 404 when the receiving WhatsApp number is unknown or inactive', function (string $phoneNumber, bool $createNumber, bool $active): void {
    $business = Business::factory()->create();

    if ($createNumber) {
        WhatsAppNumber::query()->create([
            'business_id' => $business->getKey(),
            'phone_number' => $phoneNumber,
            'active' => $active,
        ]);
    }

    $response = $this->postJson('/api/v1/chat', [
        'to' => $phoneNumber,
        'from' => '+593990000000',
        'message' => '¿A qué hora abren?',
    ]);

    $response->assertNotFound()->assertExactJson([
        'message' => 'Business not found.',
    ]);

    $this->assertDatabaseEmpty('chat_requests');
})->with([
    'unknown number' => ['+593981111111', false, true],
    'inactive number' => ['+593982222222', true, false],
]);

it('returns no context without calling the LLM when every document is below the similarity threshold', function (): void {
    $business = createChatBusiness('+593981111111');
    createChatDocument($business, [0.0, 1.0], 'Este documento no responde la consulta.');

    $question = '¿A qué hora abren?';

    $this->mock(EmbeddingService::class, function (MockInterface $mock) use ($question): void {
        $mock->shouldReceive('generate')
            ->once()
            ->with([$question])
            ->andReturn([[1.0, 0.0]]);
    });

    $this->mock(LLMService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('answer');
    });

    $response = $this->postJson('/api/v1/chat', [
        'to' => '+593981111111',
        'from' => '+593990000000',
        'message' => $question,
    ]);

    $response->assertOk()->assertExactJson([
        'answer' => 'Lo siento, no encuentro información suficiente para responder esa pregunta.',
        'source' => 'no_context',
    ]);

    $this->assertDatabaseHas('chat_requests', [
        'business_id' => $business->getKey(),
        'message' => $question,
        'status' => 'no_context',
    ]);
});

it('returns 429 when the business has reached its monthly request limit', function (): void {
    $business = createChatBusiness('+593981111111', ['monthly_request_limit' => 1]);
    ChatRequest::factory()->for($business)->create();

    $this->mock(EmbeddingService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('generate');
    });

    $this->mock(LLMService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('answer');
    });

    $response = $this->postJson('/api/v1/chat', [
        'to' => '+593981111111',
        'from' => '+593990000000',
        'message' => 'Esta pregunta debe rechazarse.',
    ]);

    $response->assertTooManyRequests()->assertExactJson([
        'message' => 'Monthly request limit exceeded.',
    ]);

    $this->assertDatabaseMissing('chat_requests', [
        'business_id' => $business->getKey(),
        'message' => 'Esta pregunta debe rechazarse.',
    ]);
});

it('does not count requests from a previous month toward the current monthly limit', function (): void {
    $this->travelTo('2025-06-15 12:00:00');

    $business = createChatBusiness('+593981111111', ['monthly_request_limit' => 1]);
    ChatRequest::factory()->for($business)->create([
        'created_at' => '2025-05-31 23:59:59',
    ]);

    $question = 'Pregunta del nuevo mes';

    $this->mock(EmbeddingService::class, function (MockInterface $mock) use ($question): void {
        $mock->shouldReceive('generate')
            ->once()
            ->with([$question])
            ->andReturn([[1.0, 0.0]]);
    });

    $this->mock(LLMService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('answer');
    });

    $response = $this->postJson('/api/v1/chat', [
        'to' => '+593981111111',
        'from' => '+593990000000',
        'message' => $question,
    ]);

    $response->assertOk()->assertJsonPath('source', 'no_context');

    $this->assertDatabaseHas('chat_requests', [
        'business_id' => $business->getKey(),
        'message' => $question,
        'status' => 'no_context',
    ]);
});

it('returns a stored answer when the LLM fails and records the fallback status', function (): void {
    $business = createChatBusiness('+593981111111');
    createChatDocument($business, [1.0, 0.0], 'Abrimos de 09:00 a 18:00.');

    $question = '¿A qué hora abren?';

    $this->mock(EmbeddingService::class, function (MockInterface $mock) use ($question): void {
        $mock->shouldReceive('generate')
            ->once()
            ->with([$question])
            ->andReturn([[1.0, 0.0]]);
    });

    $this->mock(LLMService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('answer')
            ->once()
            ->andThrow(new RuntimeException('The AI provider is unavailable.'));
    });

    Exceptions::fake();

    $response = $this->postJson('/api/v1/chat', [
        'to' => '+593981111111',
        'from' => '+593990000000',
        'message' => $question,
    ]);

    $response->assertOk()->assertExactJson([
        'answer' => 'Abrimos de 09:00 a 18:00.',
        'source' => 'fallback',
    ]);

    $this->assertDatabaseHas('chat_requests', [
        'business_id' => $business->getKey(),
        'message' => $question,
        'status' => 'fallback',
    ]);

    Exceptions::assertReported(RuntimeException::class);
});

it('returns no context when the LLM fails and retrieved documents have no usable answer', function (): void {
    $business = createChatBusiness('+593981111111');
    createChatDocument($business, [1.0, 0.0], '   ');

    $question = '¿A qué hora abren?';

    $this->mock(EmbeddingService::class, function (MockInterface $mock) use ($question): void {
        $mock->shouldReceive('generate')
            ->once()
            ->with([$question])
            ->andReturn([[1.0, 0.0]]);
    });

    $this->mock(LLMService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('answer')
            ->once()
            ->andThrow(new RuntimeException('The AI provider is unavailable.'));
    });

    Exceptions::fake();

    $response = $this->postJson('/api/v1/chat', [
        'to' => '+593981111111',
        'from' => '+593990000000',
        'message' => $question,
    ]);

    $response->assertOk()->assertExactJson([
        'answer' => 'Lo siento, no encuentro información suficiente para responder esa pregunta.',
        'source' => 'no_context',
    ]);

    $this->assertDatabaseHas('chat_requests', [
        'business_id' => $business->getKey(),
        'message' => $question,
        'status' => 'no_context',
    ]);

    Exceptions::assertReported(RuntimeException::class);
});

it('returns 500 and marks the request as failed when embedding generation throws', function (): void {
    $business = createChatBusiness('+593981111111');
    $question = '¿A qué hora abren?';

    $this->mock(EmbeddingService::class, function (MockInterface $mock) use ($question): void {
        $mock->shouldReceive('generate')
            ->once()
            ->with([$question])
            ->andThrow(new RuntimeException('The embedding provider is unavailable.'));
    });

    $this->mock(LLMService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('answer');
    });

    Exceptions::fake();

    $response = $this->postJson('/api/v1/chat', [
        'to' => '+593981111111',
        'from' => '+593990000000',
        'message' => $question,
    ]);

    $response->assertInternalServerError()->assertExactJson([
        'message' => 'Unable to process request.',
    ]);

    $this->assertDatabaseHas('chat_requests', [
        'business_id' => $business->getKey(),
        'message' => $question,
        'status' => 'failed',
    ]);

    Exceptions::assertReported(RuntimeException::class);
});

it('returns 422 with field errors when required chat fields are missing', function (): void {
    $response = $this->postJson('/api/v1/chat', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'from', 'message'])
        ->assertJsonPath('errors.to.0', 'El campo "to" (número del negocio) es requerido.')
        ->assertJsonPath('errors.from.0', 'El campo "from" (número del cliente) es requerido.')
        ->assertJsonPath('errors.message.0', 'El campo "message" es requerido.');

    $this->assertDatabaseEmpty('chat_requests');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function createChatBusiness(string $phoneNumber, array $attributes = []): Business
{
    $business = Business::factory()->create($attributes);

    WhatsAppNumber::query()->create([
        'business_id' => $business->getKey(),
        'phone_number' => $phoneNumber,
        'active' => true,
    ]);

    return $business;
}

/**
 * @param  array<int, float>  $embedding
 */
function createChatDocument(Business $business, array $embedding, string $answer): Document
{
    return $business->documents()->create([
        'title' => 'Información del negocio',
        'question' => '¿A qué hora abren?',
        'answer' => $answer,
        'embedding' => $embedding,
    ]);
}
