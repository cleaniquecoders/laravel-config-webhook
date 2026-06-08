<?php

namespace CleaniqueCoders\ConfigWebhook\Livewire;

use CleaniqueCoders\ConfigWebhook\ConfigWebhook;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use CleaniqueCoders\ConfigWebhook\Models\WebhookDeliveryLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Webhooks extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEditing = false;

    public string $editingId = '';

    // Form fields
    public string $name = '';

    public string $url = '';

    public string $secret = '';

    /** @var array<int, string> */
    public array $events = [];

    public int $maxRetries = 5;

    public int $timeout = 30;

    public bool $isActive = true;

    // Delivery log modal
    public bool $showLogsModal = false;

    public ?string $viewingWebhookId = null;

    public function mount(): void
    {
        $this->authorizeAccess();

        $this->maxRetries = (int) config('config-webhook.defaults.max_retries', 5);
        $this->timeout = (int) config('config-webhook.defaults.timeout', 30);
    }

    protected function authorizeAccess(): void
    {
        $gate = config('config-webhook.gate');

        if ($gate === null) {
            return;
        }

        $allowed = is_callable($gate)
            ? (bool) $gate(auth()->user())
            : Gate::allows($gate);

        abort_unless($allowed, 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->secret = Str::random(40);
        $this->showModal = true;
    }

    public function edit(string $uuid): void
    {
        $webhook = Webhook::where('uuid', $uuid)->firstOrFail();

        $this->editingId = (string) $webhook->id;
        $this->isEditing = true;
        $this->name = $webhook->name;
        $this->url = $webhook->url;
        $this->secret = '';
        $this->events = $webhook->events ?? [];
        $this->maxRetries = $webhook->max_retries;
        $this->timeout = $webhook->timeout;
        $this->isActive = $webhook->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => 'required|string|max:100',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => [Rule::in(app(ConfigWebhook::class)->eventTypes())],
            'maxRetries' => 'required|integer|min:0|max:10',
            'timeout' => 'required|integer|min:5|max:120',
            'secret' => $this->isEditing
                ? 'nullable|string|min:16|max:100'
                : 'required|string|min:16|max:100',
        ];

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            'max_retries' => $this->maxRetries,
            'timeout' => $this->timeout,
            'is_active' => $this->isActive,
        ];

        if ($this->isEditing) {
            $webhook = Webhook::findOrFail($this->editingId);
            if ($this->secret !== '') {
                $data['secret'] = $this->secret;
            }
            $webhook->update($data);
            session()->flash('webhook-success', 'Webhook updated.');
        } else {
            $data['secret'] = $this->secret;
            $data['user_id'] = auth()->id();
            Webhook::create($data);
            session()->flash('webhook-success', 'Webhook created.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(string $uuid): void
    {
        $webhook = Webhook::where('uuid', $uuid)->firstOrFail();
        $webhook->update(['is_active' => ! $webhook->is_active]);
    }

    public function delete(string $uuid): void
    {
        Webhook::where('uuid', $uuid)->delete();
        session()->flash('webhook-success', 'Webhook deleted.');
    }

    public function viewLogs(string $uuid): void
    {
        $webhook = Webhook::where('uuid', $uuid)->firstOrFail();
        $this->viewingWebhookId = (string) $webhook->id;
        $this->showLogsModal = true;
    }

    public function render(): View
    {
        $perPage = (int) config('config-webhook.ui.per_page', 15);

        $webhooks = Webhook::query()
            ->when($this->search, fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('url', 'like', "%{$this->search}%")
            )
            ->latest()
            ->paginate($perPage);

        $deliveryLogs = null;
        if ($this->viewingWebhookId) {
            $deliveryLogs = WebhookDeliveryLog::query()
                ->where('webhook_id', $this->viewingWebhookId)
                ->latest()
                ->paginate(20, pageName: 'logsPage');
        }

        return view('config-webhook::livewire.webhooks', [
            'webhooks' => $webhooks,
            'availableEvents' => app(ConfigWebhook::class)->groupedEvents(),
            'deliveryLogs' => $deliveryLogs,
        ])->layout(config('config-webhook.ui.layout') ?: 'config-webhook::layouts.app');
    }

    protected function resetForm(): void
    {
        $this->isEditing = false;
        $this->editingId = '';
        $this->name = '';
        $this->url = '';
        $this->secret = '';
        $this->events = [];
        $this->maxRetries = (int) config('config-webhook.defaults.max_retries', 5);
        $this->timeout = (int) config('config-webhook.defaults.timeout', 30);
        $this->isActive = true;
    }
}
