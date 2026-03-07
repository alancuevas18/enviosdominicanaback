<?php

declare(strict_types=1);

namespace App\Channels;

/**
 * Simple DTO for a Web Push payload.
 * Mirrors the most-used fields from the discontinued
 * laravel-notification-channels/webpush package.
 */
class WebPushMessage
{
    private string $title = '';

    private string $body = '';

    private string $icon = '/icon-192x192.png';

    /** @var array<array{action:string,title:string}> */
    private array $actions = [];

    /** @var array<string, mixed> */
    private array $data = [];

    private ?string $badge = null;

    private ?string $image = null;

    private ?string $tag = null;

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function badge(string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function image(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function tag(string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function action(string $title, string $action): static
    {
        $this->actions[] = ['action' => $action, 'title' => $title];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Serialise to the JSON payload that the service worker `push` event receives.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'image' => $this->image,
            'tag' => $this->tag,
            'actions' => $this->actions ?: null,
            'data' => $this->data ?: null,
        ], fn($v) => $v !== null);
    }
}
