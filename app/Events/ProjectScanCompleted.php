<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectScanCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Project $project,
        public readonly string $scanId,
        public readonly array $stats = [],
    ) {}

    /**
     * Get the scan statistics.
     *
     * @return array{files: int, chunks: int, duration_ms: int}
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}
