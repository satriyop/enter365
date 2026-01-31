<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Shared\RecurringTemplate;
use App\Services\Base\BaseService;

class RecurringTemplateService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new recurring template.
     *
     * @param  array{type: string, contact_id: int, name: string, description?: string, frequency: string, start_date: string, end_date?: string, occurrences_limit?: int, template_data: array, auto_post?: bool, auto_send?: bool}  $data
     */
    public function create(array $data): RecurringTemplate
    {
        return $this->executeInTransaction('create_recurring_template', function () use ($data) {
            $template = RecurringTemplate::create([
                ...$data,
                'next_generate_date' => $data['start_date'],
                'occurrences_count' => 0,
                'is_active' => true,
                'auto_post' => $data['auto_post'] ?? false,
                'auto_send' => $data['auto_send'] ?? false,
                'created_by' => $this->getUserId(),
            ]);

            return $template->load('contact');
        }, ['name' => $data['name']]);
    }

    /**
     * Update a recurring template.
     *
     * @param  array{name?: string, description?: string, frequency?: string, start_date?: string, end_date?: string, occurrences_limit?: int, template_data?: array, auto_post?: bool, auto_send?: bool}  $data
     */
    public function update(RecurringTemplate $template, array $data): RecurringTemplate
    {
        return $this->executeInTransaction('update_recurring_template', function () use ($template, $data) {
            $template->update($data);

            return $template->fresh('contact');
        }, ['template_id' => $template->id]);
    }

    /**
     * Delete a recurring template.
     */
    public function delete(RecurringTemplate $template): bool
    {
        return $this->executeInTransaction('delete_recurring_template', function () use ($template) {
            // Check if template has generated documents
            $hasDocuments = $template->invoices()->exists() || $template->bills()->exists();

            if ($hasDocuments) {
                // Soft delete by deactivating
                $template->update(['is_active' => false]);
                $template->delete();

                return false; // Indicates soft delete
            }

            $template->forceDelete();

            return true; // Indicates hard delete
        }, ['template_id' => $template->id]);
    }
}
