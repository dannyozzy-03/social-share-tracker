<?php

declare(strict_types=1);

namespace App\Support;

use App\Content\ArticleRepository;
use App\Repository\PageRepository;
use App\Exception\DatabaseException;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

final class DemoSeeder
{
    /** Host of the archive pages; ".example" is reserved for examples (RFC 2606), so it can never be a real site. */
    public const ARCHIVE_HOST = 'archive.example';
    public const ARCHIVE_MAX = 50000;

    private const ARCHIVE_FORMATS = [
        "A first-timer's guide to %s in %s",
        'Where to find the best %s in %s',
        'Five things to know about %s in %s',
        'How locals enjoy %s in %s',
        'A slow weekend of %s in %s',
        'Budget tips for %s in %s',
        'The quiet side of %s in %s',
        'What changed for %s in %s this year',
        'Seven photos of %s in %s',
        "A local's map of %s in %s",
        'Rain or shine: %s in %s',
    ];
    private const ARCHIVE_SUBJECTS = [
        'street food', 'nasi lemak', 'char kway teow', 'kopitiam breakfasts', 'night markets', 'hawker centres',
        'heritage walks', 'park trails', 'rainy-day plans', 'family outings', 'home energy savings',
        'public transport', 'weekend markets', 'cycling routes', 'seafood', 'coffee',
    ];
    private const ARCHIVE_PLACES = [
        'Penang', 'Ipoh', 'Melaka', 'Kuching', 'Kota Kinabalu', 'Johor Bahru', 'Kuala Lumpur',
        'Petaling Jaya', 'Langkawi', 'Taiping', 'Kuala Terengganu', 'Cameron Highlands',
    ];
    private const PLATFORM_WEIGHTS = ['whatsapp' => 36, 'facebook' => 27, 'telegram' => 14, 'x' => 12, 'email' => 8];
    private const OTHER_PLATFORM_WEIGHT = 4;
    private const HOUR_WEIGHTS = [1, 1, 1, 1, 1, 2, 4, 7, 8, 6, 5, 6, 8, 7, 5, 5, 5, 6, 7, 8, 9, 8, 5, 2];
    private const USER_AGENTS = [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
    ];

    public function __construct(private PDO $db)
    {
    }

    /**
     * Deletes all clicks and pages, then generates new demo data, in one transaction:
     * if generation fails, the previous data is left untouched.
     * @throws DatabaseException
     */
    public function replace(int $days = 90, int $seed = 2026): int
    {
        return $this->transaction('replace the demo data', function () use ($days, $seed): int {
            $this->deleteAll();

            return $this->generate($days, $seed);
        });
    }

    /** @throws DatabaseException */
    public function run(int $days = 90, int $seed = 2026): int
    {
        return $this->transaction('generate the demo data', fn (): int => $this->generate($days, $seed));
    }

    /** @throws DatabaseException */
    public function clear(): void
    {
        $this->transaction('clear the share data', function (): int {
            $this->deleteAll();

            return 0;
        });
    }

    /**
     * Adds $count extra shared pages (a demo "archive") so the dashboard can be tried with a big
     * site: the page search, the paged Page breakdown table and the charts. As on a real news
     * site, a few pages are shared a lot and most only once or twice, over the last 90 days.
     *
     * Existing data is kept. The pages live under ARCHIVE_HOST, so running this again replaces
     * the archive, and removeArchive() takes it out without touching anything else.
     *
     * @return array{pages: int, clicks: int}
     * @throws DatabaseException
     */
    public function addArchive(int $count, int $seed = 2026): array
    {
        $count = max(1, min(self::ARCHIVE_MAX, $count));
        $clicks = $this->transaction('add the demo archive', function () use ($count, $seed): int {
            $this->deleteArchive();

            return $this->generateArchive($count, $seed);
        });

        return ['pages' => $count, 'clicks' => $clicks];
    }

