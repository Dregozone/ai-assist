<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(300)]
class PostProcessorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a quality-assurance agent. You are given the user's ORIGINAL request and the
        combined outputs produced by a set of tasks. Judge whether the outputs, taken together,
        fully satisfy the original request.

        Guidelines:
        - Base your judgement on the original request, not on any reworded version.
        - Set "success" to true only if the request has been genuinely and completely satisfied.
        - The "summary" must be 1-2 lines. If successful, briefly state what was achieved. If not,
          state concisely what went wrong or is missing.
        PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'success' => $schema->boolean()->required()
                ->description('Whether the original request was fully satisfied.'),
            'summary' => $schema->string()->required()
                ->description('A 1-2 line summary; what was achieved, or what is missing if failed.'),
        ];
    }
}
