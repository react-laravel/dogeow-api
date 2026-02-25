<?php

namespace App\Models\Cloud;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $path
 * @property mixed $extension
 * @property mixed $size
 * @property mixed $is_folder
 * @property mixed $total_size
 * @property mixed $file_count
 * @property mixed $folder_count
 */
class File extends Model
{
    use HasFactory;

    protected $table = 'cloud_files';

    protected $fillable = [
        'name',
        'original_name',
        'path',
        'mime_type',
        'extension',
        'size',
        'parent_id',
        'user_id',
        'is_folder',
        'description',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_folder' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['type'];

    /**
     * 按文件类型过滤的 Scope
     */
    public function scopeWhereHasFileType(Builder $query, string $type): Builder
    {
        if ($type === 'folder') {
            return $query->where('is_folder', true);
        }

        $extensions = self::getExtensionsByType($type);

        if (empty($extensions)) {
            return $query->where('is_folder', false);
        }

        return $query->where('is_folder', false)
            ->whereIn('extension', $extensions);
    }

    /**
     * 获取文件类型对应的扩展名数组
     */
    public static function getExtensionsByType(string $type): array
    {
        return match ($type) {
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'],
            'pdf' => ['pdf'],
            'document' => ['doc', 'docx', 'txt', 'rtf', 'md'],
            'spreadsheet' => ['xls', 'xlsx', 'csv'],
            'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
            'audio' => ['mp3', 'wav', 'ogg', 'flac'],
            'video' => ['mp4', 'avi', 'mov', 'wmv', 'mkv'],
            default => [],
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(File::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(File::class, 'parent_id');
    }

    /**
     * 获取所有后代文件夹（用于移动验证）
     */
    public function getAllDescendants(): array
    {
        $descendants = [];
        $queue = [$this->id];

        while (! empty($queue)) {
            $currentId = array_shift($queue);
            $children = self::where('parent_id', $currentId)
                ->where('is_folder', true)
                ->pluck('id')
                ->toArray();

            $descendants = array_merge($descendants, $children);
            $queue = array_merge($queue, $children);
        }

        return $descendants;
    }

    public function getDownloadUrl(): string
    {
        return route('cloud.files.download', $this->id);
    }

    public function getTypeAttribute(): string
    {
        if ($this->is_folder) {
            return 'folder';
        }

        $extension = strtolower($this->extension ?? '');

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'])) {
            return 'image';
        }

        if (in_array($extension, ['pdf'])) {
            return 'pdf';
        }

        if (in_array($extension, ['doc', 'docx', 'txt', 'rtf', 'md', 'pages', 'key', 'numbers'])) {
            return 'document';
        }

        if (in_array($extension, ['xls', 'xlsx', 'csv'])) {
            return 'spreadsheet';
        }

        if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return 'archive';
        }

        if (in_array($extension, ['mp3', 'wav', 'ogg', 'flac'])) {
            return 'audio';
        }

        if (in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'mkv'])) {
            return 'video';
        }

        return 'other';
    }
}
