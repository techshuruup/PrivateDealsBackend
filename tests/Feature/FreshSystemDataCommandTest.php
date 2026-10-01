<?php

namespace Tests\Feature;

use Tests\TestCase;

class FreshSystemDataCommandTest extends TestCase
{
    public function test_fresh_refuses_without_force(): void
    {
        $this->artisan('system:fresh')
            ->expectsOutputToContain('Refusing to run without --force')
            ->assertExitCode(1);
    }
}
