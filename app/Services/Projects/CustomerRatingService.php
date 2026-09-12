<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Projects\CustomerRatingServiceInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Projects\CustomerRating;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Services\Base\BaseService;
use Illuminate\Support\Arr;

class CustomerRatingService extends BaseService implements CustomerRatingServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CustomerRating
    {
        return $this->executeInTransaction('create_customer_rating', function () use ($data) {
            $payload = $this->payload($data);

            return CustomerRating::query()->create([
                'project_id' => (int) $payload['project_id'],
                'rateable_type' => $payload['rateable_type'] ?? null,
                'rateable_id' => array_key_exists('rateable_id', $payload) && $payload['rateable_id'] !== null
                    ? (int) $payload['rateable_id']
                    : null,
                'contact_id' => array_key_exists('contact_id', $payload) && $payload['contact_id'] !== null
                    ? (int) $payload['contact_id']
                    : null,
                'rating' => (int) $payload['rating'],
                'comment' => $payload['comment'] ?? null,
                'rated_at' => $payload['rated_at'] ?? now(),
                'created_by' => $this->getUserId(),
            ]);
        }, ['project_id' => $data['project_id'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomerRating $rating, array $data): CustomerRating
    {
        return $this->executeInTransaction('update_customer_rating', function () use ($rating, $data) {
            $rating->update($this->payload($data, $rating));

            return $rating->fresh(['project', 'contact', 'rateable']) ?? $rating;
        }, ['customer_rating_id' => $rating->id]);
    }

    public function delete(CustomerRating $rating): void
    {
        $this->executeInTransaction('delete_customer_rating', function () use ($rating) {
            $rating->delete();
        }, ['customer_rating_id' => $rating->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?CustomerRating $existing = null): array
    {
        $payload = Arr::only($data, [
            'project_id',
            'rateable_type',
            'rateable_id',
            'contact_id',
            'rating',
            'comment',
            'rated_at',
        ]);

        $rating = array_key_exists('rating', $payload)
            ? (int) $payload['rating']
            : (int) ($existing === null ? 0 : $existing->rating);
        if ($rating < 1 || $rating > 5) {
            throw new BusinessRuleException('Nilai rating harus antara 1 dan 5.');
        }
        $payload['rating'] = $rating;

        $projectId = array_key_exists('project_id', $payload)
            ? (int) $payload['project_id']
            : (int) ($existing === null ? 0 : $existing->project_id);
        if ($projectId < 1) {
            throw new BusinessRuleException('Proyek wajib dipilih.');
        }

        if (array_key_exists('contact_id', $payload) && ($payload['contact_id'] === '' || $payload['contact_id'] === 0)) {
            $payload['contact_id'] = null;
        }

        $type = array_key_exists('rateable_type', $payload)
            ? $payload['rateable_type']
            : ($existing === null ? null : $existing->rateable_type);
        $rateableId = array_key_exists('rateable_id', $payload)
            ? (int) $payload['rateable_id']
            : (int) ($existing === null ? 0 : ($existing->rateable_id ?? 0));
        if (is_string($type) && $type !== '' && $rateableId > 0) {
            $payload['rateable_type'] = $this->normalizeRateableType($type);
            $payload['rateable_id'] = $rateableId;
            $this->assertRateable($payload['rateable_type'], $rateableId, $projectId);
        }

        return $payload;
    }

    private function normalizeRateableType(string $type): string
    {
        return match ($type) {
            'task', Task::class, 'App\\Models\\Projects\\Task' => 'task',
            'project', Project::class, 'App\\Models\\Projects\\Project' => 'project',
            default => throw new BusinessRuleException('Tipe rating tidak valid.'),
        };
    }

    private function assertRateable(string $type, int $id, int $projectId): void
    {
        if ($type === 'project') {
            if ($id !== $projectId || ! Project::query()->whereKey($id)->exists()) {
                throw new BusinessRuleException('Proyek rating tidak ditemukan.');
            }

            return;
        }

        $task = Task::query()->find($id);
        if ($task === null || (int) $task->project_id !== $projectId) {
            throw new BusinessRuleException('Tugas rating tidak ditemukan pada proyek ini.');
        }
    }
}
