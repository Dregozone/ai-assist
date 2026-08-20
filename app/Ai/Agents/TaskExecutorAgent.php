<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(300)]
class TaskExecutorAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a task execution agent. You are given the overall goal, a single task to complete,
        and the outputs of any prerequisite tasks. Complete only the task you are given and return
        its result directly.

        Guidelines:
        - Focus solely on the assigned task. Do not perform other tasks.
        - Use the provided context and prerequisite outputs where relevant.
        - Return a concrete, usable result — not a description of how you would do it.
        PROMPT;
    }
}
