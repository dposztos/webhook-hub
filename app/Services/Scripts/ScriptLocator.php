<?php

namespace App\Services\Scripts;

use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Everything that decides *which* file a script action is allowed to run.
 *
 * The rule stores a path relative to the configured script directory; this class
 * is the only place that turns one into an absolute path, and it refuses
 * anything that resolves outside that directory.
 */
class ScriptLocator
{
    public function enabled(): bool
    {
        return (bool) config('webhookhub.scripts.enabled');
    }

    public function inlineAllowed(): bool
    {
        return (bool) config('webhookhub.scripts.allow_inline');
    }

    /**
     * The script directory, resolved through symlinks. Null when it does not exist.
     */
    public function directory(): ?string
    {
        $dir = (string) config('webhookhub.scripts.dir');

        if ($dir === '') {
            return null;
        }

        $real = realpath($dir);

        return $real === false ? null : $real;
    }

    /**
     * The .py files available to pick from, as paths relative to the script
     * directory, sorted. Dot-directories and dot-files are skipped.
     *
     * @return array<int, string>
     */
    public function available(): array
    {
        $dir = $this->directory();

        if (! $dir) {
            return [];
        }

        $files = Finder::create()
            ->files()
            ->in($dir)
            ->name('*.py')
            ->notName('.*')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->depth('< 5')
            ->sortByName();

        return array_values(array_map(
            fn (SplFileInfo $file) => str_replace('\\', '/', $file->getRelativePathname()),
            iterator_to_array($files, false)
        ));
    }

    /**
     * Turns a stored relative path into an absolute one.
     *
     * @throws RuntimeException when the path escapes the directory or is missing
     */
    public function resolve(string $relative): string
    {
        $dir = $this->directory();

        if (! $dir) {
            throw new RuntimeException(__('webhookhub.script.no_directory', [
                'dir' => (string) config('webhookhub.scripts.dir'),
            ]));
        }

        $relative = trim(str_replace('\\', '/', $relative));

        if ($relative === '' || str_starts_with($relative, '/') || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            throw new RuntimeException(__('webhookhub.script.bad_path', ['path' => $relative]));
        }

        $path = realpath($dir.DIRECTORY_SEPARATOR.$relative);

        // realpath() collapses symlinks too, so a link pointing out of the
        // directory is caught here rather than silently followed.
        if ($path === false || ! is_file($path) || ! $this->within($dir, $path)) {
            throw new RuntimeException(__('webhookhub.script.not_found', ['path' => $relative]));
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'py') {
            throw new RuntimeException(__('webhookhub.script.not_python', ['path' => $relative]));
        }

        return $path;
    }

    private function within(string $dir, string $path): bool
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $dir);
    }
}
