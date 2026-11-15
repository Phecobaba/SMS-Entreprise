@props(['context' => null, 'plural' => false, 'capitalized' => true]){{ app(\App\Modules\Academics\Services\LearnerTerminology::class)->word($context, (bool) $plural, (bool) $capitalized) }}
