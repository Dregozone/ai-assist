<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(300)]
class PreProcessorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a prompt optimisation specialist. You take raw, unstructured text written by a
        human and rewrite it into a single, clear, self-contained prompt that is ready to be
        processed by another AI system.

        Guidelines:
        - Preserve the human's original intent exactly. Do not add new requirements.
        - Make implicit goals explicit and remove ambiguity.
        - State the desired outcome, any constraints, and the expected form of the result.
        - Do not answer or attempt to fulfil the request yourself.
        - Return only the optimised prompt text.
        PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'optimized_prompt' => $schema->string()->required()
                ->description('The rewritten, optimised, AI-ready prompt.'),
        ];
    }
}
