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
     * The interpreter a script actually runs with: the virtualenv built from
     * requirements.txt when there is one, otherwise the configured Python.
     *
     * Resolved per run rather than at boot, because the virtualenv appears
     * while the container starts and a cached config would freeze the answer
     * from before it existed.
     */
    public function interpreter(): string
    {
        $venv = $this->venvPython();

        return $venv ?? (string) config('webhookhub.scripts.python');
    }

    /** The virtualenv's python, when it is there and runnable. */
    public function venvPython(): ?string
    {
        $venv = (string) config('webhookhub.scripts.venv');

        if ($venv === '') {
            return null;
        }

        $python = rtrim($venv, '/').'/bin/python3';

        return is_executable($python) ? $python : null;
    }

    /**
     * What went wrong the last time requirements.txt was installed, if it did.
     * The entrypoint writes this; the editor shows it, so a failed install is
     * not something you find out from a script that cannot import.
     */
    public function requirementsError(): ?string
    {
        $venv = (string) config('webhookhub.scripts.venv');
        $marker = rtrim($venv, '/').'/.requirements-error';

        if ($venv === '' || ! is_file($marker)) {
            return null;
        }

        $error = trim((string) file_get_contents($marker));

        return $error === '' ? null : mb_strimwidth($error, 0, 2000, '…');
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
