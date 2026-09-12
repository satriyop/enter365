<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Projects\CustomerRating;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerRating
 */
class CustomerRatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->id,
                'project_number' => $this->project->project_number,
                'name' => $this->project->name,
            ]),
            'rateable_type' => $this->rateable_type,
            'rateable_id' => $this->rateable_id,
            'rateable' => $this->whenLoaded('rateable', function () {
                $rateable = $this->rateable;
                if ($rateable instanceof Task) {
                    return [
                        'id' => $rateable->id,
                        'type' => 'task',
                        'title' => $rateable->title,
                    ];
                }
                if ($rateable instanceof Project) {
                    return [
                        'id' => $rateable->id,
                        'type' => 'project',
                        'name' => $rateable->name,
                    ];
                }

                return null;
            }),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact === null ? null : [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
            ]),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'rated_at' => $this->rated_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
