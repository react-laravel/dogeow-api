<?php

namespace App\Models\Nav;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'nav_categories';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'icon',
        'description',
        'sort_order',
        'is_visible',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * 获取该分类下的所有导航项
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'nav_category_id');
    }
}
