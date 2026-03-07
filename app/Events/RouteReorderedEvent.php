<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Route;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an admin reorders a courier's route stops.
 *
 * Broadcast to:
 *   - private-courier.{courierId}  → courier's app reflects the new stop order immediately
 *   - private-branch.{branchId}   → admin sees confirmation the event went through
 */
class RouteReorderedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Route $route
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("courier.{$this->route->courier_id}"),
            new PrivateChannel("branch.{$this->route->branch_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'route.reordered';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteStop> $stops */
        $stops = $this->route->stops()
            ->orderBy('route_stops.suggested_order')
            ->select(['stops.id', 'stops.type', 'stops.status', 'route_stops.suggested_order'])
            ->get();

        return [
            'route_id'   => $this->route->id,
            'courier_id' => $this->route->courier_id,
            'branch_id'  => $this->route->branch_id,
            'date'       => $this->route->date instanceof \Carbon\Carbon
                ? $this->route->date->toDateString()
                : $this->route->date,
            'stops'      => $stops->map(fn($s) => [
                'id'              => $s->id,
                'type'            => $s->type,
                'status'          => $s->status,
                'suggested_order' => $s->suggested_order,
            ])->values()->all(),
            'reordered_at' => now()->toIso8601String(),
        ];
    }
}
