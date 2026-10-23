<?php

declare(strict_types=1);

namespace App\Share;

use App\Repository\PageRepository;
use App\Repository\PlatformRepository;
use App\Repository\ShareClickRepository;
use App\Support\Url;
use App\Validation\ValidationException;
use App\Validation\Validator;
use DateTimeImmutable;

final class ShareTracker
{
    /** @param list<string> $allowedHosts */
    public function __construct(
        private PlatformRepository $platforms,
        private PageRepository $pages,
        private ShareClickRepository $clicks,
        private array $allowedHosts,
        private int $dedupeSeconds = 10,
        private int $maxPerMinute = 30,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{recorded: bool, total: int}
     * @throws ValidationException|RateLimitException
     */
    public function track(array $input, string $ipHash, ?string $userAgent, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $errors = [];

        $slug = is_string($input['platform'] ?? null) ? strtolower(trim($input['platform'])) : '';
        $platform = null;
        if (!Validator::slug($slug)) {
            $errors['platform'] = 'A valid platform is required.';
        } elseif (($platform = $this->platforms->findActiveBySlug($slug)) === null) {
            $errors['platform'] = 'Unknown or inactive platform.';
        }

        $url = is_string($input['url'] ?? null) ? Url::normalize($input['url']) : null;
        if ($url === null) {
            $errors['url'] = 'A valid http(s) page URL is required.';
        } elseif (!in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), $this->allowedHosts, true)) {
            $errors['url'] = 'Pages on this host are not tracked.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($this->clicks->countByVisitorSince($ipHash, $now->modify('-1 minute')) >= $this->maxPerMinute) {
            throw new RateLimitException();
        }

        $pageId = $this->pages->findOrCreate($url, Validator::cleanText($input['title'] ?? null, 255));
        $platformId = (int) $platform['id'];

        $isDuplicate = $this->dedupeSeconds > 0 && $this->clicks->existsSince(
            $platformId,
            $pageId,
            $ipHash,
            $now->modify(sprintf('-%d seconds', $this->dedupeSeconds))
        );
        if (!$isDuplicate) {
            $this->clicks->record($platformId, $pageId, $ipHash, Validator::cleanText($userAgent, 255), $now);
        }

        return ['recorded' => !$isDuplicate, 'total' => $this->clicks->countForPage($pageId)];
    }
}
