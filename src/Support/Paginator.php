<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Page arithmetic for any list shown a page at a time (front-page stories, dashboard table).
 * Pure: it only counts, so it never throws.
 */
final class Paginator
{
    /** Largest page number accepted from a request; anything bigger is treated as invalid. */
    public const MAX_PAGE = 100000;

    public readonly int $total;
    public readonly int $perPage;
    public readonly int $page;

    /** $page is clamped into 1..pages(), so an out-of-date link still lands on a real page. */
    public function __construct(int $total, int $perPage, int $page = 1)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->page = max(1, min($page, $this->pages()));
    }

    /**
     * Reads a page number from request input: null/'' means page 1; anything that is not a
     * whole number from 1 to MAX_PAGE returns null so the caller can reject it.
     */
    public static function parsePage(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return 1;
        }
        if (!is_string($raw) || !ctype_digit($raw) || strlen($raw) > 6) {
            return null;
        }
        $page = (int) $raw;

        return $page >= 1 && $page <= self::MAX_PAGE ? $page : null;
    }

    /** Number of pages; an empty list still has one (empty) page. */
    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages();
    }

    /** 1-based position of the first and last item on this page, e.g. [11, 20]; [0, 0] when empty. */
    public function range(): array
    {
        if ($this->total === 0) {
            return [0, 0];
        }

        return [$this->offset() + 1, min($this->total, $this->offset() + $this->perPage)];
    }

    /**
     * Page numbers to show as links: always the first and last page, plus $around pages either
     * side of the current one; null marks a gap ("…"). E.g. 1 … 4 5 6 … 20.
     * @return list<int|null>
     */
    public function links(int $around = 1): array
    {
        $last = $this->pages();
        $around = max(0, $around);
        $shown = range(max(1, $this->page - $around), min($last, $this->page + $around));
        $shown = array_unique([1, ...$shown, $last]);
        sort($shown);

        $links = [];
        $previous = 0;
        foreach ($shown as $n) {
            if ($n - $previous === 2) {
                $links[] = $n - 1; // a gap of one page: show the page instead of "…"
            } elseif ($n - $previous > 2) {
                $links[] = null;
            }
            $links[] = $n;
            $previous = $n;
        }

        return $links;
    }

    /** @return array{page: int, per_page: int, total: int, pages: int} */
    public function toArray(): array
    {
        return ['page' => $this->page, 'per_page' => $this->perPage, 'total' => $this->total, 'pages' => $this->pages()];
    }
}
