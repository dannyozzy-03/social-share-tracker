<?php

declare(strict_types=1);

use App\Stats\StatsFilter;
use App\Validation\ValidationException;

$today = new DateTimeImmutable('2026-10-22 15:30:00');
$known = [1, 2, 3, 4, 5];

return [
    'defaults to the last 30 days, by day, all platforms' => function () use ($today, $known): void {
        $filter = StatsFilter::fromQuery([], $known, $today);
        check_same('2026-09-23', $filter->from->format('Y-m-d'));
        check_same('2026-10-22', $filter->to->format('Y-m-d'));
        check_same('day', $filter->granularity);
        check_same([], $filter->platformIds);
        check_same(null, $filter->pageId);
        check_same(30, $filter->days());
        check_same(30, count($filter->periods()));
    },
    'accepts platforms as an array or a comma list' => function () use ($today, $known): void {
        check_same([2, 5], StatsFilter::fromQuery(['platforms' => ['5', '2', '5']], $known, $today)->platformIds);
        check_same([1, 3], StatsFilter::fromQuery(['platforms' => '3,1'], $known, $today)->platformIds);
    },
    'collects every validation error at once' => function () use ($today, $known): void {
        $e = check_throws(ValidationException::class, fn () => StatsFilter::fromQuery([
            'from' => '2026-13-01',
            'granularity' => 'hour',
            'platforms' => ['1', '99'],
            'page' => 'abc',
        ], $known, $today));
        check_same(['from', 'granularity', 'platforms', 'page'], array_keys($e->errors()));
    },
    'rejects reversed and oversized ranges' => function () use ($today, $known): void {
        check_throws(ValidationException::class, fn () => StatsFilter::fromQuery(['from' => '2026-10-10', 'to' => '2026-10-01'], $known, $today));
        check_throws(ValidationException::class, fn () => StatsFilter::fromQuery(['from' => '2023-01-01', 'to' => '2026-10-01'], $known, $today));
    },
    'rejects non-scalar input without crashing' => function () use ($today, $known): void {
        check_throws(ValidationException::class, fn () => StatsFilter::fromQuery(['from' => ['x'], 'page' => ['1']], $known, $today));
        check_throws(ValidationException::class, fn () => StatsFilter::fromQuery(['platforms' => [['1']]], $known, $today));
    },
    'builds Monday-based week buckets' => function () use ($known): void {
        // 2026-10-01 is a Thursday, so the first bucket starts on Monday 28 September.
        $filter = StatsFilter::fromQuery(['from' => '2026-10-01', 'to' => '2026-10-22', 'granularity' => 'week'], $known);
        check_same(['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'], $filter->periods());
    },
    'builds month buckets' => function () use ($known): void {
        $filter = StatsFilter::fromQuery(['from' => '2026-07-25', 'to' => '2026-10-22', 'granularity' => 'month'], $known);
        check_same(['2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01'], $filter->periods());
    },
    'computes the comparison period' => function () use ($known): void {
        $previous = StatsFilter::fromQuery(['from' => '2026-10-16', 'to' => '2026-10-22'], $known)->previousPeriod();
        check_same('2026-10-09', $previous->from->format('Y-m-d'));
        check_same('2026-10-15', $previous->to->format('Y-m-d'));
    },
];
