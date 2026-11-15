<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Services\QaDatabaseProofRoute;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Tests\TestCase;

class QaDatabaseProofRouteTest extends TestCase
{
    public function test_proof_route_is_stateless_and_gated_by_marker_and_environment(): void
    {
        $registrar = app(QaDatabaseProofRoute::class);
        $activeRouter = new Router(app(Dispatcher::class), app());
        $active = $registrar->register($activeRouter, 'local', '1');

        $this->assertNotNull($active);
        $this->assertSame([], $active->gatherMiddleware());
        $this->assertNotContains('web', $active->gatherMiddleware());
        $this->assertNotContains('Illuminate\Session\Middleware\StartSession', $active->gatherMiddleware());
        $this->assertNotContains('Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse', $active->gatherMiddleware());
        $this->assertNotContains('Illuminate\Foundation\Http\Middleware\ValidateCsrfToken', $active->gatherMiddleware());

        $withoutMarker = new Router(app(Dispatcher::class), app());
        $production = new Router(app(Dispatcher::class), app());

        $this->assertNull($registrar->register($withoutMarker, 'local', null));
        $this->assertCount(0, $withoutMarker->getRoutes());
        $this->assertNull($registrar->register($production, 'production', '1'));
        $this->assertCount(0, $production->getRoutes());
        $this->assertNull(app('router')->getRoutes()->getByName('qa.database-proof'));
    }
}
