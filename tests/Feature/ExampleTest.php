<?php

namespace Tests\Feature;

use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $school = School::create(['name' => 'Example School']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create(['motto' => 'Learn and grow']);

        $response = $this->get('/');

        $response->assertOk()->assertSee('Example School')->assertSee('Learn and grow');
    }
}
