<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Console;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use SashaLenz\SettingsUi\Import\DeclarationPrinter;
use SashaLenz\SettingsUi\Import\ImportNode;
use SashaLenz\SettingsUi\Import\LegacyImporter;

/**
 * Generates declaration classes from a legacy flat settings table.
 *
 * A migration aid, not a runtime feature: run it once against the old table,
 * review the output, commit it, drop the table. Defaults match the column
 * names used by the app this package was extracted from; override them for a
 * differently-shaped table.
 */
final class ImportSettingsCommand extends Command
{
    protected $signature = 'settings:import
        {--table=setting_fields : Legacy table to read}
        {--connection= : Database connection to read from}
        {--output=app/Settings : Directory for the generated classes}
        {--namespace=App\\Settings : Namespace for the generated classes}
        {--lang= : Write extracted labels to this lang file (e.g. lang/uk/settings.php)}
        {--lang-namespace=settings : Prefix for generated translation keys}
        {--literal-labels : Emit labels inline instead of as translation keys}
        {--group=* : Only import these root groups}
        {--dry-run : Print what would be written without touching the filesystem}';

    protected $description = 'Generate settings declaration classes from a legacy flat settings table';

    public function handle(DatabaseManager $database): int
    {
        $importer = new LegacyImporter(
            connection: $database->connection($this->stringOption('connection')),
            table: (string) $this->option('table'),
        );

        /** @var list<string> $only */
        $only = (array) $this->option('group');

        $roots = $importer->import($only);

        if ($roots === []) {
            $this->components->warn('No rows found — nothing to import.');

            return self::SUCCESS;
        }

        $printer = new DeclarationPrinter(
            namespace: $this->normalizeNamespace((string) $this->option('namespace')),
            langNamespace: (string) $this->option('lang-namespace'),
            literalLabels: (bool) $this->option('literal-labels'),
        );

        $this->report($roots);

        $dryRun = (bool) $this->option('dry-run');
        $directory = (string) $this->option('output');

        foreach ($roots as $root) {
            $this->writeClass($printer, $root, $directory, $dryRun);
        }

        $this->writeTranslations($printer, $dryRun);
        $this->reportWarnings($importer->warnings());

        if ($dryRun) {
            $this->components->info('Dry run — nothing was written.');
        }

        return $this->hasErrors($importer->warnings()) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Collapse repeated backslashes and strip the edges.
     *
     * A namespace typed at a shell prompt routinely arrives over-escaped —
     * quoting rules differ between the shell, docker exec and artisan — so
     * emitting it verbatim is a good way to produce a file that will not parse,
     * with the error surfacing far from its cause. Normalising is cheaper than
     * explaining.
     */
    private function normalizeNamespace(string $namespace): string
    {
        return trim((string) preg_replace('#\\\\+#', '\\', $namespace), '\\');
    }

    /** @param  array<string, ImportNode>  $roots */
    private function report(array $roots): void
    {
        $rows = [];
        $total = 0;

        foreach ($roots as $root) {
            $count = $root->fieldCount();
            $total += $count;

            $rows[] = [$root->name, (string) $count, (string) $this->depth($root), (string) count($root->children)];
        }

        $this->table(['group', 'fields', 'depth', 'nested groups'], $rows);
        $this->components->info(sprintf('%d fields across %d root groups.', $total, count($roots)));
    }

    private function depth(ImportNode $node): int
    {
        $depth = 0;

        foreach ($node->children as $child) {
            $depth = max($depth, 1 + $this->depth($child));
        }

        return $depth;
    }

    private function writeClass(DeclarationPrinter $printer, ImportNode $root, string $directory, bool $dryRun): void
    {
        $file = rtrim($directory, '/').'/'.$printer->className($root->name).'.php';
        $source = $printer->print($root);

        if ($dryRun) {
            $this->components->twoColumnDetail($file, Str::plural('line', substr_count($source, PHP_EOL)).': '.substr_count($source, PHP_EOL));

            return;
        }

        $this->ensureDirectory(dirname($file));
        file_put_contents($file, $source);

        $this->components->twoColumnDetail($file, '<fg=green>written</>');
    }

    private function writeTranslations(DeclarationPrinter $printer, bool $dryRun): void
    {
        $translations = $printer->translations();

        if ($translations === []) {
            return;
        }

        $target = $this->stringOption('lang');

        if ($target === null) {
            $this->components->warn(sprintf(
                '%d labels extracted but --lang was not given, so they were not written. '
                .'The generated classes reference keys that do not resolve yet.',
                count($translations),
            ));

            return;
        }

        $nested = [];

        foreach ($translations as $key => $literal) {
            // Strip the lang-file's own name from the front: a key like
            // `settings.selling.foo.label` lives at `selling.foo.label` inside
            // lang/xx/settings.php.
            $path = Str::after($key, (string) $this->option('lang-namespace').'.');
            data_set($nested, $path, $literal);
        }

        $source = "<?php\n\ndeclare(strict_types=1);\n\nreturn ".$this->exportArray($nested, 0).";\n";

        if ($dryRun) {
            $this->components->twoColumnDetail($target, count($translations).' keys');

            return;
        }

        $this->ensureDirectory(dirname($target));
        file_put_contents($target, $source);

        $this->components->twoColumnDetail($target, sprintf('<fg=green>%d keys written</>', count($translations)));
    }

    /** @param  array<array-key, mixed>  $value */
    private function exportArray(array $value, int $depth): string
    {
        $pad = str_repeat('    ', $depth + 1);
        $close = str_repeat('    ', $depth);
        $lines = ['['];

        foreach ($value as $key => $item) {
            $lines[] = $pad."'".str_replace("'", "\\'", (string) $key)."' => "
                .(is_array($item) ? $this->exportArray($item, $depth + 1) : "'".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $item)."'").',';
        }

        $lines[] = $close.']';

        return implode(PHP_EOL, $lines);
    }

    /** @param  list<array{level: string, path: string, message: string}>  $warnings */
    private function reportWarnings(array $warnings): void
    {
        if ($warnings === []) {
            $this->components->info('No anomalies found in the legacy table.');

            return;
        }

        $this->newLine();

        foreach ($warnings as $warning) {
            $line = sprintf('%s — %s', $warning['path'], $warning['message']);

            $warning['level'] === 'error'
                ? $this->components->error($line)
                : $this->components->warn($line);
        }
    }

    /** @param  list<array{level: string, path: string, message: string}>  $warnings */
    private function hasErrors(array $warnings): bool
    {
        foreach ($warnings as $warning) {
            if ($warning['level'] === 'error') {
                return true;
            }
        }

        return false;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
