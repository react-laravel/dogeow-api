<?php

namespace App\Models\Nav;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'nav_items';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'nav_category_id',
        'name',
        'url',
        'icon',
        'description',
        'sort_order',
        'is_visible',
        'is_new_window',
        'clicks',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'is_visible' => 'boolean',
        'is_new_window' => 'boolean',
        'sort_order' => 'integer',
        'clicks' => 'integer',
    ];

    /**
     * 获取该导航项所属的分类
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'nav_category_id');
    }

    /**
     * 增加点击次数
     */
    public function incrementClicks()
    {
        return $this->increment('clicks');
    }
}
