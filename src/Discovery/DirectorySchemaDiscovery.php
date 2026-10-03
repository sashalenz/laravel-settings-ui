<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Discovery;

use ReflectionClass;
use SashaLenz\SettingsUi\Contracts\DeclaresSettings;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Scans directories for classes implementing DeclaresSettings.
 * Ideal for modular monoliths, Domain-Driven Design or pluggable packages/modules.
 */
final class DirectorySchemaDiscovery
{
    /**
     * Scan one or more paths/directories or glob patterns and return discovered classes.
     *
     * @param  list<string>  $paths  Directory paths or glob patterns
     * @return list<class-string<DeclaresSettings>>
     */
    public function discover(array $paths): array
    {
        $discovered = [];

        foreach ($paths as $path) {
            $directories = glob($path, GLOB_ONLYDIR) ?: [];

            if (empty($directories) && is_dir($path)) {
                $directories = [$path];
            }

            foreach ($directories as $dir) {
                if (! is_dir($dir)) {
                    continue;
                }

                $classes = $this->scanDirectory($dir);
                foreach ($classes as $class) {
                    $discovered[$class] = true;
                }
            }
        }

        /** @var list<class-string<DeclaresSettings>> */
        return array_keys($discovered);
    }

    /**
     * @return list<class-string<DeclaresSettings>>
     */
    private function scanDirectory(string $directory): array
    {
        $found = [];
        $finder = (new Finder)->files()->name('*Settings.php')->in($directory);

        foreach ($finder as $file) {
            $class = $this->extractClassFromFile($file->getRealPath());

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            try {
                $ref = new ReflectionClass($class);
                if ($ref->isInstantiable() && $ref->implementsInterface(DeclaresSettings::class)) {
                    /** @var class-string<DeclaresSettings> $class */
                    $found[] = $class;
                }
            } catch (Throwable) {
                // Ignore classes that fail reflection
            }
        }

        return $found;
    }

    private function extractClassFromFile(string $path): ?string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $contents, $matches)) {
            $className = trim($matches[1]);

            return $namespace !== '' ? "{$namespace}\\{$className}" : $className;
        }

        return null;
    }
}
