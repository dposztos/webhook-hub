<?php

namespace Tests\Feature;

use App\Models\ActionRun;
use App\Models\Endpoint;
use App\Models\Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Actions of one rule as a chain: what an earlier step produced is what a later
 * step's templates can read.
 */
class ScriptChainTest extends TestCase
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

        $this->dir = sys_get_temp_dir().'/webhookhub-chain-'.bin2hex(random_bytes(4));
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

    private function endpoint(): Endpoint
    {
        return Endpoint::create(['name' => 'Orders', 'slug' => 'orders']);
    }

    private function rule(Endpoint $endpoint, string $name = 'Everything'): Rule
    {
        return Rule::create([
            'name' => $name,
            'endpoint_id' => $endpoint->id,
            'conditions' => ['type' => 'group', 'op' => 'and', 'children' => []],
        ]);
    }

    private function send(Endpoint $endpoint, array $payload = ['event' => 'order.paid']): void
    {
        $this->postJson("/u/orders/{$endpoint->secret}", $payload)->assertOk();
    }

    public function test_an_email_can_use_what_the_script_before_it_printed(): void
    {
        file_put_contents($this->dir.'/query.py', <<<'PY'
            import json
            print(json.dumps({"total": 42, "rows": [{"name": "ACME"}, {"name": "Other"}]}))
            PY);

        $endpoint = $this->endpoint();
        $rule = $this->rule($endpoint);

        // The name is what the template addresses, accents and all.
        $rule->actions()->create([
            'type' => 'script',
            'name' => 'Lekérdezés',
            'position' => 0,
            'config' => ['script' => 'query.py'],
        ]);

        $rule->actions()->create([
            'type' => 'email',
            'name' => 'Értesítés',
            'position' => 1,
            'config' => [
                'to' => 'ops@example.com',
                'subject' => 'Összesen: {{ steps.lekerdezes.output.total }}',
                'body_html' => '<p>{{ steps.lekerdezes.output.rows.0.name }}</p>',
            ],
        ]);

        $this->send($endpoint);

        $email = ActionRun::where('type', 'email')->firstOrFail();

        $this->assertSame('success', $email->status, $email->error ?? '');
        $this->assertSame('Összesen: 42', $email->detail['subject']);

        // The rendered body is deliberately kept out of the run log, so the
        // proof that the loop variable resolved comes from the mail itself.
        $sent = app('mailer')->getSymfonyTransport()->messages();

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('ACME', $sent[0]->toString());
    }

    public function test_a_step_can_be_told_to_run_only_after_a_successful_one(): void
    {
        file_put_contents($this->dir.'/broken.py', <<<'PY'
            import sys
            print("the query failed", file=sys.stderr)
            sys.exit(2)
            PY);

        $endpoint = $this->endpoint();
        $rule = $this->rule($endpoint);

        $rule->actions()->create([
            'type' => 'script',
            'name' => 'Lekérdezés',
            'position' => 0,
            'config' => ['script' => 'broken.py'],
        ]);

        $rule->actions()->create([
            'type' => 'email',
            'name' => 'Értesítés',
            'position' => 1,
            'config' => [
                'to' => 'ops@example.com',
                'subject' => 'Összesen: {{ steps.lekerdezes.output.total }}',
                'body_html' => '<p>x</p>',
                'only_if_previous_succeeded' => true,
            ],
        ]);

        $this->send($endpoint);

        $email = ActionRun::where('type', 'email')->firstOrFail();

        $this->assertSame('skipped', $email->status);
        $this->assertStringContainsString('failed', $email->summary);
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());
    }

    public function test_without_that_switch_the_next_step_still_runs(): void
    {
        file_put_contents($this->dir.'/broken.py', 'import sys; sys.exit(2)');

        $endpoint = $this->endpoint();
        $rule = $this->rule($endpoint);

        $rule->actions()->create(['type' => 'script', 'name' => 'Q', 'position' => 0, 'config' => ['script' => 'broken.py']]);
        $rule->actions()->create([
            'type' => 'email',
            'position' => 1,
            'config' => ['to' => 'ops@example.com', 'subject' => 'ok', 'body_html' => '<p>x</p>'],
        ]);

        $this->send($endpoint);

        $this->assertSame('success', ActionRun::where('type', 'email')->firstOrFail()->status);
    }

    public function test_an_unnamed_step_is_addressed_by_its_position(): void
    {
        file_put_contents($this->dir.'/query.py', 'import json; print(json.dumps({"value": "here"}))');

        $endpoint = $this->endpoint();
        $rule = $this->rule($endpoint);

        $rule->actions()->create(['type' => 'script', 'position' => 0, 'config' => ['script' => 'query.py']]);
        $rule->actions()->create([
            'type' => 'email',
            'position' => 1,
            'config' => [
                'to' => 'ops@example.com',
                'subject' => 'v={{ steps.step_1.output.value }}',
                'body_html' => '<p>x</p>',
            ],
        ]);

        $this->send($endpoint);

        $this->assertSame('v=here', ActionRun::where('type', 'email')->firstOrFail()->detail['subject']);
    }

    public function test_two_steps_with_the_same_name_do_not_overwrite_each_other(): void
    {
        file_put_contents($this->dir.'/one.py', 'import json; print(json.dumps({"n": 1}))');
        file_put_contents($this->dir.'/two.py', 'import json; print(json.dumps({"n": 2}))');

        $endpoint = $this->endpoint();
        $rule = $this->rule($endpoint);

        $rule->actions()->create(['type' => 'script', 'name' => 'Q', 'position' => 0, 'config' => ['script' => 'one.py']]);
        $rule->actions()->create(['type' => 'script', 'name' => 'Q', 'position' => 1, 'config' => ['script' => 'two.py']]);
        $rule->actions()->create([
            'type' => 'email',
            'position' => 2,
            'config' => [
                'to' => 'ops@example.com',
                'subject' => '{{ steps.q.output.n }}-{{ steps.q_2.output.n }}',
                'body_html' => '<p>x</p>',
            ],
        ]);

        $this->send($endpoint);

        $this->assertSame('1-2', ActionRun::where('type', 'email')->firstOrFail()->detail['subject']);
    }

    public function test_steps_do_not_leak_from_one_rule_into_another(): void
    {
        file_put_contents($this->dir.'/query.py', 'import json; print(json.dumps({"value": "first"}))');

        $endpoint = $this->endpoint();

        $first = $this->rule($endpoint, 'First');
        $first->actions()->create(['type' => 'script', 'name' => 'Q', 'position' => 0, 'config' => ['script' => 'query.py']]);

        $second = $this->rule($endpoint, 'Second');
        $second->actions()->create([
            'type' => 'email',
            'position' => 0,
            'config' => [
                'to' => 'ops@example.com',
                // The other rule's step must not resolve here; an unknown path
                // renders empty rather than carrying the neighbour's data.
                'subject' => 'v={{ steps.q.output.value|default("—") }}',
                'body_html' => '<p>x</p>',
            ],
        ]);

        $this->send($endpoint);

        $this->assertSame('v=—', ActionRun::where('type', 'email')->firstOrFail()->detail['subject']);
    }
}
