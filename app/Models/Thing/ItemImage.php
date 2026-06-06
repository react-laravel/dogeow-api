<?php

namespace App\Models\Thing;

use App\Services\File\RmbgItemImageLinkerService;
use App\Services\File\RmbgStatusService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ItemImage extends Model
{
    use HasFactory;

    protected $table = 'thing_item_images';

    protected $fillable = [
        'item_id',
        'path',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'item_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'url',
        'thumbnail_url',
        'thumbnail_path',
        'rmbg_status',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * 获取图片完整 URL
     */
    public function getUrlAttribute()
    {
        if (! $this->path) {
            return null;
        }

        return config('app.url') . '/storage/' . $this->path;
    }

    /**
     * 获取缩略图完整 URL
     */
    public function getThumbnailUrlAttribute()
    {
        if (! $this->path) {
            return null;
        }
        $dirname = pathinfo($this->path, PATHINFO_DIRNAME);
        $filename = pathinfo($this->path, PATHINFO_FILENAME);
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        $thumbPath = $dirname . '/' . $filename . '-thumb.' . $extension;

        return config('app.url') . '/storage/' . $thumbPath;
    }

    public function getThumbnailPathAttribute()
    {
        if (! $this->path) {
            return null;
        }
        $dirname = pathinfo($this->path, PATHINFO_DIRNAME);
        $filename = pathinfo($this->path, PATHINFO_FILENAME);
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        $thumbPath = $dirname . '/' . $filename . '-thumb.' . $extension;

        return Storage::url($thumbPath);
    }

    public function getRmbgStatusAttribute(): ?string
    {
        $uploadPath = app(RmbgItemImageLinkerService::class)->getUploadPathForItemImage($this->id);
        if ($uploadPath === null) {
            return null;
        }

        $status = app(RmbgStatusService::class)->get($uploadPath);
        if (! is_array($status)) {
            return null;
        }

        $rmbgStatus = $status['status'] ?? null;

        return is_string($rmbgStatus) ? $rmbgStatus : null;
    }
}
