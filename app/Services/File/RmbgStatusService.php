<?php

namespace App\Services\File;

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
    }
}
