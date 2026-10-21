<section class="oak-section">
    <div class="oak-section-head"><h2>{{ $overview['kind'] === 'head' ? 'Results awaiting your approval' : 'Results awaiting approval' }}</h2>@if($overview['approval_count'] > count($overview['approvals']))<a class="oak-link" href="{{ route('results.index') }}" wire:navigate>See all {{ $overview['approval_count'] }}</a>@endif</div>
    @if($overview['approvals'])
        <div class="oak-table-scroll"><table class="oak-table ad-queue"><thead><tr><th>Class and subject</th>@if($overview['kind'] === 'admin' && !$overview['selected_section_id'])<th>Section</th>@endif<th>Teacher</th><th>Submitted</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
        @foreach($overview['approvals'] as $row)<tr><td>{{ $row['class'] }} · {{ $row['subject'] }}</td>@if($overview['kind'] === 'admin' && !$overview['selected_section_id'])<td><span class="ad-tag">{{ $row['section'] }}</span></td>@endif<td>{{ $row['teacher'] }}</td><td>{{ $row['submitted_at']?->timezone('Africa/Lagos')->format('D j M, H:i') }}</td><td><a class="oak-btn oak-btn-secondary" href="{{ $row['url'] }}" wire:navigate>Review</a></td></tr>@endforeach
        </tbody></table></div>
    @else
        <div class="oak-empty"><x-icon name="circle-check" /><strong>Nothing is waiting</strong>Submitted results appear here for approval.</div>
    @endif
</section>
