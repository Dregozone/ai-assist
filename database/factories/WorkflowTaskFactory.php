<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Workflow;
use App\Models\WorkflowTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTask>
 */
class WorkflowTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'key' => 't'.$this->faker->unique()->numberBetween(1, 9999),
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'depends_on' => [],
            'status' => TaskStatus::Pending,
            'output' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the task has completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskStatus::Completed,
            'output' => $this->faker->paragraph(),
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
