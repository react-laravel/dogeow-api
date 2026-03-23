<?php

namespace App\Services\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClientInfoService
{
    private const LOCATION_ERROR_MESSAGE = '地理位置信息获取失败';

    /**
     * 获取客户端基本信息(IP 和 User-Agent)
     */
    public function getBasicInfo(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    /**
     * 获取地理位置信息
     */
    public function getLocationInfo(?string $ip, bool $skipReservedIpLookup = true): array
    {
        if (! is_string($ip) || $ip === '') {
            return $this->emptyLocationResponse();
        }

        if ($skipReservedIpLookup && ! $this->isPublicIp($ip)) {
            return $this->emptyLocationResponse();
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get("http://ip-api.com/json/{$ip}", ['lang' => 'zh-CN']);

            $ipInfo = $response->json();
            if (! $response->successful() || ! is_array($ipInfo) || ($ipInfo['status'] ?? 'success') !== 'success') {
                Log::warning('Failed to fetch location info from provider', [
                    'ip' => $ip,
                    'status_code' => $response->status(),
                    'provider_status' => is_array($ipInfo) ? ($ipInfo['status'] ?? null) : null,
                ]);

                return $this->emptyLocationResponse(self::LOCATION_ERROR_MESSAGE);
            }

            return [
                'location' => [
                    'country' => $ipInfo['country'] ?? null,
                    'region' => $ipInfo['regionName'] ?? null,
                    'city' => $ipInfo['city'] ?? null,
                    'isp' => $ipInfo['isp'] ?? null,
                    'timezone' => $ipInfo['timezone'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to fetch location info', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyLocationResponse(self::LOCATION_ERROR_MESSAGE);
        }
    }

    /**
     * 获取完整客户端信息
     */
    public function getClientInfo(Request $request, bool $skipReservedIpLookup = true): array
    {
        $basicInfo = $this->getBasicInfo($request);
        $locationInfo = $this->getLocationInfo($basicInfo['ip'] ?? null, $skipReservedIpLookup);

        return [
            'ip' => $basicInfo['ip'],
            'user_agent' => $basicInfo['user_agent'],
            'location' => $locationInfo['location'] ?? [],
        ];
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function emptyLocationResponse(?string $error = null): array
    {
        $response = [
            'location' => [
                'country' => null,
                'region' => null,
                'city' => null,
                'isp' => null,
                'timezone' => null,
            ],
        ];

        if ($error !== null) {
            $response['error'] = $error;
        }

        return $response;
    }
}
