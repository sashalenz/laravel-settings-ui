<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Stores;

use SashaLenz\SettingsUi\Contracts\SettingsStore;

/**
 * Single-JSON-file store, for hosts with no database.
 *
 * Writes are atomic — the file is written to a sibling temp path and renamed,
 * because `rename()` is atomic on POSIX filesystems while a partial
 * `file_put_contents` is not. A half-written settings file would be worse than
 * a missing one: it reads as "no values stored", silently reverting every knob
 * to its file default.
 *
 * Not safe across instances. Two processes writing different keys will lose
 * one of them, since each rewrites the whole document — `settings:doctor`
 * warns when this store is in use.
 */
final class FileStore implements SettingsStore
{
    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function __construct(private readonly string $path) {}

    public function load(string $group): array
    {
        $prefix = $group.'.';

        return array_filter(
            $this->all(),
            static fn (string $path): bool => str_starts_with($path, $prefix),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function get(string $path): mixed
    {
        return $this->all()[$path] ?? null;
    }

    public function put(string $path, mixed $value): void
    {
        $values = $this->all();
        $values[$path] = $value;

        $this->write($values);
    }

    public function forget(string $path): void
    {
        $values = $this->all();

        if (! array_key_exists($path, $values)) {
            return;
        }

        unset($values[$path]);

        $this->write($values);
    }

    public function orphans(array $declaredPaths): array
    {
        /** @var list<string> $orphans */
        $orphans = array_values(array_diff(array_keys($this->all()), $declaredPaths));

        sort($orphans);

        return $orphans;
    }

    public function writable(): bool
    {
        return true;
    }

    public function flush(): void
    {
        $this->values = null;
    }

    /** @return array<string, mixed> */
    private function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        if (! is_file($this->path)) {
            return $this->values = [];
        }

        $contents = @file_get_contents($this->path);

        if ($contents === false || $contents === '') {
            return $this->values = [];
        }

        $decoded = json_decode($contents, true);

        // Unreadable JSON resolves to "nothing stored" rather than throwing:
        // the app boots on file defaults and the operator can fix the file.
        return $this->values = is_array($decoded) ? $decoded : [];
    }

    /** @param  array<string, mixed>  $values */
    private function write(array $values): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        ksort($values);

        $encoded = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $temp = $this->path.'.'.bin2hex(random_bytes(6)).'.tmp';

        if (@file_put_contents($temp, $encoded, LOCK_EX) === false) {
            return;
        }

        // Atomic swap: readers see either the old document or the new one.
        if (! @rename($temp, $this->path)) {
            @unlink($temp);

            return;
        }

        $this->values = $values;
    }
}
