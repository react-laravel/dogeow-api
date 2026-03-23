<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Packages\PackageWatchRefreshService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class GithubWebhookController extends Controller
{
    public function __construct(
        private readonly PackageWatchRefreshService $refreshService
    ) {}

    public function repoWatch(Request $request): JsonResponse
    {
        $secret = config('services.github.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            return response()->json(['message' => 'Webhook secret not configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $this->isValidSignature($request, $secret)) {
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $event = $request->header('X-GitHub-Event');
        if (! in_array($event, ['push', 'release'], true)) {
            return response()->json(['message' => 'Ignored event'], Response::HTTP_ACCEPTED);
        }

        $owner = Arr::get($request->all(), 'repository.owner.login')
            ?? Arr::get($request->all(), 'repository.owner.name');
        $repo = Arr::get($request->all(), 'repository.name');

        if (! is_string($owner) || ! is_string($repo)) {
            return response()->json(['message' => 'Missing repository information'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $count = $this->refreshService->refreshRepositoryPackages($owner, $repo);

        return response()->json([
            'message' => 'Webhook processed',
            'refreshed_packages' => $count,
        ]);
    }

    private function isValidSignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        if (! is_string($signature) || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
