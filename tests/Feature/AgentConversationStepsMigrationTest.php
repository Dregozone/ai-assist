<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function stepsMigration(): object
{
    return require database_path('migrations/2026_10_26_081720_update_agent_conversation_messages_for_steps.php');
}

it('stores conversation messages in the laravel/ai 1.x shape', function () {
    expect(Schema::hasColumns('agent_conversation_messages', ['steps', 'status']))->toBeTrue()
        ->and(Schema::hasColumn('agent_conversation_messages', 'tool_calls'))->toBeFalse()
        ->and(Schema::hasColumn('agent_conversation_messages', 'tool_results'))->toBeFalse()
        ->and(Schema::hasColumn('agent_conversation_messages', 'approval_state'))->toBeFalse();
});

it('backfills existing messages into steps', function () {
    $migration = stepsMigration();
    $migration->down();

    $message = [
        'conversation_id' => 'conv-1',
        'agent' => 'App\\Ai\\Agents\\TaskExecutorAgent',
        'attachments' => '[]',
        'usage' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('agent_conversation_messages')->insert([
        [...$message, 'id' => 'm1', 'role' => 'user', 'content' => 'Hi', 'tool_calls' => '[]', 'tool_results' => '[]', 'meta' => '{}'],
        [
            ...$message,
            'id' => 'm2',
            'role' => 'assistant',
            'content' => 'Done.',
            'tool_calls' => json_encode([['id' => 'call_1', 'name' => 'read_file', 'arguments' => []]]),
            'tool_results' => json_encode([['id' => 'call_1', 'name' => 'read_file', 'result' => 'contents']]),
            'meta' => json_encode(['reasoning' => 'Thinking', 'provider_steps' => []]),
        ],
    ]);

    $migration->up();

    $user = DB::table('agent_conversation_messages')->find('m1');
    $assistant = DB::table('agent_conversation_messages')->find('m2');

    expect(json_decode($user->steps, true))->toBe([])
        ->and($assistant->status)->toBe('completed')
        ->and(json_decode($assistant->meta, true))->toBe([])
        ->and(json_decode($assistant->steps, true))->toBe([
            [
                'content' => '',
                'tool_calls' => [['id' => 'call_1', 'name' => 'read_file', 'arguments' => [], 'result' => 'contents']],
                'reasoning' => '',
                'replay_blocks' => [],
                'provider_tool_calls' => [],
            ],
            [
                'content' => 'Done.',
                'tool_calls' => [],
                'reasoning' => 'Thinking',
                'replay_blocks' => [],
                'provider_tool_calls' => [],
            ],
        ]);
});
