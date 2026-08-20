<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(300)]
class ProcessorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a planning agent. Given an optimised prompt, decompose the work into a list of
        concrete, independently-executable tasks that together fully satisfy the prompt.

        Guidelines:
        - Give every task a stable, unique key such as "t1", "t2", "t3".
        - Each task description must be self-contained: an executor will only see that description
          plus the outputs of the tasks it depends on. Include enough context to act on it alone.
        - Use "depends_on" to list the keys of tasks that must complete first. Leave it empty for
          tasks that can start immediately.
        - Maximise parallelism: only add a dependency when the task genuinely needs a prior result.
        - Prefer a small number of meaningful tasks (typically 2-6) over many trivial ones.
        - Do not attempt to complete the tasks yourself; only plan them.
        PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tasks' => $schema->array()->required()->items(
                $schema->object([
                    'key' => $schema->string()->required()
                        ->description('Stable unique identifier for the task, e.g. "t1".'),
                    'title' => $schema->string()->required()
                        ->description('Short human-readable title.'),
                    'description' => $schema->string()->required()
                        ->description('Self-contained instructions for completing this task.'),
                    'depends_on' => $schema->array()->items($schema->string())
                        ->description('Keys of tasks that must complete before this one runs.'),
                ])
            ),
        ];
    }
}
