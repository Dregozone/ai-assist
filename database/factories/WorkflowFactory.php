<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'input_text' => $this->faker->sentence(),
            'status' => WorkflowStatus::Pending,
            'optimized_prompt' => null,
            'post_success' => null,
            'post_summary' => null,
            'error' => null,
        ];
    }
}
