<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// M2 CAT-01..04, blueprint §6.2.
// Name uniqueness is case-insensitive (lower(name) unique index); the
// stored name keeps its original casing for display. Slug generation
// lives in CatalogService, not here, so it stays testable in one place.
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
