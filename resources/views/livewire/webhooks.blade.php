<div>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl">Webhooks</flux:heading>
                <flux:text class="mt-1">Configure webhooks to receive event notifications in external systems.</flux:text>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="create" class="cursor-pointer">
                Add Webhook
            </flux:button>
        </div>

        @if (session('webhook-success'))
            <flux:callout variant="success" class="mb-4" icon="check-circle" :heading="session('webhook-success')" />
        @endif

        {{-- Search --}}
        <div class="mb-4">
            <flux:input type="search" wire:model.live.debounce.300ms="search" placeholder="Search webhooks..." icon="magnifying-glass" />
        </div>

        @if ($webhooks->isEmpty() && ! $search)
            <div class="rounded-lg border border-dashed border-zinc-200 p-12 text-center dark:border-zinc-700">
                <flux:icon.bolt class="mx-auto h-12 w-12 text-zinc-400" />
                <flux:heading size="lg" class="mt-4">No webhooks configured</flux:heading>
                <flux:text class="mt-2">Create a webhook to start receiving event notifications.</flux:text>
                <flux:button variant="primary" wire:click="create" class="mt-6 cursor-pointer">Add Webhook</flux:button>
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Name &amp; URL</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Events</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Last Triggered</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($webhooks as $webhook)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $webhook->name }}</div>
                                    <div class="max-w-xs truncate text-sm text-zinc-500 dark:text-zinc-400" title="{{ $webhook->url }}">{{ $webhook->url }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (array_slice($webhook->events ?? [], 0, 3) as $event)
                                            <flux:badge size="sm">{{ $event }}</flux:badge>
                                        @endforeach
                                        @if (count($webhook->events ?? []) > 3)
                                            <flux:badge size="sm" color="zinc">+{{ count($webhook->events) - 3 }}</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <button wire:click="toggleActive('{{ $webhook->uuid }}')" class="cursor-pointer">
                                        <flux:badge size="sm" :color="$webhook->is_active ? 'green' : 'zinc'">
                                            {{ $webhook->is_active ? 'Active' : 'Inactive' }}
                                        </flux:badge>
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $webhook->last_triggered_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="cursor-pointer" />
                                        <flux:menu>
                                            <flux:menu.item icon="queue-list" wire:click="viewLogs('{{ $webhook->uuid }}')">Logs</flux:menu.item>
                                            <flux:menu.item icon="pencil" wire:click="edit('{{ $webhook->uuid }}')">Edit</flux:menu.item>
                                            <flux:menu.item icon="trash" variant="danger"
                                                wire:click="delete('{{ $webhook->uuid }}')"
                                                wire:confirm="Delete this webhook? This cannot be undone.">Delete</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    No webhooks found matching "{{ $search }}".
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $webhooks->links() }}
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model="showModal" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $isEditing ? 'Edit Webhook' : 'Create Webhook' }}</flux:heading>

            <flux:input wire:model="name" label="Name" placeholder="My Integration" />

            <flux:input wire:model="url" label="Webhook URL" type="url" placeholder="https://example.com/webhook" />

            <flux:input wire:model="secret" label="Secret" type="password"
                placeholder="{{ $isEditing ? 'Leave blank to keep existing' : 'Auto-generated secret' }}" />
            <flux:text size="sm" class="-mt-4">Used to sign webhook payloads for verification (HMAC).</flux:text>

            <div>
                <flux:heading size="sm" class="mb-2">Events</flux:heading>
                @if (empty($availableEvents))
                    <flux:text size="sm" class="text-amber-600 dark:text-amber-400">
                        No event types registered. Register events via <code>ConfigWebhook::registerEvent()</code> or the <code>config-webhook.events</code> config.
                    </flux:text>
                @else
                    <div class="max-h-64 space-y-4 overflow-y-auto rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        @foreach ($availableEvents as $group => $groupEvents)
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ $group }}</p>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach ($groupEvents as $type => $label)
                                        <flux:checkbox wire:model="events" value="{{ $type }}" label="{{ $label }}" />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @error('events') <flux:text size="sm" class="mt-1 text-red-600">{{ $message }}</flux:text> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="maxRetries" label="Max Retries" type="number" min="0" max="10" />
                <flux:input wire:model="timeout" label="Timeout (seconds)" type="number" min="5" max="120" />
            </div>

            <flux:checkbox wire:model="isActive" label="Active" description="When inactive, no events will be delivered." />

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showModal', false)" class="cursor-pointer">Cancel</flux:button>
                <flux:button variant="primary" wire:click="save" class="cursor-pointer">{{ $isEditing ? 'Update' : 'Create' }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delivery Logs Modal --}}
    <flux:modal wire:model="showLogsModal" class="max-w-4xl">
        <div class="space-y-4">
            <flux:heading size="lg">Delivery Logs</flux:heading>

            @if ($deliveryLogs && $deliveryLogs->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-zinc-500">Event</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-zinc-500">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-zinc-500">HTTP</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-zinc-500">Time</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-zinc-500">Attempt</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-zinc-500">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($deliveryLogs as $log)
                                <tr>
                                    <td class="px-4 py-2"><flux:badge size="sm">{{ $log->event_type }}</flux:badge></td>
                                    <td class="px-4 py-2">
                                        <flux:badge size="sm" :color="$log->status->color()">{{ $log->status->label() }}</flux:badge>
                                    </td>
                                    <td class="px-4 py-2 text-zinc-500 dark:text-zinc-400">{{ $log->response_status ?? '-' }}</td>
                                    <td class="px-4 py-2 text-zinc-500 dark:text-zinc-400">{{ $log->response_time_ms ? $log->response_time_ms . 'ms' : '-' }}</td>
                                    <td class="px-4 py-2 text-zinc-500 dark:text-zinc-400">{{ $log->attempt }}</td>
                                    <td class="px-4 py-2 text-zinc-500 dark:text-zinc-400">{{ $log->created_at->format('M d, H:i:s') }}</td>
                                </tr>
                                @if ($log->error_message)
                                    <tr>
                                        <td colspan="6" class="bg-red-50 px-4 py-1 text-xs text-red-500 dark:bg-red-900/10 dark:text-red-400">
                                            {{ $log->error_message }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">{{ $deliveryLogs->links() }}</div>
            @else
                <div class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">No delivery logs yet.</div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="ghost" wire:click="$set('showLogsModal', false)" class="cursor-pointer">Close</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
