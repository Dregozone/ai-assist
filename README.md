# AI Assist — Multi-Agent Workflow Visualiser

A Laravel app that takes a plain-English request, runs it through a **multi-agent AI pipeline**, and draws the whole thing as a live graph in your browser while it happens.

Everything runs **locally on your own machine** — including the AI model. No API keys, no cloud provider, no per-token cost.

```
🧑 You → ✨ PreProcessor → 🧩 Processor → ⚙️ Task ─┐
                                        ⚙️ Task ─┼→ 🔎 PostProcessor → 🎉 Outcome
                                        ⚙️ Task ─┘
```

---

## What it does

You type something like *"Plan a small launch email for a new coffee blend"* and hit **Run workflow**. Four kinds of AI agent then take turns:

| Agent | Role |
| --- | --- |
| **PreProcessor** | Rewrites your rough text into a single clear, unambiguous prompt. |
| **Processor** | Breaks that prompt into 2–6 concrete tasks, with a `depends_on` dependency graph so independent tasks can run in parallel. |
| **TaskExecutor** | Runs one task, seeing only the overall goal, its own instructions, and the outputs of its prerequisites. One queued job per task. |
| **PostProcessor** | Compares the combined outputs against your **original** request and decides pass/fail with a one-line verdict. |

Each stage runs as a queued job. As jobs progress they broadcast over WebSockets, so the graph on screen animates in real time — nodes pulse while active, edges flow while carrying data, and you can **hover any connecting line to see the exact text passed between steps**. When the run finishes, an **Output** button opens the full result, ready to copy.

Every run is saved, so the **Recent runs** list lets you click back into any previous one.

---

## Tech stack

- **PHP 8.4** / **Laravel 13**
- **Livewire 4** + **Flux UI 2** + **Alpine.js** — the reactive front end
- **Tailwind CSS 4** + **Vite 8** — styling and bundling
- **Laravel AI SDK** (`laravel/ai`) — agents, structured JSON output, timeouts
- **Laravel Reverb** — WebSocket server for live updates
- **Laravel Echo** + **Pusher JS** — browser-side subscriptions
- **SQLite** — database *and* queue backend (nothing extra to install)
- **Pest 5**, **Pint**, **Larastan** — tests, formatting, static analysis
- **LM Studio** + **Qwen** — the local model that powers every agent

---

## Prerequisites

Install these first. The versions below are what the project is developed against; anything newer of the same major version is fine.

