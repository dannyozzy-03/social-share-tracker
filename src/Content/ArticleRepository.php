<?php

declare(strict_types=1);

namespace App\Content;

final class ArticleRepository
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return array_map(static fn (array $article): array => $article + [
            // Absolute, as social previews require.
            'image' => self::coverUrl($article['slug']),
            // Relative, so <img> loads on whichever host the site is browsed on.
            'image_src' => asset('img/covers/' . $article['slug'] . '.jpg'),
        ], self::articles());
    }

    /** @return list<string> Section names in the order they first appear. */
    public static function sections(): array
    {
        return array_values(array_unique(array_column(self::articles(), 'category')));
    }

    public static function sectionUrl(string $section): string
    {
        return url('?section=' . rawurlencode(strtolower($section)));
    }

    public static function coverUrl(string $name): string
    {
        return app_url('assets/img/covers/' . $name . '.jpg');
    }

    /** @return list<array<string, mixed>> */
    private static function articles(): array
    {
        return [
            [
                'slug' => 'kopitiam-breakfast-guide',
                'caption' => 'Kopi and kaya toast, the classic kopitiam breakfast. Photo: decade_null / Flickr (CC BY 2.0)',
                'category' => 'Food',
                'set_piece' => 'kopitiam-cup',
                'title' => 'Seven kopitiam breakfasts worth waking up early for',
                'summary' => 'From half-boiled eggs to charcoal-toasted bread, a slow morning crawl through the classics that still draw queues before 8am.',
                'author' => 'Aina Rahman',
                'published' => '2026-10-20',
                'body' => [
                    'There is a particular sound to a busy kopitiam at seven in the morning: the clink of spoons against thick ceramic cups, the hiss of the coffee sock being wrung out, and an uncle calling orders in three languages at once.',
                    'The menu rarely changes, and that is the point. Kaya toast is still grilled over charcoal until the edges blacken slightly, then filled with cold slabs of butter that melt only at the centre. Half-boiled eggs arrive in a saucer, ready for a dash of soy sauce and white pepper.',
                    'Regulars will tell you the coffee matters most. Beans roasted with margarine and sugar give kopi its dark, almost smoky flavour. Order it kosong for no sugar, peng for iced, or simply point at what the person next to you is drinking.',
                    'If you go, go early. The best tables, under the ceiling fans, are taken by eight, and the most popular items sell out by mid-morning. Bring cash, share a table, and do not rush.',
                ],
            ],
            [
                'slug' => 'easy-hiking-trails-near-pj',
                'caption' => 'A shaded trail at Bukit Kiara Park. Photo: Slleong / Wikimedia Commons (CC0)',
                'category' => 'Lifestyle',
                'set_piece' => 'rainforest-tree',
                'title' => 'Five easy hiking trails within an hour of Petaling Jaya',
                'summary' => 'Short climbs, shaded paths and city views: weekend walks that suit beginners, families and anyone who wants fresh air before the heat sets in.',
                'author' => 'Daniel Wong',
                'published' => '2026-09-26',
                'body' => [
                    'You do not need to drive for hours to find a proper trail. The Klang Valley is ringed by forest reserves and hills that make for rewarding half-day walks.',
                    'Bukit Gasing is the obvious starting point for PJ residents: a network of shaded paths, a suspension bridge and a small temple near the summit. Most loops take under two hours.',
                    'Further north, the canopy walkway area at FRIM offers wide, well-marked routes through some of the oldest managed forest in the country. Arrive early on weekends, when parking fills up fast.',
                    'Whichever trail you choose, carry more water than you think you need, wear shoes with grip for the muddy sections after rain, and start before 8am to beat both the crowds and the midday sun.',
                ],
            ],
            [
                'slug' => 'lower-electricity-bill',
                'caption' => 'A split-type air conditioner, usually the biggest item on the bill. Photo: Curpharar / Wikimedia Commons (CC BY-SA 4.0)',
                'category' => 'Money',
                'set_piece' => 'standing-fan',
                'title' => 'Small habits that cut your monthly electricity bill',
                'summary' => 'Air-conditioning settings, fridge placement and the appliances quietly drawing power on standby — practical changes that add up.',
                'author' => 'Priya Nair',
                'published' => '2026-09-03',
                'body' => [
                    'In a hot, humid climate, air-conditioning is usually the single biggest item on a household electricity bill. The good news is that it is also where small changes make the largest difference.',
                    'Setting the thermostat to 24 or 25 degrees instead of 18 can noticeably reduce consumption, especially when paired with a ceiling fan to move air around the room. Cleaning the filters every few weeks keeps the unit from working harder than it needs to.',
                    'The fridge is the other appliance that never switches off. Leave a gap behind it for air to circulate, keep it away from the stove and direct sunlight, and check that the door seals close properly.',
                    'Finally, look for devices that stay on standby: set-top boxes, game consoles and chargers left plugged in. A switched power strip makes it easy to turn a whole corner of the living room off at night.',
                ],
            ],
            [
                'slug' => 'penang-hawker-first-timer',
                'caption' => 'The evening crowd at Gurney Drive hawker centre. Photo: suran2007 / Flickr (CC BY 2.0)',
                'category' => 'Travel',
                'set_piece' => 'hawker-stall',
                'title' => "A first-timer's guide to Penang's hawker centres",
                'summary' => 'How ordering works, what to try first and the unwritten rules of claiming a table at the island’s busiest open-air food courts.',
                'author' => 'Aina Rahman',
                'published' => '2026-08-12',
                'body' => [
                    'Penang’s hawker centres can be overwhelming on a first visit: dozens of stalls, handwritten menus and a crowd that seems to know exactly what it is doing. A little preparation goes a long way.',
                    'First, find a table and note its number. You order at each stall separately, tell them your table number, and the food is brought to you. Drinks are usually sold by a separate vendor who will come to your table.',
                    'Start with the classics. Char kway teow fried over high heat, assam laksa with its sour fish broth, and a plate of oyster omelette to share. Finish with cendol or ais kacang if you still have room.',
                    'Pay when the food arrives, keep small notes handy, and do not worry about making mistakes. Stall owners are used to visitors, and pointing at what looks good is a perfectly acceptable way to order.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $article) {
            if ($article['slug'] === $slug) {
                return $article;
            }
        }

        return null;
    }

    public static function url(array $article): string
    {
        return app_url('article.php?slug=' . rawurlencode($article['slug']));
    }

    public static function readingMinutes(array $article): int
    {
        $words = str_word_count(implode(' ', $article['body']));

        return max(1, (int) round($words / 200));
    }
}
