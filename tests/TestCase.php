<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests render Blade views; the compiled Vite manifest is a
        // build artefact and is not required to assert on markup.
        $this->withoutVite();
    }
}
