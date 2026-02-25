<?php

namespace App\Models\Thing;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
{
    use HasFactory;

    protected $table = 'thing_item_categories';

    protected $fillable = [
        'name',
        'user_id',
        'parent_id',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取父分类
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    /**
     * 获取子分类
     */
    public function children(): HasMany
    {
        return $this->hasMany(ItemCategory::class, 'parent_id');
    }

    /**
     * 判断是否为主分类（没有父分类）
     */
    public function isParent()
    {
        return is_null($this->parent_id);
    }

    /**
     * 判断是否为子分类（有父分类）
     */
    public function isChild()
    {
        return ! is_null($this->parent_id);
    }
}
