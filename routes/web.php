<?php

use Illuminate\Support\Facades\Route;

use function Laravel\Ai\agent;

Route::view('/', 'welcome')->name('home');

Route::livewire('/workflow', 'pages::workflow')->name('workflow');

Route::get('ai-test', function () {
    $input = request('input', 'In one sentence, what is the Laravel framework?');

    $response = agent(instructions: 'You are a helpful assistant. Answer concisely.')
        ->prompt($input, timeout: 300);

    return $response->text;
})->name('ai.test');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
