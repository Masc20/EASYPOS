<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $compiledPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'easypos_testing_views';
        if (! is_dir($compiledPath)) {
            @mkdir($compiledPath, 0777, true);
        }

        $app['config']->set('view.compiled', $compiledPath);

        if ($app->bound('blade.compiler')) {
            $compiler = $app->make('blade.compiler');
            $ref = new \ReflectionProperty($compiler, 'cachePath');
            $ref->setValue($compiler, $compiledPath);
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Suppress Windows sandboxed environment tempnam notice during Blade compilation
        set_error_handler(function (int $errno, string $errstr) {
            if (str_contains($errstr, "tempnam(): file created in the system's temporary directory")) {
                return true;
            }

            return false;
        });
    }
}
