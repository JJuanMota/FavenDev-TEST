<?php

namespace App\Repositories;

class WebhookEventRepository
{
    private string $file;

    /** @var array<string, bool> */
    private array $events = [];

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? config('giftflow.events_file');
        $this->events = $this->load();
    }

    public function hasEvent(string $eventId): bool
    {
        return isset($this->events[$eventId]);
    }

    public function recordEvent(string $eventId): void
    {
        $this->events[$eventId] = true;
        $this->persist();
    }

    public function reset(): void
    {
        $this->events = [];
        $this->persist();
    }

    private function load(): array
    {
        if (!file_exists($this->file)) {
            return [];
        }

        $contents = file_get_contents($this->file);
        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function persist(): void
    {
        $this->ensurePath();
        file_put_contents(
            $this->file,
            json_encode($this->events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private function ensurePath(): void
    {
        $dir = dirname($this->file);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
