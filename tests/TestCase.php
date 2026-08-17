<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Both Blade views call @vite, which needs public/build/manifest.json —
        // a file that only exists once the frontend has been built. Without
        // this, the suite passes on a developer machine and fails on a clean
        // checkout. Building assets is the frontend CI job's business, not the
        // PHP suite's.
        $this->withoutVite();
    }
}
