<?php

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Prompts\AgentPrompt;

it('prompts the agent with the given input', function () {
    AnonymousAgent::fake(['Laravel is a PHP web framework.']);

    $this->get(route('ai.test', ['input' => 'What is Laravel?']));

    AnonymousAgent::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->contains('What is Laravel?')
    );
});

it('falls back to a default input when none is given', function () {
    AnonymousAgent::fake(['Laravel is a PHP web framework.']);

    $this->get(route('ai.test'));

    AnonymousAgent::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->contains('Laravel framework')
    );
});

it('uses the lmstudio provider by default', function () {
    expect(config('ai.default'))->toBe('lmstudio')
        ->and(config('ai.providers.lmstudio.driver'))->toBe('openai-compatible')
        ->and(config('ai.providers.lmstudio.url'))->toBe('http://localhost:1234/v1')
        ->and(config('ai.providers.lmstudio.models.text.default'))->toBe('google/gemma-4-e4b');
});
