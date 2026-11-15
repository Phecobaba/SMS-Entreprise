<div class="oak-dashboard-actions oak-teacher-actions">
    @if($overview['form_arms']->isNotEmpty() || auth()->user()->can('manage_attendance'))<a class="oak-action oak-action-primary" href="{{ route('attendance.index') }}" wire:navigate><span aria-hidden="true">✓</span>Mark attendance</a>@endif
    <a class="oak-action oak-action-secondary" href="{{ route('results.index', ['workspace' => 'teaching']) }}" wire:navigate><span aria-hidden="true">▤</span>Enter scores</a>
</div>
<div class="oak-metric-grid oak-metric-grid-three">
    <div class="oak-metric"><span>{{ $overview['learners_term'] }}</span><strong>{{ $overview['students'] }}</strong></div>
    <div class="oak-metric"><span>Attendance</span><strong class="oak-metric-success">M {{ ($overview['attendance']['Morning'] ?? null) === null ? '—' : $overview['attendance']['Morning'].'%' }} · A {{ ($overview['attendance']['Afternoon'] ?? null) === null ? '—' : $overview['attendance']['Afternoon'].'%' }}</strong></div>
    <div class="oak-metric"><span>Pending</span><strong class="oak-metric-accent">{{ $overview['pending'] }}</strong></div>
</div>
<section class="oak-dashboard-section"><h2 class="oak-roster-heading">Your teaching</h2>
    <div class="oak-two-col">
        <div><strong>Form arms</strong>@forelse($overview['form_arms'] as $label)<div class="oak-list-row"><span>{{ $label }}</span></div>@empty<p class="oak-empty">No form arm assigned.</p>@endforelse</div>
        <div><strong>Assigned subjects</strong>@forelse($overview['assigned_pairs'] as $label)<div class="oak-list-row"><span>{{ $label }}</span></div>@empty<p class="oak-empty">No class and subject pair assigned.</p>@endforelse</div>
    </div>
</section>
<section class="oak-dashboard-section"><h2 class="oak-roster-heading">Class roster</h2>
    @if($overview['roster'])<div class="oak-table-scroll"><table class="oak-roster"><thead><tr><th>{{ $overview['learner_term'] }}</th><th>Status</th></tr></thead><tbody>
        @foreach($overview['roster'] as $row)<tr><td>{{ $row['name'] }}</td><td><span class="oak-status-pill {{ $row['status'] === 'Present' ? 'oak-status-success' : (in_array($row['status'], ['Absent']) ? 'oak-status-danger' : 'oak-status-warning') }}">{{ $row['status'] }}</span></td></tr>@endforeach
    </tbody></table></div>@else<p class="oak-empty">No {{ strtolower($overview['learners_term']) }} are assigned to your form-teacher class yet.</p>@endif
</section>
