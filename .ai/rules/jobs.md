---
paths:
  - 'app/Jobs/**'
---

# Jobs

## AI workflow pipeline: stateless agents + service orchestrator
The /workflow visualiser runs a 4-stage queued pipeline: PreProcessWorkflow → ProcessWorkflow → ExecuteTask (per task) → PostProcessWorkflow. Agents live in app/Ai/Agents and are stateless (implement Agent + HasStructuredOutput only; no Conversational). Since laravel/ai 1.x, `prompt()` on a HasStructuredOutput agent is typed as StructuredAgentResponse, so jobs read `$response['key']` directly — no `instanceof` guard (PHPStan flags it as always true). Task DAG timing is controlled by App\Services\WorkflowOrchestrator (plain service), NOT by an AI agent: it atomically claims Pending→Queued tasks whose depends_on are all Completed, and guards ExecutingTasks→PostProcessing to dispatch post-processing once. Every state change dispatches App\Events\WorkflowUpdated (ShouldBroadcastNow, public channel workflow.{id}); the Livewire page listens via getListeners() with the dot-prefixed name ".workflow.updated". Provider is the local LM Studio default (no #[Provider] attribute). Job $timeout=320 requires queue retry_after=360 (DB_QUEUE_RETRY_AFTER).
