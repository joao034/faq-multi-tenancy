<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\ChatRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatRequest>
 */
class ChatRequestFactory extends Factory
{
    protected $model = ChatRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'from' => fake()->numerify('+5939########'),
            'message' => fake()->sentence(),
            'model' => config('ai.providers.'.config('ai.default').'.models.text.default'),
            'tokens' => fake()->numberBetween(1, 500),
            'status' => 'completed',
        ];
    }
}
