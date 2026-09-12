<?php

namespace App\Models\Projects;

use App\Models\Contacts\Contact;
use App\Models\User;
use Database\Factories\Projects\CustomerRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string|null $rateable_type
 * @property int|null $rateable_id
 * @property int|null $contact_id
 * @property int $rating
 * @property string|null $comment
 * @property \Carbon\Carbon|null $rated_at
 * @property int|null $created_by
 */
class CustomerRating extends Model
{
    /** @use HasFactory<CustomerRatingFactory> */
    use HasFactory;

    protected static function newFactory(): CustomerRatingFactory
    {
        return CustomerRatingFactory::new();
    }

    protected $fillable = [
        'project_id',
        'rateable_type',
        'rateable_id',
        'contact_id',
        'rating',
        'comment',
        'rated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function rateable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
