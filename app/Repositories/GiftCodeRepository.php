<?php

namespace App\Repositories;

use Illuminate\Support\Arr;

class GiftCodeRepository
{
    private string $file;

    /** @var array<string, array> */
    private array $records = [];

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? config('giftflow.codes_file');
        $this->records = $this->load();
    }

    public function all(): array
    {
        return array_values($this->records);
    }

    public function find(string $code): ?array
    {
        return $this->records[$code] ?? null;
    }

    public function upsert(array $payload): array
    {
        $code = Arr::get($payload, 'code');

        if (!$code) {
            throw new \InvalidArgumentException('Code is required');
        }

        $existing = $this->records[$code] ?? [];
        $this->records[$code] = array_merge($existing, $payload, ['code' => $code]);
        $this->persist();

        return $this->records[$code];
    }

    public function reset(array $records = []): void
    {
        $this->records = [];
        foreach ($records as $record) {
            $this->upsert($record);
        }
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
            json_encode($this->records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
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
