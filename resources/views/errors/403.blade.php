<x-error-page code="403" title="Access denied" message="You do not have access to this page." :detail="$detail ?? null" :action-url="route('dashboard')" action-label="Go to dashboard" />
