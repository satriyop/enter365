<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\Shared\Attachment;
use App\Services\Base\Traits\WithOperationContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    use WithOperationContext;

    /**
     * Create a new attachment.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(UploadedFile $file, array $data): Attachment
    {
        $disk = config('filesystems.default', 'local');

        // Generate a unique path
        $folder = 'attachments/'.now()->format('Y/m');
        $filename = uniqid().'_'.$file->getClientOriginalName();
        $path = $file->storeAs($folder, $filename, $disk);

        $attachment = Attachment::create([
            'attachable_type' => $data['attachable_type'],
            'attachable_id' => $data['attachable_id'],
            'filename' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? Attachment::CATEGORY_OTHER,
            'uploaded_by' => $this->getUserId(),
        ]);

        return $attachment->load('uploader');
    }

    /**
     * Delete an attachment and its file.
     */
    public function delete(Attachment $attachment): void
    {
        // Delete the physical file if it exists
        if ($attachment->exists()) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();
    }
}
