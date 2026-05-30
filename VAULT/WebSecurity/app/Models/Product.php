<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    
    protected $fillable = [
        'category_id',
        'code',
        'name',
        'model',
        'photo',
        'price',
        'stock',
        'description'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);

    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}
