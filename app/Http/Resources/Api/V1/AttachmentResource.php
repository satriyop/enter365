<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Attachment
 */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_human' => $this->getHumanSize(),
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'is_image' => $this->isImage(),
            'is_pdf' => $this->isPdf(),
            'download_url' => route('api.v1.attachments.download', $this->id),
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->when($this->relationLoaded('uploader'), fn () => [
                'id' => $this->uploader?->id,
                'name' => $this->uploader?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function getCategoryLabel(): string
    {
        $categories = \App\Models\Accounting\Attachment::getCategories();

        return $categories[$this->category] ?? $this->category;
    }
}