| Tool | Version | Notes |
| --- | --- | --- |
| [PHP](https://www.php.net/downloads) | 8.3+ (8.4 recommended) | On macOS/Windows, [Laravel Herd](https://herd.laravel.com) installs PHP and Composer together in one click — the easiest route. |
| [Composer](https://getcomposer.org/download/) | 2.x | PHP package manager. Bundled with Herd. |
| [Node.js](https://nodejs.org) | 20+ (22 recommended) | Includes `npm`. |
| [Git](https://git-scm.com/downloads) | any recent | For cloning. |
| [LM Studio](https://lmstudio.ai) | latest | Runs the AI model locally. See below. |

**Hardware note:** the Qwen model used here is a small 4-billion-parameter model, chosen specifically so it runs comfortably on an ordinary laptop — roughly **8 GB of free RAM** is enough. It will use your GPU automatically if you have one, but it does not need one.

---

## Step 1 — Set up LM Studio and the Qwen model

This app talks to a local AI server instead of a cloud API. LM Studio provides that server.

1. **Download and install LM Studio** from [lmstudio.ai](https://lmstudio.ai) (Windows, macOS, and Linux builds are available). Open it once installed.

2. **Download the model.** Click the **Discover** tab (magnifying glass icon in the left sidebar) and search for:

   ```
   qwen3.5-4b
   ```

   Pick the **Qwen3.5 4B** result and click **Download**. It's a couple of gigabytes, so give it a few minutes.

   > **Why this model?** Qwen3.5 4B is small, fast, and reliably produces the **structured JSON** the pipeline depends on — the Processor agent has to return a valid task list, not prose. Larger models work too (see [Using a different model](#using-a-different-model)), they're just slower.

3. **Start the local server.** Click the **Developer** tab (`>_` icon in the left sidebar), then:
   - Toggle the server **Status** to **Running**.
   - Confirm the port reads **1234** — that's the default this app expects.
   - Load `qwen3.5-4b` into the server using the model selector at the top.

4. **Check it's working.** Open a terminal and run:

   ```bash
   curl http://localhost:1234/v1/models
   ```

   You should get back a chunk of JSON listing `qwen3.5-4b`. If you get "connection refused", the server isn't running yet — go back to step 3.

**Leave LM Studio running.** The app calls it constantly while a workflow executes.

---

## Step 2 — Clone and install the app

```bash
git clone https://github.com/Dregozone/ai-assist.git
cd ai-assist
```

Install the PHP and JavaScript dependencies:

```bash
composer install
npm install
```

---

## Step 3 — Configure the environment

Copy the example environment file:

```bash
# macOS / Linux
cp .env.example .env

# Windows (PowerShell)
Copy-Item .env.example .env
```

Generate the application encryption key:

```bash
php artisan key:generate
```

Generate credentials for the WebSocket server (this fills in the empty `REVERB_*` values in your `.env`):

```bash
php artisan reverb:install
```

Now open `.env` in an editor and set the model name to the Qwen model you downloaded:

```dotenv
AI_PROVIDER=lmstudio
LMSTUDIO_URL=http://localhost:1234/v1
LMSTUDIO_API_KEY=lm-studio
LMSTUDIO_MODEL=qwen3.5-4b
```

`LMSTUDIO_API_KEY` is a placeholder — LM Studio doesn't check it, but the OpenAI-compatible client requires *something* to be set.

While you're in there, give the queue plenty of time to wait on the model (local inference is slower than a cloud API):

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=360
```

---

## Step 4 — Create the database

The project uses SQLite, so the "database" is just a file:

```bash
# macOS / Linux
touch database/database.sqlite

# Windows (PowerShell)
New-Item -ItemType File database/database.sqlite
```

Then create the tables:

```bash
php artisan migrate
```

---

## Step 5 — Run it

One command starts everything you need:

```bash
composer run dev
```

That runs four processes side by side, colour-coded in your terminal:

| Process | What it does |
| --- | --- |
| `server` | The Laravel web server on **http://localhost:8000** |
| `queue` | The worker that actually executes the AI jobs |
| `reverb` | The WebSocket server pushing live updates to the browser |
| `vite` | Builds CSS/JS and hot-reloads the front end |

**All four must be running.** If the queue worker isn't up, your workflow will sit at "Pending" forever; if Reverb isn't up, the graph won't update live.

Now open **http://localhost:8000/workflow**, type a request, and press **Run workflow**.

Press `Ctrl+C` to stop everything.

<details>
<summary>Prefer separate terminals?</summary>

```bash
php artisan serve
php artisan queue:work --tries=1 --timeout=360
php artisan reverb:start
npm run dev
```

</details>

---

## Try it

Good first prompts — small enough to finish quickly on a local model:

- `Plan a small launch email for a new coffee blend`
- `Write three tagline options for a dog-walking app, then pick the best one and explain why`
- `Outline a 20-minute beginner presentation on how HTTPS works`

Watch for:

- **Nodes pulsing blue** while an agent is thinking.
- **Dashed animated edges** while data is in flight.
- **Hovering an edge** to see the exact text being handed between steps — this is the most interesting part; you can watch your rough sentence become an optimised prompt, then a task list, then real output.
- The **Output** button (appears once the run finishes) for the final copyable result.

A first run takes a few minutes on a laptop. Tasks that don't depend on each other run in parallel, so wider plans finish faster than they look.

---

## Testing

```bash
php artisan test --compact
```

Or the full quality gate — formatting check, static analysis, then the test suite:

```bash
composer run test
```

Individual tools:

```bash
composer run lint          # auto-fix code style with Pint
composer run types:check   # Larastan static analysis (level 7)
php artisan test --compact --filter=Workflow
```

The tests fake the queue and the AI provider, so **they don't need LM Studio running**.

---

## Configuration reference

Everything relevant lives in `.env`:

| Variable | Default | Purpose |
| --- | --- | --- |
| `AI_PROVIDER` | `lmstudio` | Which provider in `config/ai.php` the agents use. |
| `LMSTUDIO_URL` | `http://localhost:1234/v1` | LM Studio's OpenAI-compatible endpoint. |
| `LMSTUDIO_API_KEY` | `lm-studio` | Ignored by LM Studio, but must be non-empty. |
| `LMSTUDIO_MODEL` | — | The model identifier, e.g. `qwen3.5-4b`. |
| `QUEUE_CONNECTION` | `database` | Where queued jobs are stored. |
| `DB_QUEUE_RETRY_AFTER` | `360` | Seconds before a stuck job is retried. Keep this above the job timeout. |
| `BROADCAST_CONNECTION` | `reverb` | The broadcasting driver. |
| `REVERB_*` | generated | WebSocket server credentials, set by `reverb:install`. |

### Using a different model

Any model LM Studio can serve will work — download it in LM Studio, load it in the Developer tab, and change `LMSTUDIO_MODEL` in `.env` to match the identifier LM Studio shows. Larger Qwen variants (`qwen3.5-8b` and up) give better task plans at the cost of speed.

Because the agents talk through an OpenAI-compatible interface, you can also point the app at a cloud provider instead. `config/ai.php` already defines OpenAI, Anthropic, Gemini, Groq, Ollama, OpenRouter and others — set `AI_PROVIDER` to one of those keys and add its API key to `.env`.

### Agent timeouts

Each agent carries a `#[Timeout(300)]` attribute (`app/Ai/Agents/`) and each job sets `$timeout = 320`. Local models are slow; if you switch to a larger model and hit timeouts, raise both — and keep `DB_QUEUE_RETRY_AFTER` higher than the job timeout so the queue doesn't retry a job that's still running.

---

## How it works under the hood

```
Livewire page (/workflow)
   │  submit()
   ▼
Workflow record ──► PreProcessWorkflow  (job) ──► PreProcessorAgent   → optimized_prompt
                        │
                        ▼
                    ProcessWorkflow     (job) ──► ProcessorAgent      → WorkflowTask rows
                        │
                        ▼
                 WorkflowOrchestrator ──► ExecuteTask (job, one per ready task)
                        ▲                      │   └─► TaskExecutorAgent → output
                        └──────────────────────┘   (re-checks dependencies after each completion)
                        │  all tasks terminal
                        ▼
                  PostProcessWorkflow   (job) ──► PostProcessorAgent  → success + summary
```

Every state change dispatches a `WorkflowUpdated` event on the `workflow.{id}` channel. The Livewire component subscribes to only its own workflow's channel and calls `$refresh`, with a 2-second `wire:poll` as a safety net while a run is in flight.

`WorkflowOrchestrator` is the interesting bit. After each task finishes it re-examines the whole graph inside a locked transaction: it atomically claims any task whose dependencies are now satisfied, cascade-fails tasks whose prerequisites failed, and uses a single conditional `UPDATE` to guarantee post-processing is dispatched exactly once even when several tasks finish simultaneously.

### Key files

| Path | What's there |
| --- | --- |
| `resources/views/pages/⚡workflow.blade.php` | The whole dashboard — Livewire component and graph markup in one file. |
| `resources/views/components/workflow/` | The `node` and `edge` Blade components that draw the graph. |
| `app/Ai/Agents/` | The four agents: instructions and JSON output schemas. |
| `app/Jobs/` | One queued job per pipeline stage. |
| `app/Services/WorkflowOrchestrator.php` | Dependency resolution and task dispatch. |
| `app/Events/WorkflowUpdated.php` | The broadcast event driving live UI updates. |
| `app/Models/`, `app/Enums/` | `Workflow` / `WorkflowTask` and their status enums. |
| `config/ai.php` | Provider definitions, including the `lmstudio` entry. |

---

## Troubleshooting

**The workflow stays on "Pending" and nothing happens.**
The queue worker isn't running. Make sure `composer run dev` is going (look for the `queue` process), or start `php artisan queue:work` yourself.

**The graph doesn't update until I refresh the page.**
Reverb isn't running, or the browser can't reach it. Confirm the `reverb` process is up, that the `REVERB_*` values in `.env` are filled in, and that `npm run dev` is running so `resources/js/echo.js` is loaded. Reverb settings are baked into the front-end bundle at build time — if you change any `REVERB_*` value, restart Vite.

**"Connection refused", or the run fails immediately.**
LM Studio isn't serving. Open LM Studio → Developer tab → set Status to **Running**, load the model, then verify with `curl http://localhost:1234/v1/models`.

**"The pre-processor did not return structured output."**
The model returned prose instead of valid JSON. This usually means the loaded model isn't the one configured, or is too small to follow schemas reliably. Check that `LMSTUDIO_MODEL` exactly matches the identifier shown in LM Studio, and stick to `qwen3.5-4b` or larger.

**Jobs time out or repeat.**
Local inference is slow. Raise the agent timeouts (`app/Ai/Agents/`) and job timeouts (`app/Jobs/`), and keep `DB_QUEUE_RETRY_AFTER` in `.env` above the job timeout.

**"Unable to locate file in Vite manifest".**
The front-end assets aren't built. Run `npm run dev` (development) or `npm run build` (one-off).

**Styling looks broken, or changes don't appear.**
Restart Vite. If you edited Blade templates and see stale output, run `php artisan view:clear`.

---

## License

MIT.
