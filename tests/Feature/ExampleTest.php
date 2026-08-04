<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_tenancy_is_opt_in_by_default()
    {
        self::assertFalse((bool) config('tenancy.enabled'));
    }
}
