<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Core\Models\User;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\ResultTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ResultConfigurationAccess
{
    public function isAdmin(User $actor): bool
    {
        return $actor->can('manage_result_settings');
    }

    public function sectionIds(User $actor): array
    {
        return $this->isAdmin($actor)
            ? Section::orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : app(SectionAccess::class)->sectionIds($actor);
    }

    public function selectedSectionId(User $actor, ?int $requestedSectionId): ?int
    {
        if ($this->isAdmin($actor)) {
            if ($requestedSectionId !== null) {
                $this->assertSection($actor, $requestedSectionId);
            }

            return $requestedSectionId;
        }

        $ids = $this->sectionIds($actor);
        if ($ids === []) {
            return null;
        }
        $selected = $requestedSectionId ?? $ids[0];
        $this->assertSection($actor, $selected);

        return $selected;
    }

    public function createSectionId(User $actor, mixed $submittedSectionId): int
    {
        if ($this->isAdmin($actor)) {
            if ($submittedSectionId === null || $submittedSectionId === '') {
                throw ValidationException::withMessages(['section_id' => 'Choose a Section. A Section cannot be changed after you save.']);
            }
            $sectionId = (int) $submittedSectionId;
            $this->assertSection($actor, $sectionId);

            return $sectionId;
        }

        $ids = $this->sectionIds($actor);
        if ($ids === []) {
            throw new AuthorizationException('No Section is assigned to your account.');
        }
        if ($submittedSectionId !== null && $submittedSectionId !== '') {
            $sectionId = (int) $submittedSectionId;
            if (! in_array($sectionId, $ids, true)) {
                throw new AuthorizationException('You cannot create Result configuration for another Section.');
            }

            return $sectionId;
        }

        return $ids[0];
    }

    public function assertSection(User $actor, int $sectionId): void
    {
        if (! Section::whereKey($sectionId)->exists()) {
            throw new AuthorizationException('That Section does not exist in this school.');
        }
        if (! $this->isAdmin($actor) && ! in_array($sectionId, $this->sectionIds($actor), true)) {
            throw new AuthorizationException('You cannot access another Section’s Result configuration.');
        }
    }

    public function assertScheme(User $actor, GradingScheme $scheme): void
    {
        $this->assertSection($actor, (int) $scheme->section_id);
    }

    public function assertBand(User $actor, GradeBand $band): void
    {
        $scheme = $band->scheme()->firstOrFail();
        $this->assertScheme($actor, $scheme);
    }

    public function assertComponent(User $actor, AssessmentComponent $component): void
    {
        $this->assertSection($actor, (int) $component->section_id);
    }

    public function templateSectionId(ResultTemplate $template): int
    {
        return (int) $template->section_id;
    }

    public function assertTemplate(User $actor, ResultTemplate $template): void
    {
        $this->assertSection($actor, $this->templateSectionId($template));
    }

    public function assertImmutableSection(mixed $submittedSectionId, int $currentSectionId): void
    {
        if ($submittedSectionId !== null && $submittedSectionId !== '' && (int) $submittedSectionId !== $currentSectionId) {
            throw new AuthorizationException('A Section cannot be changed after this item is created. Delete it and recreate it in the correct Section.');
        }
    }
}
