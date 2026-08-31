<?php

namespace App\Services\Actions;

use App\Models\RuleAction;
use App\Services\Scripts\ScriptLocator;
use App\Services\Templating\TemplateException;
use App\Services\Templating\TemplateRenderer;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs a Python script with the captured message on stdin.
 *
 * Two shapes, both off by default (see config/webhookhub.php):
 *   - "file":   a .py file from the configured script directory
 *   - "inline": code stored on the rule, written to a temporary file per run
 *
 * The interpreter comes from configuration, never from the rule, and the process
 * is started without a shell, so nothing in a template can turn into a command.
 */
class ScriptAction implements ActionContract
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly ScriptLocator $locator,
    ) {}

    public function execute(RuleAction $action, array $context, bool $dryRun = false): ActionResult
    {
        if (! $this->locator->enabled()) {
            return ActionResult::failed(__('webhookhub.script.disabled'));
        }

        $config = $action->config ?? [];
        $inline = ($config['source'] ?? 'file') === 'inline';

        if ($inline && ! $this->locator->inlineAllowed()) {
            return ActionResult::failed(__('webhookhub.script.inline_disabled'));
        }

        try {
            $arguments = $this->arguments((string) ($config['args'] ?? ''), $context);
            $environment = $this->environment($config['env'] ?? [], $context);
            $stdin = $this->stdin($config, $context);
        } catch (TemplateException $e) {
            return ActionResult::failed($e->getMessage());
        }

        $timeout = $this->timeout($config);
        $python = $this->locator->interpreter();
        $workingDir = $this->locator->directory();

        // Inline code is deliberately NOT rendered as a template: the script gets
        // its data on stdin, so there is no reason to splice payload text into it.
        $code = (string) ($config['code'] ?? '');

        if ($inline && trim($code) === '') {
            return ActionResult::failed(__('webhookhub.script.no_code'));
        }

        $detail = [
            'source' => $inline ? 'inline' : 'file',
            'script' => $inline ? null : (string) ($config['script'] ?? ''),
            'args' => $arguments,
            'timeout' => $timeout,
            'env' => array_keys($environment),
        ];

        if ($dryRun) {
            return ActionResult::skipped(__('webhookhub.script.dry_run'), $detail);
        }

        $scriptPath = null;
        $temporary = null;

        try {
            if ($inline) {
                $scriptPath = $temporary = $this->writeTemporary($code);
            } else {
                $scriptPath = $this->locator->resolve((string) ($config['script'] ?? ''));
            }

            // sys.path[0] is the script's own directory, which for inline code is
            // the temp directory — PYTHONPATH keeps shared helpers importable.
            if ($workingDir) {
                $environment['PYTHONPATH'] = $workingDir;
            }

            $process = new Process(
                array_merge([$python, $scriptPath], $arguments),
                $workingDir,
                $environment,
                $stdin,
                (float) $timeout,
            );

            $process->run();

            $stdout = $this->clip($process->getOutput());
            $stderr = $this->clip($process->getErrorOutput());
            $exitCode = $process->getExitCode();

            $detail = array_merge($detail, [
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]);

            // A script that prints JSON gets it parsed out, so later rules and the
            // run log show structured output instead of a blob of text.
            $parsed = json_decode(trim($stdout), true);

            if (is_array($parsed)) {
                $detail['output_json'] = $parsed;
            }

            if ($exitCode !== 0) {
                return ActionResult::failed(
                    __('webhookhub.script.exit_code', [
                        'code' => (string) $exitCode,
                        'error' => $this->tail($stderr ?: $stdout),
                    ]),
                    $detail
                );
            }

            return ActionResult::success(
                __('webhookhub.script.ok', ['name' => basename((string) $scriptPath)]),
                $detail
            );
        } catch (ProcessTimedOutException) {
            return ActionResult::failed(
                __('webhookhub.script.timeout', ['seconds' => (string) $timeout]),
                $detail
            );
        } catch (RuntimeException $e) {
            return ActionResult::failed($e->getMessage(), $detail);
        } catch (Throwable $e) {
            return ActionResult::failed(get_class($e).': '.$e->getMessage(), $detail);
        } finally {
            if ($temporary && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Renders the argument template and splits it into argv the way a shell
     * would — quotes group, whitespace separates — without running a shell.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function arguments(string $template, array $context): array
    {
        $rendered = trim($this->renderer->renderText($template, $context));

        if ($rendered === '') {
            return [];
        }

        preg_match_all('/"([^"]*)"|\'([^\']*)\'|(\S+)/', $rendered, $matches, PREG_SET_ORDER);

        $out = [];

        foreach ($matches as $match) {
            // The three groups are double-quoted, single-quoted and bare; only
            // one of them is filled per token.
            $token = ($match[3] ?? '') !== ''
                ? $match[3]
                : ((($match[2] ?? '') !== '') ? $match[2] : ($match[1] ?? ''));

            if ($token !== '') {
                $out[] = $token;
            }
        }

        return $out;
    }

    /**
     * Extra environment variables for the process. Values are templates; names
     * are restricted so a rule cannot smuggle in something like LD_PRELOAD.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function environment(mixed $config, array $context): array
    {
        $out = [];

        foreach (is_array($config) ? $config : [] as $name => $value) {
            $name = strtoupper(trim((string) $name));

            if (! preg_match('/^WEBHOOK_[A-Z0-9_]*$/', $name)) {
                continue;
            }

            $out[$name] = $this->renderer->renderText((string) $value, $context);
        }

        return $out;
    }

    /**
     * What the script reads on stdin: the whole message as JSON by default, or a
     * rendered template when the rule asks for one.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function stdin(array $config, array $context): string
    {
        return match ($config['stdin'] ?? 'json') {
            'none' => '',
            'template' => $this->renderer->renderText((string) ($config['stdin_template'] ?? ''), $context),
            default => (string) json_encode(
                $context,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        };
    }

    /** @param array<string, mixed> $config */
    private function timeout(array $config): int
    {
        $default = (int) config('webhookhub.scripts.timeout');
        $max = (int) config('webhookhub.scripts.max_timeout');
        $timeout = (int) ($config['timeout'] ?? $default);

        return max(1, min($timeout ?: $default, $max));
    }

    /**
     * Inline code goes to a private temp file that is removed after the run.
     */
    private function writeTemporary(string $code): string
    {
        $dir = storage_path('app/scripts');

        if (! is_dir($dir) && ! @mkdir($dir, 0770, true) && ! is_dir($dir)) {
            throw new RuntimeException(__('webhookhub.script.temp_failed', ['dir' => $dir]));
        }

        $path = $dir.DIRECTORY_SEPARATOR.'inline-'.bin2hex(random_bytes(8)).'.py';

        if (file_put_contents($path, $code) === false) {
            throw new RuntimeException(__('webhookhub.script.temp_failed', ['dir' => $dir]));
        }

        @chmod($path, 0600);

        return $path;
    }

    private function clip(string $output): string
    {
        $limit = (int) config('webhookhub.scripts.max_output_bytes');

        if ($limit > 0 && strlen($output) > $limit) {
            return substr($output, 0, $limit)."\n…[".__('webhookhub.script.truncated').']';
        }

        return $output;
    }

    private function tail(string $output): string
    {
        $lines = preg_split('/\R/', trim($output)) ?: [];
        $lines = array_slice(array_filter($lines), -3);

        return $lines ? implode(' | ', $lines) : '—';
    }
}