    /**
     * Deletes the pages added by addArchive(); their clicks go with them (ON DELETE CASCADE).
     * @return int pages removed
     * @throws DatabaseException
     */
    public function removeArchive(): int
    {
        return $this->transaction('remove the demo archive', fn (): int => $this->deleteArchive());
    }

    private function deleteArchive(): int
    {
        $stmt = $this->db->prepare('DELETE FROM pages WHERE url LIKE ?');
        $stmt->execute(['https://' . self::ARCHIVE_HOST . '/%']);

        return $stmt->rowCount();
    }

    /** @return int clicks inserted */
    private function generateArchive(int $count, int $seed): int
    {
        mt_srand($seed);
        $weights = $this->platformWeights();

        // Every format × subject × place is a different headline; shuffled so neighbours differ.
        $headlines = [];
        foreach (self::ARCHIVE_FORMATS as $format) {
            foreach (self::ARCHIVE_SUBJECTS as $subject) {
                foreach (self::ARCHIVE_PLACES as $place) {
                    $headlines[] = sprintf($format, $subject, $place);
                }
            }
        }
        shuffle($headlines);

        // Long tail: the page ranked r gets about 40 / r^0.8 shares, at least 1. Ranks are
        // shuffled so popularity has nothing to do with the order the pages were added in.
        $ranks = range(1, $count);
        shuffle($ranks);

        $now = new DateTimeImmutable();
        $insertPage = $this->db->prepare('INSERT INTO pages (url, url_hash, title) VALUES (?, ?, ?)');
        $rows = [];
        $inserted = 0;
        for ($i = 0; $i < $count; $i++) {
            $headline = $headlines[$i % count($headlines)];
            $round = intdiv($i, count($headlines));
            $title = $round === 0 ? $headline : sprintf('%s (part %d)', $headline, $round + 1);
            $published = $now->setTime(mt_rand(6, 20), mt_rand(0, 59))->modify(sprintf('-%d days', mt_rand(1, 89)));
            $url = (string) Url::normalize(sprintf(
                'https://%s/%s/%s-%d',
                self::ARCHIVE_HOST,
                $published->format('Y/m'),
                trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-'),
                $i + 1
            ));
            $insertPage->execute([$url, PageRepository::hash($url), $title]);
            $pageId = (int) $this->db->lastInsertId();

            if ($weights === []) {
                continue;
            }
            $shares = max(1, (int) round(40 / $ranks[$i] ** 0.8 * (0.7 + mt_rand() / mt_getrandmax() * 0.6)));
            $span = max(60, $now->getTimestamp() - $published->getTimestamp());
            for ($k = 0; $k < $shares; $k++) {
                // Squaring the random fraction puts most shares soon after publishing, a few later.
                $at = $published->modify(sprintf('+%d seconds', (int) ($span * (mt_rand() / mt_getrandmax()) ** 2)));
                array_push(
                    $rows,
                    self::weightedPick($weights),
                    $pageId,
                    ip_hash('203.0.113.' . mt_rand(1, 254)),
                    self::USER_AGENTS[mt_rand(0, count(self::USER_AGENTS) - 1)],
                    min($at, $now)->format('Y-m-d H:i:s')
                );
                if (count($rows) >= 500 * 5) {
                    $inserted += $this->insert($rows);
                    $rows = [];
                }
            }
        }

        return $inserted + $this->insert($rows);
    }

    /**
     * @param callable(): int $work
     * @throws DatabaseException
     */
    private function transaction(string $operation, callable $work): int
    {
        try {
            $this->db->beginTransaction();
            $result = $work();
            $this->db->commit();

            return $result;
        } catch (Throwable $e) {
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (PDOException) {
                // The connection is gone; the server discards the transaction. Report the original error.
            }
            // Only driver errors become DatabaseException; anything else (a bug) is rethrown as-is.
            throw $e instanceof PDOException ? DatabaseException::during($operation, $e) : $e;
        }
    }

