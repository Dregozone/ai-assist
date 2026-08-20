---
paths:
  - composer.json
---

# General

## Reverb requires downgraded Guzzle 7 (psr7 ^2)
laravel/reverb 1.x requires guzzlehttp/psr7 ^2.6, which conflicts with the Guzzle 8 / psr7 3.x the starter kit shipped. Installing Reverb downgraded guzzlehttp/guzzle 8→7.15 and psr7 3→2.13 (via `composer require laravel/reverb -W`). All dependents (aws-sdk, framework, boost) accept Guzzle 7.15, so this is safe. Do not try to bump Guzzle back to 8 while Reverb 1.x is installed — it will break the dependency resolution.
