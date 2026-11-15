<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Services\ActivityLogPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request, ActivityLogPresenter $presenter): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'module' => ['nullable', 'string', Rule::in($presenter->modules())],
            'person' => ['nullable', 'string', 'max:100'],
        ]);

        $entries = AuditLog::query()
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', CarbonImmutable::parse($date, 'Africa/Lagos')->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', CarbonImmutable::parse($date, 'Africa/Lagos')->endOfDay()))
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $presenter->constrainToModule($query, $module))
            ->when($filters['person'] ?? null, function (Builder $query, string $person): void {
                $needle = '%'.addcslashes($person, '%_\\').'%';
                $query->where(function (Builder $people) use ($needle): void {
                    $people->where('actor_name', 'like', $needle)
                        ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', $needle));
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('core.activity-log', [
            'entries' => $entries,
            'modules' => $presenter->modules(),
            'presenter' => $presenter,
            'filters' => $filters,
        ]);
    }
}
