<?php

namespace App\Modules\Results\Services;

use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\GradingScheme;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GradeScale
{
    public function roundedPercentage(float $percentage): int
    {
        return (int) round($percentage, 0, PHP_ROUND_HALF_UP);
    }

    public function bandFor(int $schemeId, float $percentage): ?GradeBand
    {
        $rounded = $this->roundedPercentage($percentage);

        return GradeBand::where('grading_scheme_id', $schemeId)
            ->where('min_score', '<=', $rounded)
            ->where('max_score', '>=', $rounded)
            ->orderBy('min_score')
            ->first();
    }

    public function coverage(GradingScheme|int $scheme): array
    {
        $schemeId = $scheme instanceof GradingScheme ? $scheme->id : $scheme;
        $bands = GradeBand::where('grading_scheme_id', $schemeId)->orderBy('min_score')->get();

        return $this->coverageFor($bands);
    }

    public function coverageFor(Collection $bands): array
    {
        if ($bands->isEmpty()) {
            return [
                'complete' => false,
                'valid' => true,
                'covered' => null,
                'uncovered' => ['0 to 100'],
                'uncovered_text' => '0 to 100',
                'next_start' => 0,
            ];
        }

        $valid = true;
        $uncovered = [];
        $firstUncoveredStart = null;
        $cursor = 0;
        foreach ($bands as $band) {
            $min = (float) $band->min_score;
            $max = (float) $band->max_score;
            if (! $this->isWhole($min) || ! $this->isWhole($max) || $min < 0 || $max > 100 || $min > $max) {
                $valid = false;

                continue;
            }
            $min = (int) $min;
            $max = (int) $max;
            if ($min > $cursor) {
                $uncovered[] = $this->rangeLabel($cursor, $min - 1);
                $firstUncoveredStart ??= $cursor;
            }
            if ($min < $cursor) {
                $valid = false;
            }
            $cursor = max($cursor, $max + 1);
        }
        if ($cursor <= 100) {
            $uncovered[] = $this->rangeLabel($cursor, 100);
            $firstUncoveredStart ??= $cursor;
        }

        $lowest = $bands->min(fn (GradeBand $band): float => (float) $band->min_score);
        $highest = $bands->max(fn (GradeBand $band): float => (float) $band->max_score);

        return [
            'complete' => $valid && $uncovered === [] && $lowest === 0.0 && $highest === 100.0,
            'valid' => $valid,
            'covered' => $this->displayNumber($lowest).' to '.$this->displayNumber($highest),
            'uncovered' => $uncovered,
            'uncovered_text' => $uncovered === [] ? 'none' : implode(' and ', $uncovered),
            'next_start' => $firstUncoveredStart ?? (int) floor($highest) + 1,
        ];
    }

    public function assertComplete(int $schemeId): void
    {
        $coverage = $this->coverage($schemeId);
        if ($coverage['complete']) {
            return;
        }

        $detail = $coverage['valid']
            ? 'Uncovered marks: '.$coverage['uncovered_text'].'.'
            : 'Every band must use whole numbers and the bands must not overlap or leave an internal gap.';
        throw ValidationException::withMessages([
            'grade' => 'This grade scale is incomplete. '.$detail,
        ]);
    }

    public function assertValidBands(array $bands, string $field): void
    {
        usort($bands, fn (array $a, array $b): int => (float) $a['min_score'] <=> (float) $b['min_score']);
        foreach ($bands as $index => $band) {
            $min = (float) $band['min_score'];
            $max = (float) $band['max_score'];
            if (! $this->isWhole($min) || ! $this->isWhole($max)) {
                throw ValidationException::withMessages([$field => 'Grade band bounds must be whole numbers from 0 to 100.']);
            }
            $min = (int) $min;
            $max = (int) $max;
            if ($min < 0 || $max > 100 || $min > $max) {
                throw ValidationException::withMessages([$field => 'Grade band bounds must be whole numbers from 0 to 100, with minimum no greater than maximum.']);
            }
            if ($index > 0) {
                $previousMax = (int) $bands[$index - 1]['max_score'];
                if ($min <= $previousMax) {
                    throw ValidationException::withMessages([$field => 'This grade band overlaps an existing band.']);
                }
            }
        }
    }

    private function isWhole(float $value): bool
    {
        return floor($value) === $value;
    }

    private function rangeLabel(int $start, int $end): string
    {
        return $start === $end ? (string) $start : $start.' to '.$end;
    }

    private function displayNumber(float $value): string
    {
        return $this->isWhole($value) ? (string) (int) $value : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
