<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Runs the package migration stubs so a workbench (`testbench serve`) app has
 * the webhook tables available without a manual publish step.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->stubs() as $stub) {
            (include $stub)->up();
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->stubs()) as $stub) {
            (include $stub)->down();
        }
    }

    /**
     * @return array<int, string>
     */
    protected function stubs(): array
    {
        return [
            dirname(__DIR__, 3).'/database/migrations/create_webhooks_table.php.stub',
            dirname(__DIR__, 3).'/database/migrations/create_webhook_delivery_logs_table.php.stub',
        ];
    }
};
