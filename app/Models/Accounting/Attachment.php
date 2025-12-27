<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    public const CATEGORY_INVOICE_PDF = 'invoice_pdf';

    public const CATEGORY_RECEIPT = 'receipt';

    public const CATEGORY_CONTRACT = 'contract';

    public const CATEGORY_DELIVERY_NOTE = 'delivery_note';

    public const CATEGORY_BANK_STATEMENT = 'bank_statement';

    public const CATEGORY_TAX_DOCUMENT = 'tax_document';

    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'filename',
        'disk',
        'path',
        'mime_type',
        'size',
        'description',
        'category',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full storage path.
     */
    public function getFullPath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * Get the URL for the file.
     */
    public function getUrl(): ?string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get the file contents.
     */
    public function getContents(): ?string
    {
        return Storage::disk($this->disk)->get($this->path);
    }

    /**
     * Check if file exists.
     */
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Delete the file from storage.
     */
    public function deleteFile(): bool
    {
        if ($this->exists()) {
            return Storage::disk($this->disk)->delete($this->path);
        }

        return true;
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Check if attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get available categories.
     *
     * @return array<string, string>
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_INVOICE_PDF => 'Faktur PDF',
            self::CATEGORY_RECEIPT => 'Kwitansi',
            self::CATEGORY_CONTRACT => 'Kontrak',
            self::CATEGORY_DELIVERY_NOTE => 'Surat Jalan',
            self::CATEGORY_BANK_STATEMENT => 'Rekening Koran',
            self::CATEGORY_TAX_DOCUMENT => 'Dokumen Pajak',
            self::CATEGORY_OTHER => 'Lainnya',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            $attachment->deleteFile();
        });
    }
}
