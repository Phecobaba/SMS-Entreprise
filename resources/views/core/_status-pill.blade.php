@php
    $oakStatus = strtolower((string) $status);
    $oakTone = in_array($oakStatus, ['paid', 'approved', 'present', 'submitted', 'passed', 'accepted', 'active', 'published', 'payment confirmed', 'offer accepted', 'converted to student'], true)
        ? 'success'
        : (in_array($oakStatus, ['absent', 'overdue', 'rejected', 'failed', 'declined', 'terminated', 'sacked', 'offer lapsed'], true) ? 'danger' : 'warning');
@endphp
<span class="oak-status-pill oak-status-{{ $oakTone }}">{{ $status }}</span>
