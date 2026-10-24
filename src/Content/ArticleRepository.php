<?php

declare(strict_types=1);

namespace App\Content;

final class ArticleRepository
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return array_map(static function (array $article): array {
            $cover = $article['cover'] ?? $article['slug'] . '.jpg';
            $coverFile = preg_match('/\.(?:jpg|svg)$/i', $cover) === 1 ? $cover : $cover . '.jpg';

            return $article + [
                // Absolute, as social previews require.
                'image' => self::coverUrl($cover),
                // Relative, so <img> loads on whichever host the site is browsed on.
                'image_src' => asset('img/covers/' . $coverFile),
            ];
        }, self::articles());
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
        $file = preg_match('/\.(?:jpg|svg)$/i', $name) === 1 ? $name : $name . '.jpg';

        return app_url('assets/img/covers/' . $file);
    }

    /** @return list<array<string, mixed>> */
    private static function articles(): array
    {
        return [
            [
                'slug' => 'morning-market-breakfasts',
                'cover' => 'morning-market-breakfasts',
                'caption' => 'A neighbourhood market stall at Cheng San Market and Cooked Food Centre.',
                'photo_credit' => [
                    'author' => 'Kbseah',
                    'source' => 'https://commons.wikimedia.org/wiki/File:Cheng_San_Market_and_Cooked_Food_Centre,_market_stall.jpg',
                    'license' => 'CC BY-SA 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                ],
                'category' => 'Food',
                'set_piece' => 'kopitiam-cup',
                'title' => 'A relaxed guide to breakfast at the neighbourhood market',
                'summary' => 'A few simple ways to find the busy stalls, order local favourites and make the most of an early market visit.',
                'author' => 'Aina Rahman',
                'published' => '2026-10-24',
                'body' => [
                    'A neighbourhood market is at its best before the day gets hot. The first hour brings a lively mix of shoppers, stallholders and breakfast regulars.',
                    'Look for the stalls with a steady queue, then take a moment to see how people order. Some vendors serve at the counter, while others will bring food to numbered tables.',
                    'If you are unsure what to try, start with familiar favourites such as kaya toast, soft-boiled eggs or a bowl of noodles. Ask about spice and portion size before ordering.',
                    'Bring small notes, keep the walkway clear and take your time. A relaxed breakfast is a good way to discover the everyday rhythm of a new neighbourhood.',
                    'Arriving early gives you more time to browse before the crowd builds. Walk through once to see what is available, then choose a stall that looks appealing rather than ordering in a hurry.',
                    'Market routines vary from place to place. Some cafés take payment at the counter, while other stalls collect after serving, so watch for signs or ask a nearby staff member if you are unsure.',
                    'A little consideration makes the visit easier for everyone: return trays where requested, leave space for people carrying groceries and bring your own reusable bag for anything you buy to take home.',
                ],
            ],
            [
                'slug' => 'market-noodle-breakfasts',
                'cover' => 'market-noodle-breakfasts',
                'caption' => 'Mee soto served at Kitchen by Open House, Kuala Lumpur International Airport.',
                'photo_credit' => [
                    'author' => 'Bahnfrend',
                    'source' => 'https://commons.wikimedia.org/wiki/File:Noodle_soup,_Kitchen_by_Open_House,_KLIA1,_2025_(01).jpg',
                    'license' => 'CC BY-SA 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                ],
                'category' => 'Food',
                'set_piece' => 'kopitiam-cup',
                'title' => 'A comforting bowl of noodles to start the day',
                'summary' => 'From a light broth to springy noodles and fresh herbs, a warm breakfast can be simple, satisfying and easy to find.',
                'author' => 'Aina Rahman',
                'published' => '2026-10-22',
                'body' => [
                    'A bowl of noodles makes an easy start to a cool morning. At many neighbourhood coffee shops, the breakfast menu is short, familiar and served quickly.',
                    'Look for a broth that suits your appetite, then add greens, tofu or an egg if they are available. Chilli is often served separately, so you can season the bowl to taste.',
                    'If it is your first visit, ask the stallholder which dishes are mild and whether the noodles contain ingredients you avoid. A simple question can make ordering much easier.',
                    'Find a seat, let the bowl cool for a moment and enjoy breakfast at your own pace. The best morning meal is the one that leaves you ready for the day.',
                    'The broth is a good place to start when deciding what kind of bowl you want. Clear soups tend to feel lighter, while richer gravies and coconut-based options can make a more filling meal.',
                    'Noodles soften as they sit, so have a seat ready before ordering if the stall is busy. Keep condiments nearby and add them gradually; chilli, sambal and pepper can quickly change the flavour.',
                    'If you are sharing, order a smaller bowl or a side dish to try more than one flavour. Ask about allergens and preparation directly, since recipes and ingredients can differ between stalls.',
                ],
            ],
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
                    'A soft-boiled egg is often served with soy sauce and white pepper, making it an easy savoury counterpoint to sweet kaya. If you prefer a firmer texture, ask whether the kitchen can cook it longer.',
                    'Many kopitiams have their own ordering shorthand. You can keep it simple by saying what you would like and whether you want it hot or iced; the staff can help with the local terms.',
                    'Menus and recipes vary, so treat the classics as a starting point rather than a fixed checklist. Try one favourite, notice what regulars are ordering and come back another morning for something new.',
                ],
            ],
            [
                'slug' => 'weekend-walks-after-the-rain',
                'cover' => 'weekend-walks-after-the-rain',
                'caption' => 'A rainforest hiking trail in Taman Negara, Malaysia.',
                'photo_credit' => [
                    'author' => 'Chamberlain of Nilai',
                    'source' => 'https://commons.wikimedia.org/wiki/File:Rainforest_hiking_trail_of_Taman_Negara.jpg',
                    'license' => 'CC BY-SA 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                ],
                'category' => 'Lifestyle',
                'set_piece' => 'rainforest-tree',
                'title' => 'How to plan a comfortable weekend walk after rain',
                'summary' => 'Choose an easy route, check the weather and pack a few essentials for a refreshing walk on a damp morning.',
                'author' => 'Daniel Wong',
                'published' => '2026-10-15',
                'body' => [
                    'A little rain does not have to cancel a weekend walk, but wet paths call for a slower pace and a bit of preparation.',
                    'Check local park notices before leaving and choose a familiar route with clear paths. Avoid steep slopes and streams if there has been heavy rain.',
                    'Shoes with good grip, a light rain layer and a small towel are useful additions to your usual water bottle. Keep your phone in a dry pouch and let someone know your route.',
                    'Give yourself extra time, turn back if conditions worsen and leave the trail as you found it. The walk should feel restorative, not like a race against the weather.',
                    'Before setting off, check the latest forecast as well as any notices from the park or local authority. A short shower can leave roots and boardwalks slippery even after the rain has stopped.',
                    'Pack water, a charged phone and a small first-aid kit, and choose a route that matches the least experienced person in your group. Avoid walking alone on unfamiliar trails in poor visibility.',
                    'After the walk, check yourself and your clothing for ticks or leeches, and clean muddy shoes before storing them. Taking a few minutes to dry your gear helps it stay comfortable for the next outing.',
                ],
            ],
            [
                'slug' => 'packing-for-a-city-park-walk',
                'cover' => 'packing-for-a-city-park-walk',
                'caption' => 'A walking trail in Bukit Nanas Forest Reserve, Kuala Lumpur.',
                'photo_credit' => [
                    'author' => 'McKay Savage',
                    'source' => 'https://commons.wikimedia.org/wiki/File:Malaysia_-_056_-_KL_-_mini_jungle_walk_in_Bukit_Nanas_Forest_Reserve_(3528859318).jpg',
                    'license' => 'CC BY 2.0',
                    'license_url' => 'https://creativecommons.org/licenses/by/2.0/',
                ],
                'category' => 'Lifestyle',
                'set_piece' => 'rainforest-tree',
                'title' => 'What to pack for an easy walk in the city',
                'summary' => 'A small water bottle, comfortable shoes and a little planning make it easier to enjoy a nearby park at your own pace.',
                'author' => 'Daniel Wong',
                'published' => '2026-10-10',
                'body' => [
                    'A short walk close to home is easier to enjoy when you pack light and plan for the weather.',
                    'Choose comfortable shoes with a secure fit, bring drinking water and keep a light rain layer in your bag during wet months.',
                    'Check the park’s opening hours and path conditions before setting out. If you are walking with children or friends, agree on a meeting point in case you get separated.',
                    'Leave room in your bag for anything you bring in, stay on marked paths and take breaks whenever you need them. A simple outing does not need complicated gear.',
                    'A small day bag is usually enough for a short outing. Add sun protection, tissues and a portable power bank only if you expect to be out for longer; carrying less makes an easy walk feel easier.',
                    'Choose a route with a clear start and finish, and let someone know roughly when you expect to return. If the weather turns, a nearby exit or covered rest stop can make it simple to change plans.',
                    'Keep snacks sealed and take all rubbish with you. Parks are shared spaces, and staying on marked paths helps protect plants while making it easier for other visitors to find their way.',
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
                    'For a gentler outing, look for signed loops in local parks and forest reserves, and confirm current access rules before travelling. Facilities, opening times and trail conditions can change, particularly after heavy rain.',
                    'Check the distance and elevation rather than relying only on a trail name or online photograph. A short route with a steep climb can be more demanding than a longer, flatter walk.',
                    'Go with a companion when possible, keep your phone charged and carry enough water for the time you plan to be outside. Turn back if the trail becomes unsafe; reaching a viewpoint is never worth taking a risk.',
                ],
            ],
            [
                'slug' => 'simple-home-energy-check',
                'cover' => 'simple-home-energy-check',
                'caption' => 'A Siemens household electromechanical electricity meter.',
                'photo_credit' => [
                    'author' => 'Robbie Ian Morrison',
                    'source' => 'https://commons.wikimedia.org/wiki/File:Siemens_household_electricity_meter_electromechanical_induction_type.jpg',
                    'license' => 'CC BY 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
                ],
                'category' => 'Money',
                'set_piece' => 'standing-fan',
                'title' => 'A simple room-by-room check for home energy use',
                'summary' => 'Small maintenance and habit checks can help you understand where electricity goes before changing your routine.',
                'author' => 'Priya Nair',
                'published' => '2026-09-18',
                'body' => [
                    'Before trying to cut your electricity use, spend a few minutes noticing which appliances run most often and when they are used.',
                    'Check that air-conditioner filters are clean and that vents are not blocked by curtains or furniture. Follow the manufacturer’s guidance for maintenance and temperature settings.',
                    'Look around the kitchen too. Keep refrigerator doors closed between trips, allow air to circulate around the appliance and avoid placing hot food directly inside.',
                    'A short monthly check of your meter or bill can help you see whether a change made a difference. Choose comfortable, safe settings and consult a qualified technician if an appliance needs repair.',
                    'In the living room, notice which lights and devices are used every day and whether they need to stay on when nobody is there. Switching off what is not needed is a simple first step that costs nothing.',
                    'Compare bills over similar periods, keeping in mind that weather, household routines and billing estimates can affect the total. A short note of any changes you make can help explain a later increase or decrease.',
                    'Do not open electrical equipment or attempt repairs yourself. If a plug, cable or appliance looks damaged, stop using it and contact a qualified professional or the relevant utility for advice.',
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
                    'Start by checking your bill for the billing period and actual meter readings, then compare it with a similar month. This gives you a more useful picture than comparing months with very different routines or weather.',
                    'Try one change at a time, such as switching off unused lights or adjusting appliance schedules. Small, consistent habits are easier to maintain than a long list of changes made all at once.',
                    'Never compromise on ventilation, food safety or a comfortable temperature to lower a bill. If usage rises unexpectedly, check for changes in household routines and contact your electricity provider about the bill before replacing equipment.',
                ],
            ],
            [
                'slug' => 'keeping-cool-at-home',
                'cover' => 'keeping-cool-at-home',
                'caption' => 'A ceiling fan in a guest room at the Bancroft Hotel, Berkeley.',
                'photo_credit' => [
                    'author' => 'Sharon Hahn Darlin',
                    'source' => 'https://commons.wikimedia.org/wiki/File:Bancroft_Hotel,_Berkeley,_California_-_Room_with_ceiling_fan.jpg',
                    'license' => 'CC BY 2.0',
                    'license_url' => 'https://creativecommons.org/licenses/by/2.0/',
                ],
                'category' => 'Money',
                'set_piece' => 'standing-fan',
                'title' => 'Small ways to keep a room comfortable in warm weather',
                'summary' => 'Thoughtful use of shade, airflow and cooling can make a room more comfortable without changing everything at once.',
                'author' => 'Priya Nair',
                'published' => '2026-08-28',
                'body' => [
                    'Warm afternoons can make a home feel stuffy, but a few simple checks can improve comfort from room to room.',
                    'Close curtains or blinds where direct sun comes in, and keep windows clear so air can move when outdoor conditions are comfortable.',
                    'Use fans according to their instructions and keep air-conditioner filters clean. Small maintenance steps help appliances work as intended.',
                    'Try one change at a time and compare how the room feels. If cooling equipment is making unusual noises or not working properly, ask a qualified technician to check it.',
                    'During the hottest part of the day, close blinds on sun-facing windows to reduce direct heat. When outdoor air is cooler and conditions are suitable, opening windows can help refresh a room.',
                    'A fan can make people in a room feel cooler by moving air, but it does not lower the room temperature. Switch it off when the room is empty and follow the manufacturer’s safety guidance.',
                    'Pay attention to how the room feels rather than chasing a particular setting. If anyone at home feels unwell in the heat, move to a cooler place and seek medical help when needed.',
                ],
            ],
            [
                'slug' => 'first-evening-at-a-hawker-centre',
                'cover' => 'first-evening-at-a-hawker-centre',
                'caption' => 'A Char Koay Kak hawker stall on Jalan Chulia, George Town, Penang.',
                'photo_credit' => [
                    'author' => 'Khairil Yusof',
                    'source' => 'https://commons.wikimedia.org/wiki/File:Char_Koay_Kak,_Hawker_Stall,_Georgetown,_Penang.jpg',
                    'license' => 'CC BY-SA 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                ],
                'category' => 'Travel',
                'set_piece' => 'hawker-stall',
                'title' => 'What to expect on your first evening at a hawker centre',
                'summary' => 'Find a seat, explore the stalls and enjoy a casual first visit to one of Penang’s busy open-air food spots.',
                'author' => 'Aina Rahman',
                'published' => '2026-08-20',
                'body' => [
                    'The first visit to a busy hawker centre can feel like a lot to take in: bright signs, sizzling woks and people moving in every direction.',
                    'Start by finding an open table and checking whether it has a number. At many centres you can order from different stalls and tell each vendor where you are sitting.',
                    'Pick one or two dishes to begin with, then explore more once you have settled in. If you have dietary needs, ask the stall directly about ingredients and preparation.',
                    'Keep your belongings close, clear your table when you are finished and follow the local payment customs. There is no need to rush; the variety is part of the fun.',
                    'Take a slow lap before choosing what to eat. Menus, prices and opening hours differ between centres, and seeing the options first can help you avoid ordering more than you can finish.',
                    'Some places use table numbers while others expect you to collect your food. Watch how orders are handled or ask a staff member; a quick question is easier than trying to guess in a busy crowd.',
                    'Carry a small amount of cash in case a stall does not accept digital payment, and keep your valuables secure. If the centre is crowded, agree on a meeting point before your group splits up to order.',
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
                    'A practical first visit starts with a quick look around. Check the stall names, prices and queues, then pick one dish that sounds familiar and one you have not tried before.',
                    'If you have food allergies or dietary restrictions, speak directly with the stallholder before placing an order. Shared cooking surfaces and sauces can make it difficult to guarantee that a dish is suitable.',
                    'Bring water, pace your orders and share smaller portions if you want to sample several stalls. A busy food centre is more enjoyable when you leave room to pause and take in the atmosphere.',
                ],
            ],
            [
                'slug' => 'penang-evening-food-walk',
                'cover' => 'penang-evening-food-walk',
                'caption' => 'George Town skyline at night.',
                'photo_credit' => [
                    'author' => 'HundenvonPenang',
                    'source' => 'https://commons.wikimedia.org/wiki/File:George_Town,_Penang_at_night.jpg',
                    'license' => 'CC BY-SA 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                ],
                'category' => 'Travel',
                'set_piece' => 'hawker-stall',
                'title' => 'A relaxed evening food walk through Penang',
                'summary' => 'Take in the lights, local flavours and lively atmosphere on an easy-going evening stroll around Penang.',
                'author' => 'Aina Rahman',
                'published' => '2026-08-05',
                'body' => [
                    'As evening settles over Penang, food streets and hawker centres begin to glow with signs, lanterns and the warmth of busy kitchens.',
                    'Start with a short walk to get your bearings. Notice which stalls are serving the dishes you enjoy, and check menus before joining a queue.',
                    'Try one dish at a time and leave room to discover something new. If you have allergies or dietary restrictions, speak directly with the vendor before ordering.',
                    'Take breaks, bring a little cash and keep walkways clear. The best food walk is an unhurried one, with time to enjoy the atmosphere between stops.',
                    'Plan a loose route rather than a strict checklist. Pick a starting point, check how far apart the food stops are and leave time to rest if the evening is warm or the streets are busy.',
                    'Look for clear menus and ask about portion sizes before ordering. Sharing a few smaller dishes lets everyone taste more, while reducing the chance of leaving food unfinished.',
                    'Use pedestrian crossings, keep an eye on traffic and carry a light layer in case the weather changes. Finish the walk at a convenient transport stop so the return journey is as relaxed as the meal.',
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
