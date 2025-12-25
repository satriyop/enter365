<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the parent category.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    /**
     * Get the child categories.
     *
     * @return HasMany<ProductCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants (recursive).
     *
     * @return HasMany<ProductCategory, $this>
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the products in this category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Get the full path of the category (e.g., "Electronics > Phones > Smartphones").
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Check if this category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if this category can be deleted.
     */
    public function canDelete(): bool
    {
        return ! $this->hasChildren() && ! $this->products()->exists();
    }

    /**
     * Generate the next category code.
     */
    public static function generateCode(?int $parentId = null): string
    {
        if ($parentId) {
            $parent = static::find($parentId);
            $prefix = $parent ? $parent->code . '-' : 'CAT-';
            $lastChild = static::where('parent_id', $parentId)
                ->orderByDesc('code')
                ->first();

            if ($lastChild) {
                $lastNum = (int) substr($lastChild->code, strrpos($lastChild->code, '-') + 1);

                return $prefix . str_pad($lastNum + 1, 2, '0', STR_PAD_LEFT);
            }

            return $prefix . '01';
        }

        $last = static::whereNull('parent_id')
            ->where('code', 'like', 'CAT-%')
            ->orderByDesc('code')
            ->first();

        if ($last) {
            $lastNum = (int) substr($last->code, 4);

            return 'CAT-' . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
        }

        return 'CAT-001';
    }

    /**
     * Scope for active categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProductCategory>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ProductCategory>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for root categories (no parent).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ProductCategory>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ProductCategory>
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
