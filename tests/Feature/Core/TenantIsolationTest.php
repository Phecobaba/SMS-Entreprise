<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_queries_and_creates_are_scoped_to_the_current_school(): void
    {
        $firstSchool = School::create(['name' => 'First School']);
        $secondSchool = School::create(['name' => 'Second School']);
        $context = app(TenantContext::class);

        $context->set($firstSchool->id);
        $firstUser = User::create([
            'name' => 'First User',
            'email' => 'same@example.test',
            'password' => 'secret-password',
        ]);

        $context->set($secondSchool->id);
        $secondUser = User::create([
            'name' => 'Second User',
            'email' => 'same@example.test',
            'password' => 'secret-password',
        ]);

        $this->assertSame($firstSchool->id, DB::table('users')->where('id', $firstUser->id)->value('school_id'));
        $this->assertSame($secondSchool->id, DB::table('users')->where('id', $secondUser->id)->value('school_id'));
        $this->assertNull(User::find($firstUser->id));
        $this->assertSame([$secondUser->id], User::pluck('id')->all());

        $context->set($firstSchool->id);
        $this->assertNull(User::find($secondUser->id));
        $this->assertSame([$firstUser->id], User::pluck('id')->all());

        $context->clear();

        config()->set('tenancy.default_school_id', $firstSchool->id);
        Route::middleware('auth')->get('/tenant-probe', fn () => response()->json(User::pluck('id')));
        $this->actingAs($firstUser)->get('/tenant-probe')->assertOk()->assertExactJson([$firstUser->id]);
    }
}
