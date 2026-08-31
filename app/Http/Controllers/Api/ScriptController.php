<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Scripts\ScriptLocator;
use Illuminate\Http\JsonResponse;

/**
 * What the rule editor needs to offer a script action: whether the feature is
 * switched on at all, and which files it may choose from.
 */
class ScriptController extends Controller
{
    public function __construct(private readonly ScriptLocator $locator) {}

    public function index(): JsonResponse
    {
        $enabled = $this->locator->enabled();

        return response()->json([
            'enabled' => $enabled,
            'allow_inline' => $enabled && $this->locator->inlineAllowed(),
            'directory' => (string) config('webhookhub.scripts.dir'),
            'directory_exists' => $this->locator->directory() !== null,
            'interpreter' => $this->locator->interpreter(),
            'venv' => $this->locator->venvPython() !== null,
            'requirements_error' => $this->locator->requirementsError(),
            'default_timeout' => (int) config('webhookhub.scripts.timeout'),
            'max_timeout' => (int) config('webhookhub.scripts.max_timeout'),
            'scripts' => $enabled ? $this->locator->available() : [],
        ]);
    }
}
