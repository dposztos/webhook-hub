<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * What the rule editor asks the server before it offers a script action.
 */
class ScriptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_script_list_needs_authentication(): void
    {
        $this->getJson('/api/scripts')->assertUnauthorized();
    }

    public function test_it_reports_the_feature_as_off_and_hides_the_file_list(): void
    {
        $directory = $this->directoryWithScript();

        config([
            'webhookhub.scripts.enabled' => false,
            'webhookhub.scripts.dir' => $directory,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/scripts')
            ->assertOk()
            ->assertJson(['enabled' => false, 'allow_inline' => false, 'scripts' => []]);

        File::deleteDirectory($directory);
    }

    public function test_it_lists_the_python_files_once_switched_on(): void
    {
        $directory = $this->directoryWithScript();

        config([
            'webhookhub.scripts.enabled' => true,
            'webhookhub.scripts.allow_inline' => true,
            'webhookhub.scripts.dir' => $directory,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/scripts')
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'allow_inline' => true,
                'directory_exists' => true,
                'scripts' => ['nested/deep.py', 'top.py'],
            ]);

        File::deleteDirectory($directory);
    }

    private function directoryWithScript(): string
    {
        $directory = sys_get_temp_dir().'/webhookhub-api-'.bin2hex(random_bytes(4));

        File::ensureDirectoryExists($directory.'/nested');
        file_put_contents($directory.'/top.py', 'print(1)');
        file_put_contents($directory.'/nested/deep.py', 'print(2)');
        // Neither of these may show up in the list.
        file_put_contents($directory.'/notes.txt', 'ignored');
        file_put_contents($directory.'/.hidden.py', 'print(3)');

        return $directory;
    }
}
