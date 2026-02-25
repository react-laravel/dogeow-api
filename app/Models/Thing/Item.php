<?php

namespace App\Models\Thing;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $thumbnail_url
 * @property mixed $user_id
 * @property mixed $category_id
 */
class Item extends Model
{
    use HasFactory, Searchable;

    protected $table = 'thing_items';

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'quantity',
        'status',
        'expiry_date',
        'purchase_date',
        'purchase_price',
        'category_id',
        'area_id',
        'room_id',
        'spot_id',
        'is_public',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
    ];

    protected $appends = [
        'thumbnail_url',
    ];

    /**
     * 获取缩略图URL
     */
    public function getThumbnailUrlAttribute()
    {
        // 优先使用主图片
        if ($this->primaryImage && $this->primaryImage->thumbnail_url) {
            return $this->primaryImage->thumbnail_url;
        }

        // 如果没有主图片，使用第一张图片
        $firstImage = $this->images()->first();
        if ($firstImage && $firstImage->thumbnail_url) {
            return $firstImage->thumbnail_url;
        }

        return null;
    }

    /**
     * 获取模型的可搜索数据
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'is_public' => $this->is_public,
            'user_id' => $this->user_id,
        ];
    }

    /**
     * 自定义搜索查询
     *
     * @param  string  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($builder, $query)
    {
        return $builder->where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ItemImage::class)->where('is_primary', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function spot(): BelongsTo
    {
        return $this->belongsTo(Spot::class);
    }

    /**
     * 获取物品的标签
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'thing_item_tag', 'item_id', 'thing_tag_id')
            ->withTimestamps();
    }

    /**
     * 获取此物品关联的所有物品
     */
    public function relatedItems()
    {
        return $this->belongsToMany(
            Item::class,
            'thing_item_relations',
            'item_id',
            'related_item_id'
        )
            ->withPivot('relation_type', 'description')
            ->withTimestamps();
    }

    /**
     * 获取关联到此物品的所有物品（反向关系）
     */
    public function relatingItems()
    {
        return $this->belongsToMany(
            Item::class,
            'thing_item_relations',
            'related_item_id',
            'item_id'
        )
            ->withPivot('relation_type', 'description')
            ->withTimestamps();
    }

    /**
     * 获取所有关联（包括正向和反向）
     */
    public function allRelations()
    {
        // 合并正向和反向关联
        $related = $this->relatedItems()->get();
        $relating = $this->relatingItems()->get();

        return $related->merge($relating)->unique('id');
    }

    /**
     * 按关联类型获取关联物品
     *
     * @param  string  $type  关联类型：accessory, replacement, related, bundle, parent, child
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRelationsByType(string $type)
    {
        return $this->relatedItems()
            ->wherePivot('relation_type', $type)
            ->get();
    }

    /**
     * 添加物品关联
     *
     * @param  int  $relatedItemId  关联物品ID
     * @param  string  $type  关联类型
     * @param  string|null  $description  关联描述
     * @return void
     */
    public function addRelation(int $relatedItemId, string $type = 'related', ?string $description = null)
    {
        $this->relatedItems()->attach($relatedItemId, [
            'relation_type' => $type,
            'description' => $description,
        ]);
    }

    /**
     * 移除物品关联
     *
     * @param  int  $relatedItemId  关联物品ID
     * @return void
     */
    public function removeRelation(int $relatedItemId)
    {
        $this->relatedItems()->detach($relatedItemId);
    }
}
