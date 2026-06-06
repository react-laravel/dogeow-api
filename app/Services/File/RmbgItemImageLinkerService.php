<?php

namespace App\Services\File;

use Illuminate\Support\Facades\Cache;

class RmbgItemImageLinkerService
{
    private const LINK_PREFIX = 'rmbg_item_link:';

    private const SOURCE_PREFIX = 'rmbg_item_source:';

    private const TTL_SECONDS = 3600;

    public function link(string $uploadPath, int $itemImageId): void
    {
        Cache::put(self::LINK_PREFIX . $uploadPath, $itemImageId, self::TTL_SECONDS);
        Cache::put(self::SOURCE_PREFIX . $itemImageId, $uploadPath, self::TTL_SECONDS);
    }

    public function getItemImageIdForUploadPath(string $uploadPath): ?int
    {
        $itemImageId = Cache::get(self::LINK_PREFIX . $uploadPath);

        return is_int($itemImageId) ? $itemImageId : null;
    }

    public function getUploadPathForItemImage(int $itemImageId): ?string
    {
        $uploadPath = Cache::get(self::SOURCE_PREFIX . $itemImageId);

        return is_string($uploadPath) && $uploadPath !== '' ? $uploadPath : null;
    }

    public function unlink(string $uploadPath): void
    {
        $itemImageId = $this->getItemImageIdForUploadPath($uploadPath);
        Cache::forget(self::LINK_PREFIX . $uploadPath);

        if ($itemImageId !== null) {
            Cache::forget(self::SOURCE_PREFIX . $itemImageId);
        }
    }
}
