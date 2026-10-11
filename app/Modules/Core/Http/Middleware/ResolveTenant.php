<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\TenantContext;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('tenancy.mode') !== 'single_school') {
            throw new RuntimeException('Unsupported application mode.');
        }

        $this->tenantContext->set((int) config('tenancy.default_school_id'));

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
