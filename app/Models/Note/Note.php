<?php

namespace App\Models\Note;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $id
 * @property mixed $user_id
 * @property mixed $title
 * @property mixed $content
 * @property mixed $is_public
 */
class Note extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'notes';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'note_category_id',
        'title',
        'slug',
        'summary',
        'content',
        'content_markdown',
        'is_draft',
        'is_wiki',
    ];

    /**
     * 应该被调整为日期的属性
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 获取笔记所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取笔记所属分类
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NoteCategory::class, 'note_category_id');
    }

    /**
     * 获取笔记的标签
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NoteTag::class, 'note_note_tag', 'note_id', 'note_tag_id')
            ->withTimestamps();
    }

    /**
     * 获取从该节点出发的链接
     */
    public function linksFrom(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'source_id');
    }

    /**
     * 获取指向该节点的链接
     */
    public function linksTo(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'target_id');
    }

    /**
     * 获取所有相关的链接（作为源或目标）
     */
    public function links()
    {
        return NoteLink::where('source_id', $this->id)
            ->orWhere('target_id', $this->id)
            ->get();
    }

    /**
     * 从标题生成 slug
     */
    public static function normalizeSlug(string $title): string
    {
        // 转换为小写
        $slug = mb_strtolower($title, 'UTF-8');

        // 替换空格为连字符
        $slug = preg_replace('/\s+/', '-', $slug);

        // 移除特殊字符，保留中文、字母、数字、连字符
        $slug = preg_replace('/[^\w\s\x{4e00}-\x{9fa5}-]/u', '', $slug);

        // 合并多个连字符
        $slug = preg_replace('/-+/', '-', $slug);

        // 去除首尾连字符
        $slug = trim($slug, '-');

        // 如果为空，使用原始标题
        return $slug ?: $title;
    }

    /**
     * 确保 slug 唯一
     */
    public static function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
