<?php

namespace Tests\Feature;

use App\Models\RuleAction;
use App\Services\Actions\ActionResult;
use App\Services\Actions\ScriptAction;
use App\Services\Scripts\ScriptLocator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The script action runs code on the server, so what is tested here is mostly
 * what it refuses to do: run while switched off, run inline code that was not
 * allowed, or run anything from outside the configured directory.
 */
class ScriptActionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $python = $this->python();

        if (! $python) {
            $this->markTestSkipped('No python3 on this machine.');
        }

        $this->dir = sys_get_temp_dir().'/webhookhub-scripts-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir);

        config([
            'webhookhub.scripts.enabled' => true,
            'webhookhub.scripts.allow_inline' => false,
            'webhookhub.scripts.python' => $python,
            'webhookhub.scripts.dir' => $this->dir,
            'webhookhub.scripts.timeout' => 10,
            'webhookhub.scripts.max_timeout' => 15,
            'webhookhub.scripts.max_output_bytes' => 64 * 1024,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            File::deleteDirectory($this->dir);
        }

        parent::tearDown();
    }

    private function python(): ?string
    {
        foreach (['/usr/bin/python3', '/usr/local/bin/python3'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $found = trim((string) @shell_exec('command -v python3 2>/dev/null'));

        return $found !== '' ? $found : null;
    }

    /** @param array<string, mixed> $config */
    private function runScript(array $config, bool $dryRun = false): ActionResult
    {
        $action = new RuleAction(['type' => 'script', 'config' => $config]);

        return app(ScriptAction::class)->execute($action, $this->context(), $dryRun);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        return [
            'json' => ['event' => 'order.created', 'total' => 4200],
            'meta' => ['received_at_local' => '2026-08-31 10:00:00'],
            'endpoint' => ['name' => 'Orders'],
        ];
    }

    private function write(string $name, string $code): void
    {
        file_put_contents($this->dir.'/'.$name, $code);
    }

    public function test_it_runs_a_script_from_the_directory_and_keeps_its_output(): void
    {
        $this->write('ok.py', <<<'PY'
            import json, sys
            payload = json.load(sys.stdin)
            print(json.dumps({"event": payload["json"]["event"], "argv": sys.argv[1:]}))
            PY);

        $result = $this->runScript(['script' => 'ok.py', 'args' => '--event "{{ json.event }}" --total {{ json.total }}']);

        $this->assertSame('success', $result->status, $result->error ?? '');
        $this->assertSame(0, $result->detail['exit_code']);
        $this->assertSame('order.created', $result->detail['output_json']['event']);
        $this->assertSame(['--event', 'order.created', '--total', '4200'], $result->detail['output_json']['argv']);
    }

    public function test_a_non_zero_exit_code_fails_the_action_with_the_stderr_tail(): void
    {
        $this->write('boom.py', <<<'PY'
            import sys
            print("something went wrong", file=sys.stderr)
            sys.exit(3)
            PY);

        $result = $this->runScript(['script' => 'boom.py']);

        $this->assertSame('failed', $result->status);
        $this->assertSame(3, $result->detail['exit_code']);
        $this->assertStringContainsString('something went wrong', (string) $result->error);
    }

    public function test_it_stops_a_script_that_overruns_its_timeout(): void
    {
        $this->write('slow.py', <<<'PY'
            import time
            time.sleep(30)
            PY);

        $result = $this->runScript(['script' => 'slow.py', 'timeout' => 1]);

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('1 second', (string) $result->error);
    }

    public function test_it_refuses_a_path_outside_the_script_directory(): void
    {
        $outside = sys_get_temp_dir().'/webhookhub-outside-'.bin2hex(random_bytes(4)).'.py';
        file_put_contents($outside, 'print("nope")');

        try {
            $result = $this->runScript(['script' => '../'.basename($outside)]);

            $this->assertSame('failed', $result->status);
            $this->assertArrayNotHasKey('exit_code', $result->detail);
        } finally {
            @unlink($outside);
        }
    }

    public function test_it_refuses_a_symlink_that_points_out_of_the_directory(): void
    {
        $outside = sys_get_temp_dir().'/webhookhub-outside-'.bin2hex(random_bytes(4)).'.py';
        file_put_contents($outside, 'print("nope")');
        symlink($outside, $this->dir.'/link.py');

        try {
            $result = $this->runScript(['script' => 'link.py']);

            $this->assertSame('failed', $result->status);
        } finally {
            @unlink($outside);
        }
    }

    public function test_it_refuses_files_that_are_not_python(): void
    {
        $this->write('payload.txt', 'print("nope")');

        $result = $this->runScript(['script' => 'payload.txt']);

        $this->assertSame('failed', $result->status);
    }

    public function test_inline_code_needs_its_own_switch(): void
    {
        $config = ['source' => 'inline', 'code' => 'print("hello")'];

        $this->assertSame('failed', $this->runScript($config)->status);

        config(['webhookhub.scripts.allow_inline' => true]);

        $result = $this->runScript($config);

        $this->assertSame('success', $result->status, $result->error ?? '');
        $this->assertStringContainsString('hello', $result->detail['stdout']);
    }

    public function test_inline_code_can_import_from_the_script_directory(): void
    {
        config(['webhookhub.scripts.allow_inline' => true]);
        $this->write('helper.py', 'VALUE = "shared"');

        $result = $this->runScript([
            'source' => 'inline',
            'code' => "import helper\nprint(helper.VALUE)",
        ]);

        $this->assertSame('success', $result->status, $result->error ?? '');
        $this->assertStringContainsString('shared', $result->detail['stdout']);
    }

    public function test_it_prefers_the_virtualenv_interpreter_when_one_was_built(): void
    {
        $locator = app(ScriptLocator::class);

        config(['webhookhub.scripts.venv' => $this->dir.'/pyenv']);

        // No virtualenv yet: the configured interpreter stands.
        $this->assertSame(config('webhookhub.scripts.python'), $locator->interpreter());
        $this->assertNull($locator->venvPython());

        File::ensureDirectoryExists($this->dir.'/pyenv/bin');
        file_put_contents($this->dir.'/pyenv/bin/python3', "#!/bin/sh\nexec ".config('webhookhub.scripts.python')." \"$@\"\n");
        chmod($this->dir.'/pyenv/bin/python3', 0o755);

        $this->assertSame($this->dir.'/pyenv/bin/python3', $locator->interpreter());

        // And a script really runs through it.
        $this->write('venv.py', 'print("through the venv")');

        $result = $this->runScript(['script' => 'venv.py']);

        $this->assertSame('success', $result->status, $result->error ?? '');
        $this->assertStringContainsString('through the venv', $result->detail['stdout']);
    }

    public function test_a_failed_requirements_install_is_readable_afterwards(): void
    {
        $locator = app(ScriptLocator::class);

        config(['webhookhub.scripts.venv' => $this->dir.'/pyenv']);

        $this->assertNull($locator->requirementsError());

        File::ensureDirectoryExists($this->dir.'/pyenv');
        file_put_contents($this->dir.'/pyenv/.requirements-error', "ERROR: No matching distribution found for nosuchpkg\n");

        $this->assertStringContainsString('nosuchpkg', (string) $locator->requirementsError());
    }

    public function test_the_whole_feature_is_off_until_it_is_switched_on(): void
    {
        config(['webhookhub.scripts.enabled' => false]);
        $this->write('ok.py', 'print("ran")');

        $result = $this->runScript(['script' => 'ok.py']);

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('WEBHOOK_SCRIPTS_ENABLED', (string) $result->error);
    }

    public function test_a_dry_run_does_not_start_the_process(): void
    {
        $this->write('marker.py', <<<PY
            open({$this->quoted($this->dir.'/ran.txt')}, "w").write("ran")
            PY);

        $result = $this->runScript(['script' => 'marker.py'], dryRun: true);

        $this->assertSame('skipped', $result->status);
        $this->assertFileDoesNotExist($this->dir.'/ran.txt');
    }

    public function test_only_webhook_prefixed_environment_variables_are_passed_through(): void
    {
        $this->write('env.py', <<<'PY'
            import json, os
            print(json.dumps({k: v for k, v in os.environ.items() if k.startswith("WEBHOOK_") or k == "SECRET_TOKEN"}))
            PY);

        $result = $this->runScript([
            'script' => 'env.py',
            'env' => [
                'WEBHOOK_EVENT' => '{{ json.event }}',
                'SECRET_TOKEN' => 'nope',
            ],
        ]);

        $this->assertSame('success', $result->status, $result->error ?? '');
        $this->assertSame('order.created', $result->detail['output_json']['WEBHOOK_EVENT']);
        $this->assertArrayNotHasKey('SECRET_TOKEN', $result->detail['output_json']);
    }

    public function test_the_process_environment_reaches_the_script(): void
    {
        // How AS/400 credentials get to a script: set on the container, never
        // typed into a rule. Symfony's Process inherits the parent environment
        // from $_ENV/$_SERVER, which is where a container's variables land —
        // putenv() alone would not show up there, and so would prove nothing.
        $_ENV['AS400_HOST'] = $_SERVER['AS400_HOST'] = 'ibmi.example.local';

        $this->write('inherit.py', <<<'PY'
            import json, os
            print(json.dumps({"host": os.environ.get("AS400_HOST")}))
            PY);

        try {
            $result = $this->runScript(['script' => 'inherit.py']);

            $this->assertSame('success', $result->status, $result->error ?? '');
            $this->assertSame('ibmi.example.local', $result->detail['output_json']['host']);
        } finally {
            unset($_ENV['AS400_HOST'], $_SERVER['AS400_HOST']);
        }
    }

    private function quoted(string $value): string
    {
        return "'".str_replace("'", "\\'", $value)."'";
    }
}
