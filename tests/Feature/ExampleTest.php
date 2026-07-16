<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_lleva_al_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}
