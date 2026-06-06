<?php

namespace App\Services\File;

use App\Events\Upload\RmbgStatusUpdated;
use App\Models\Thing\ItemImage;
use Illuminate\Support\Facades\Cache;

class RmbgStatusService
{
    private const CACHE_PREFIX = 'rmbg_status:';

    private const TTL_SECONDS = 3600;

    public function setPending(string $path): void
    {
        $this->put($path, ['status' => 'pending']);
    }

    public function setProcessing(string $path): void
    {
        $this->put($path, ['status' => 'processing']);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function setDone(string $path, array $result): void
    {
        $this->put($path, array_merge(['status' => 'done'], $result));
    }

    public function setFailed(string $path, string $message): void
    {
        $this->put($path, [
            'status' => 'failed',
            'message' => $message,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $path): ?array
    {
        /** @var array<string, mixed>|null $status */
        $status = Cache::get(self::CACHE_PREFIX . $path);

        return $status;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function put(string $path, array $data): void
    {
        Cache::put(self::CACHE_PREFIX . $path, $data, self::TTL_SECONDS);
        $this->broadcastStatus($path, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function broadcastStatus(string $path, array $data): void
    {
        $userId = self::extractUserIdFromUploadPath($path);
        if ($userId === null) {
            return;
        }

        $linker = app(RmbgItemImageLinkerService::class);
        $itemImageId = $linker->getItemImageIdForUploadPath($path);
        $itemId = null;

        if ($itemImageId !== null) {
            $itemId = ItemImage::query()->whereKey($itemImageId)->value('item_id');
        }

        event(new RmbgStatusUpdated(
            userId: $userId,
            uploadPath: $path,
            payload: $data,
            itemId: is_numeric($itemId) ? (int) $itemId : null,
            itemImageId: $itemImageId,
        ));
    }

    public static function extractUserIdFromUploadPath(string $path): ?int
    {
        if (! preg_match('#^uploads/(\d+)/#', $path, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
