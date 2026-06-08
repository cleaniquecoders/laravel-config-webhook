<?php

namespace CleaniqueCoders\ConfigWebhook\Database\Factories;

use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'url' => $this->faker->url(),
            'secret' => Str::random(40),
            'events' => ['order.created'],
            'headers' => null,
            'is_active' => true,
            'max_retries' => 5,
            'timeout' => 30,
            'user_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * @param  array<int, string>  $events
     */
    public function listeningTo(array $events): static
    {
        return $this->state(fn () => ['events' => $events]);
    }
}
