<?php

namespace Tests\Feature;

use App\Models\ActionRun;
use App\Models\Endpoint;
use App\Models\Message;
use App\Models\Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The whole path in one test: a webhook arrives, a rule matches it, and the
 * script it names actually runs and is recorded.
 */
class ScriptRuleFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $python = is_executable('/usr/bin/python3')
            ? '/usr/bin/python3'
            : trim((string) @shell_exec('command -v python3 2>/dev/null'));

        if ($python === '') {
            $this->markTestSkipped('No python3 on this machine.');
        }

        $this->dir = sys_get_temp_dir().'/webhookhub-flow-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir);

        config([
            'webhookhub.scripts.enabled' => true,
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

    public function test_an_incoming_webhook_runs_the_script_named_by_a_matching_rule(): void
    {
        file_put_contents($this->dir.'/handle.py', <<<'PY'
            import json, sys
            payload = json.load(sys.stdin)
            print(json.dumps({"order": payload["json"]["order"]["id"], "argv": sys.argv[1:]}))
            PY);

        $endpoint = Endpoint::create(['name' => 'Orders', 'slug' => 'orders']);

        $rule = Rule::create([
            'name' => 'Paid orders go to the script',
            'endpoint_id' => $endpoint->id,
            'conditions' => [
                'type' => 'group',
                'op' => 'and',
                'children' => [
                    ['type' => 'condition', 'source' => 'json', 'path' => 'event', 'operator' => 'equals', 'value' => 'order.paid'],
                ],
            ],
        ]);

        $rule->actions()->create([
            'type' => 'script',
            'config' => ['script' => 'handle.py', 'args' => '--id {{ json.order.id }}'],
        ]);

        $this->postJson("/u/orders/{$endpoint->secret}", [
            'event' => 'order.paid',
            'order' => ['id' => 'ORD-7'],
        ])->assertOk();

        $message = Message::firstOrFail();
        $this->assertSame(1, $message->actions_ok);
        $this->assertSame(0, $message->actions_failed);

        $run = ActionRun::firstOrFail();
        $this->assertSame('script', $run->type);
        $this->assertSame('success', $run->status);
        $this->assertSame('ORD-7', $run->detail['output_json']['order']);
        $this->assertSame(['--id', 'ORD-7'], $run->detail['output_json']['argv']);
    }

    public function test_a_rule_naming_a_missing_script_is_recorded_as_failed(): void
    {
        $endpoint = Endpoint::create(['name' => 'Orders', 'slug' => 'orders']);

        $rule = Rule::create([
            'name' => 'Everything',
            'endpoint_id' => $endpoint->id,
            'conditions' => ['type' => 'group', 'op' => 'and', 'children' => []],
        ]);

        $rule->actions()->create(['type' => 'script', 'config' => ['script' => 'gone.py']]);

        $this->postJson("/u/orders/{$endpoint->secret}", ['event' => 'anything'])->assertOk();

        $this->assertSame(1, Message::firstOrFail()->actions_failed);
        $this->assertSame('failed', ActionRun::firstOrFail()->status);
    }
}
