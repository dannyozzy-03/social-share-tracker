<?php

declare(strict_types=1);

namespace App\Stats;

use App\Validation\ValidationException;
use App\Validation\Validator;
use DateTimeImmutable;

final class StatsFilter
{
    public const GRANULARITIES = ['day', 'week', 'month'];
    public const DEFAULT_RANGE_DAYS = 30;
    public const MAX_RANGE_DAYS = 731;

    /** @param list<int> $platformIds empty means all platforms */
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly string $granularity = 'day',
        public readonly array $platformIds = [],
        public readonly ?int $pageId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @param list<int> $knownPlatformIds
     * @throws ValidationException
     */
    public static function fromQuery(array $query, array $knownPlatformIds, ?DateTimeImmutable $today = null): self
    {
        $today = ($today ?? new DateTimeImmutable())->setTime(0, 0);
        $errors = [];

        $from = self::dateParam($query, 'from', $today->modify(sprintf('-%d days', self::DEFAULT_RANGE_DAYS - 1)), $errors);
        $to = self::dateParam($query, 'to', $today, $errors);
        if ($from !== null && $to !== null) {
            if ($from > $to) {
                $errors['from'] = 'The start date must be on or before the end date.';
            } elseif ($from->diff($to)->days + 1 > self::MAX_RANGE_DAYS) {
                $errors['to'] = sprintf('Choose a date range of %d days or less.', self::MAX_RANGE_DAYS);
            }
        }

        $granularity = $query['granularity'] ?? '';
        $granularity = $granularity === '' ? 'day' : $granularity;
        if (!in_array($granularity, self::GRANULARITIES, true)) {
            $errors['granularity'] = 'Group results by day, week or month.';
        }

        $platformIds = [];
        $rawPlatforms = $query['platforms'] ?? [];
        if (is_string($rawPlatforms)) {
            $rawPlatforms = $rawPlatforms === '' ? [] : explode(',', $rawPlatforms);
        }
        if (!is_array($rawPlatforms)) {
            $errors['platforms'] = 'Invalid platform selection.';
        } else {
            foreach ($rawPlatforms as $value) {
                if (!is_string($value) || !ctype_digit($value) || !in_array((int) $value, $knownPlatformIds, true)) {
                    $errors['platforms'] = 'Unknown platform selected.';
                    break;
                }
                $platformIds[] = (int) $value;
            }
        }
        $platformIds = array_values(array_unique($platformIds));
        sort($platformIds);

        $pageId = null;
        $rawPage = $query['page'] ?? '';
        if ($rawPage !== '') {
            if (!is_string($rawPage) || !ctype_digit($rawPage) || (int) $rawPage < 1) {
                $errors['page'] = 'Invalid page selected.';
            } else {
                $pageId = (int) $rawPage;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($from, $to, $granularity, $platformIds, $pageId);
    }

    public function days(): int
    {
        return $this->from->diff($this->to)->days + 1;
    }

    public function previousPeriod(): self
    {
        $shift = sprintf('-%d days', $this->days());

        return new self($this->from->modify($shift), $this->to->modify($shift), $this->granularity, $this->platformIds, $this->pageId);
    }

    /**
     * Weeks start on Monday to match MySQL's WEEKDAY().
     * @return list<string>
     */
    public function periods(): array
    {
        [$cursor, $step] = match ($this->granularity) {
            'week' => [$this->from->modify('monday this week'), '+1 week'],
            'month' => [$this->from->modify('first day of this month'), '+1 month'],
            default => [$this->from, '+1 day'],
        };

        $periods = [];
        while ($cursor <= $this->to) {
            $periods[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify($step);
        }

        return $periods;
    }

    /** @return array{from: string, to: string, granularity: string, platforms: list<int>, page: ?int} */
    public function toArray(): array
    {
        return [
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
            'granularity' => $this->granularity,
            'platforms' => $this->platformIds,
            'page' => $this->pageId,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $errors
     */
    private static function dateParam(array $query, string $key, DateTimeImmutable $default, array &$errors): ?DateTimeImmutable
    {
        $value = $query[$key] ?? '';
        if ($value === '') {
            return $default;
        }
        $date = is_string($value) ? Validator::date($value) : null;
        if ($date === null) {
            $errors[$key] = 'Enter a valid date (YYYY-MM-DD).';
        }

        return $date;
    }
}