    private function deleteAll(): void
    {
        $this->db->exec('DELETE FROM share_clicks');
        $this->db->exec('DELETE FROM pages');
    }

    /** @return array<int, int> active platform ID => how often it is picked for a demo share */
    private function platformWeights(): array
    {
        $platforms = $this->db->query('SELECT slug, id FROM platforms WHERE is_active = 1')->fetchAll(PDO::FETCH_KEY_PAIR);
        $weights = [];
        foreach ($platforms as $slug => $id) {
            $weights[(int) $id] = self::PLATFORM_WEIGHTS[$slug] ?? self::OTHER_PLATFORM_WEIGHT;
        }

        return $weights;
    }

    private function generate(int $days, int $seed): int
    {
        mt_srand($seed);
        $pages = new PageRepository($this->db);
        $weights = $this->platformWeights();
        if ($weights === []) {
            return 0;
        }

        $now = new DateTimeImmutable();
        $windowStart = $now->setTime(0, 0)->modify(sprintf('-%d days', $days - 1));

        $targets = [[
            'url' => app_url(),
            'title' => config('app.site_name') . ' — Latest stories',
            'published' => $windowStart,
            'base' => 3.0,
            'decay' => false,
        ]];
        foreach (ArticleRepository::all() as $i => $article) {
            $targets[] = [
                'url' => ArticleRepository::url($article),
                'title' => $article['title'],
                'published' => new DateTimeImmutable($article['published']),
                'base' => [22, 30, 14, 18][$i % 4],
                'decay' => true,
            ];
        }

        $viralDay = $now->setTime(0, 0)->modify('-' . min(24, $days - 1) . ' days');
        $rows = [];
        $inserted = 0;

        foreach ($targets as $index => $target) {
            $pageId = $pages->findOrCreate((string) Url::normalize($target['url']), $target['title']);

            for ($day = $windowStart; $day <= $now; $day = $day->modify('+1 day')) {
                if ($day < $target['published']->setTime(0, 0)) {
                    continue;
                }
                $age = (int) $target['published']->diff($day)->days;
                $expected = $target['base'] * ($target['decay'] ? 0.12 + 1.4 * exp(-$age / 6) : 1.0);
                $expected *= (int) $day->format('N') >= 6 ? 1.3 : 1.0;
                if ($index === 2 && abs((int) $viralDay->diff($day)->days) <= 1) {
                    $expected *= 4; // the hiking article briefly goes viral
                }

                $clicks = (int) round($expected * (0.6 + mt_rand() / mt_getrandmax() * 0.8));
                for ($i = 0; $i < $clicks; $i++) {
                    $at = $day->setTime(self::weightedPick(self::HOUR_WEIGHTS), mt_rand(0, 59), mt_rand(0, 59));
                    if ($at > $now) {
                        continue;
                    }
                    array_push(
                        $rows,
                        self::weightedPick($weights),
                        $pageId,
                        ip_hash('203.0.113.' . mt_rand(1, 254)),
                        self::USER_AGENTS[mt_rand(0, count(self::USER_AGENTS) - 1)],
                        $at->format('Y-m-d H:i:s')
                    );
                    if (count($rows) >= 500 * 5) {
                        $inserted += $this->insert($rows);
                        $rows = [];
                    }
                }
            }
        }

        return $inserted + $this->insert($rows);
    }

    /** @param list<mixed> $values flat, five values per row */
    private function insert(array $values): int
    {
        $count = intdiv(count($values), 5);
        if ($count === 0) {
            return 0;
        }
        $this->db->prepare(
            'INSERT INTO share_clicks (platform_id, page_id, ip_hash, user_agent, created_at) VALUES '
            . implode(', ', array_fill(0, $count, '(?, ?, ?, ?, ?)'))
        )->execute($values);

        return $count;
    }

    /** @param array<int, int> $weights key => weight */
    private static function weightedPick(array $weights): int
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
