<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_calendar(): void
    {
        $this->get('/')->assertRedirect('/calendar');
    }
}
