<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private const string API_BASE = 'https://api.github.com';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRepositories(User $user): array
    {
        $token = $user->githubAccount?->access_token;

        if (! $token) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->accept('application/vnd.github.v3+json')
                ->get(self::API_BASE . '/user/repos', [
                    'sort' => 'updated',
                    'per_page' => 100,
                    'affiliation' => 'owner,collaborator,organization_member',
                ]);

            if (! $response->successful()) {
                Log::error('GitHub API error: ' . $response->body());

                return [];
            }

            $repos = $response->json();

            return collect($repos)->map(fn ($repo) => [
                'id' => $repo['id'],
                'full_name' => $repo['full_name'],
                'name' => $repo['name'],
                'owner' => $repo['owner']['login'],
                'private' => $repo['private'],
                'description' => $repo['description'],
                'default_branch' => $repo['default_branch'],
                'updated_at' => $repo['updated_at'],
            ])->toArray();
        } catch (Exception $e) {
            Log::error('Failed to fetch GitHub repositories: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRepository(User $user, string $repoFullName): ?array
    {
        $token = $user->githubAccount?->access_token;

        if (! $token) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->accept('application/vnd.github.v3+json')
                ->get(self::API_BASE . '/repos/' . $repoFullName);

            if (! $response->successful()) {
                Log::error('GitHub API error: ' . $response->body());

                return null;
            }

            $repo = $response->json();

            return [
                'id' => $repo['id'],
                'full_name' => $repo['full_name'],
                'name' => $repo['name'],
                'owner' => $repo['owner']['login'],
                'private' => $repo['private'],
                'description' => $repo['description'],
                'default_branch' => $repo['default_branch'],
                'html_url' => $repo['html_url'],
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch GitHub repository: ' . $e->getMessage());

            return null;
        }
    }
}
