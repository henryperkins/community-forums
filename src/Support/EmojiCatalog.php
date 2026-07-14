<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bounded, first-party Unicode emoji catalogue for composer suggestions.
 *
 * The compact source rows are expanded into the public row contract by all();
 * no copy of this data is shipped to the browser. Primary shortcodes are kept
 * unique and stable because ComposerSuggestion IDs derive from source order.
 */
final class EmojiCatalog
{
    /** @var array<string,list<string>> */
    private const DATA = [
        'Smileys & emotion' => [
            '😄|smile,smiley|Smiling face with open mouth|happy,joy',
            '😀|grinning|Grinning face|happy,smile',
            '😃|smile_big|Grinning face with big eyes|happy,joy',
            '😁|grin|Beaming face|happy,teeth',
            '😆|laughing,satisfied|Laughing face|happy,laugh',
            '😅|sweat_smile|Grinning face with sweat|relief,nervous',
            '😂|joy|Face with tears of joy|laugh,cry',
            '🤣|rofl|Rolling on the floor laughing|laugh,funny',
            '😊|blush|Smiling face with smiling eyes|happy,warm',
            '😇|innocent|Smiling face with halo|angel,good',
            '🙂|slightly_smiling|Slightly smiling face|smile,calm',
            '🙃|upside_down|Upside down face|silly,irony',
            '😉|wink|Winking face|playful,joke',
            '😌|relieved|Relieved face|calm,peaceful',
            '😍|heart_eyes|Smiling face with heart eyes|love,crush',
            '🥰|smiling_hearts|Smiling face with hearts|love,affection',
            '😘|kissing_heart|Face blowing a kiss|love,kiss',
            '😋|yum|Face savoring food|delicious,tasty',
            '😛|stuck_out_tongue|Face with tongue|silly,playful',
            '😜|stuck_out_tongue_winking|Winking face with tongue|silly,joke',
            '🤪|zany|Zany face|wild,silly',
            '🤨|raised_eyebrow|Face with raised eyebrow|skeptical,doubt',
            '🧐|monocle|Face with monocle|inspect,curious',
            '🤓|nerd|Nerd face|geek,smart',
            '😎|sunglasses|Smiling face with sunglasses|cool,summer',
            '🥳|partying_face,party_face|Partying face|celebrate,birthday',
            '😏|smirk|Smirking face|confident,sly',
            '😒|unamused|Unamused face|annoyed,bored',
            '😞|disappointed|Disappointed face|sad,down',
            '😢|cry|Crying face|sad,tear',
            '😭|sob|Loudly crying face|sad,tears',
            '😡|rage|Enraged face|angry,mad',
        ],
        'People & body' => [
            '👍|+1,thumbsup,thumbs_up|Thumbs up|approve,yes,like',
            '👎|-1,thumbsdown,thumbs_down|Thumbs down|disapprove,no,dislike',
            '👋|wave|Waving hand|hello,goodbye',
            '👏|clap|Clapping hands|applause,congrats',
            '🙌|raised_hands|Raising hands|celebrate,hooray',
            '👐|open_hands|Open hands|welcome,hug',
            '🤲|palms_up|Palms up together|receive,offer',
            '🤝|handshake|Handshake|agreement,deal',
            '🙏|pray|Folded hands|thanks,please,hope',
            '✍️|writing_hand|Writing hand|write,sign',
            '💪|muscle|Flexed biceps|strong,power',
            '🦾|mechanical_arm|Mechanical arm|robot,accessibility',
            '🦿|mechanical_leg|Mechanical leg|robot,accessibility',
            '🦵|leg|Leg|kick,limb',
            '🦶|foot|Foot|step,walk',
            '👂|ear|Ear|listen,hear',
            '👃|nose|Nose|smell,face',
            '👀|eyes|Eyes|look,watch',
            '👁️|eye|Eye|see,watch',
            '🧠|brain|Brain|think,mind',
            '🫀|anatomical_heart|Anatomical heart|health,organ',
            '🫁|lungs|Lungs|breathe,health',
            '🦷|tooth|Tooth|dentist,smile',
            '🦴|bone|Bone|skeleton,body',
            '👶|baby|Baby|child,newborn',
            '🧒|child|Child|young,kid',
            '👦|boy|Boy|child,person',
            '👧|girl|Girl|child,person',
            '🧑|adult|Adult|person,grownup',
            '👨|man|Man|person,adult',
            '👩|woman|Woman|person,adult',
            '🧓|older_adult|Older adult|elder,senior',
        ],
        'Animals & nature' => [
            '🐶|dog|Dog face|pet,puppy',
            '🐱|cat|Cat face|pet,kitten',
            '🐭|mouse|Mouse face|animal,rodent',
            '🐹|hamster|Hamster|pet,rodent',
            '🐰|rabbit|Rabbit face|bunny,pet',
            '🦊|fox|Fox|animal,clever',
            '🐻|bear|Bear|animal,wild',
            '🐼|panda|Panda|animal,bamboo',
            '🐨|koala|Koala|animal,australia',
            '🐯|tiger|Tiger face|animal,cat',
            '🦁|lion|Lion|animal,cat',
            '🐮|cow|Cow face|animal,farm',
            '🐷|pig|Pig face|animal,farm',
            '🐸|frog|Frog|animal,amphibian',
            '🐵|monkey_face|Monkey face|animal,primate',
            '🙈|see_no_evil|See no evil monkey|monkey,hide',
            '🙉|hear_no_evil|Hear no evil monkey|monkey,listen',
            '🙊|speak_no_evil|Speak no evil monkey|monkey,quiet',
            '🐔|chicken|Chicken|bird,farm',
            '🐧|penguin|Penguin|bird,cold',
            '🐦|bird|Bird|animal,fly',
            '🐤|baby_chick|Baby chick|bird,young',
            '🦄|unicorn|Unicorn|fantasy,magic',
            '🐝|bee|Honeybee|insect,honey',
            '🦋|butterfly|Butterfly|insect,nature',
            '🐌|snail|Snail|animal,slow',
            '🐞|lady_beetle|Lady beetle|insect,ladybug',
            '🌸|cherry_blossom|Cherry blossom|flower,spring',
            '🌹|rose|Rose|flower,love',
            '🌻|sunflower|Sunflower|flower,summer',
            '🌲|evergreen_tree|Evergreen tree|forest,nature',
            '🍀|four_leaf_clover|Four leaf clover|luck,nature',
        ],
        'Food & drink' => [
            '🍏|green_apple|Green apple|fruit,food',
            '🍎|apple|Red apple|fruit,food',
            '🍐|pear|Pear|fruit,food',
            '🍊|tangerine|Tangerine|orange,fruit',
            '🍋|lemon|Lemon|fruit,citrus',
            '🍌|banana|Banana|fruit,food',
            '🍉|watermelon|Watermelon|fruit,summer',
            '🍇|grapes|Grapes|fruit,wine',
            '🍓|strawberry|Strawberry|fruit,berry',
            '🫐|blueberries|Blueberries|fruit,berry',
            '🍈|melon|Melon|fruit,food',
            '🍒|cherries|Cherries|fruit,food',
            '🍑|peach|Peach|fruit,food',
            '🥭|mango|Mango|fruit,tropical',
            '🍍|pineapple|Pineapple|fruit,tropical',
            '🥥|coconut|Coconut|fruit,tropical',
            '🥝|kiwi|Kiwi fruit|fruit,food',
            '🍅|tomato|Tomato|vegetable,food',
            '🥑|avocado|Avocado|fruit,food',
            '🍆|eggplant|Eggplant|vegetable,food',
            '🥔|potato|Potato|vegetable,food',
            '🥕|carrot|Carrot|vegetable,food',
            '🌽|corn|Ear of corn|vegetable,food',
            '🌶️|hot_pepper|Hot pepper|spicy,food',
            '🥒|cucumber|Cucumber|vegetable,food',
            '🥬|leafy_green|Leafy green|vegetable,salad',
            '🥦|broccoli|Broccoli|vegetable,food',
            '🧄|garlic|Garlic|vegetable,seasoning',
            '🍞|bread|Bread|food,loaf',
            '🧀|cheese|Cheese wedge|food,dairy',
            '🍕|pizza|Pizza|food,slice',
            '☕|coffee|Hot beverage|drink,cafe',
        ],
        'Activities' => [
            '⚽|soccer|Soccer ball|sport,football',
            '🏀|basketball|Basketball|sport,hoop',
            '🏈|football|American football|sport,ball',
            '⚾|baseball|Baseball|sport,ball',
            '🥎|softball|Softball|sport,ball',
            '🎾|tennis|Tennis|sport,racket',
            '🏐|volleyball|Volleyball|sport,ball',
            '🏉|rugby|Rugby football|sport,ball',
            '🥏|flying_disc|Flying disc|sport,frisbee',
            '🎱|pool_8_ball|Pool eight ball|game,billiards',
            '🎉|tada,party|Party popper|celebrate,confetti',
            '🏓|ping_pong|Ping pong|sport,table_tennis',
            '🏸|badminton|Badminton|sport,racket',
            '🥅|goal_net|Goal net|sport,score',
            '🏒|ice_hockey|Ice hockey|sport,puck',
            '🥍|lacrosse|Lacrosse|sport,stick',
            '🏏|cricket|Cricket game|sport,bat',
            '⛳|golf|Flag in hole|sport,course',
            '🏹|bow_and_arrow|Bow and arrow|sport,archery',
            '🎣|fishing_pole|Fishing pole|sport,fish',
            '🤿|diving_mask|Diving mask|sport,swim',
            '🥊|boxing_glove|Boxing glove|sport,fight',
            '🥋|martial_arts|Martial arts uniform|sport,karate',
            '🎽|running_shirt|Running shirt|sport,race',
            '🛹|skateboard|Skateboard|sport,ride',
            '🛼|roller_skate|Roller skate|sport,ride',
            '🛷|sled|Sled|sport,winter',
            '⛸️|ice_skate|Ice skate|sport,winter',
            '🎿|ski|Skis|sport,winter',
            '🏂|snowboarder|Snowboarder|sport,winter',
            '🪂|parachute|Parachute|sport,skydiving',
            '🎯|dart|Direct hit|game,target',
        ],
        'Travel & places' => [
            '🚗|car|Automobile|vehicle,drive',
            '🚕|taxi|Taxi|vehicle,cab',
            '🚙|suv|Sport utility vehicle|vehicle,drive',
            '🚌|bus|Bus|vehicle,transit',
            '🚎|trolleybus|Trolleybus|vehicle,transit',
            '🏎️|racing_car|Racing car|vehicle,fast',
            '🚓|police_car|Police car|vehicle,emergency',
            '🚑|ambulance|Ambulance|vehicle,emergency',
            '🚒|fire_engine|Fire engine|vehicle,emergency',
            '🚐|minibus|Minibus|vehicle,transit',
            '🛻|pickup_truck|Pickup truck|vehicle,drive',
            '🚚|truck|Delivery truck|vehicle,shipping',
            '🚛|articulated_lorry|Articulated lorry|vehicle,shipping',
            '🚜|tractor|Tractor|vehicle,farm',
            '🛵|motor_scooter|Motor scooter|vehicle,ride',
            '🏍️|motorcycle|Motorcycle|vehicle,ride',
            '🚲|bike|Bicycle|vehicle,cycle',
            '🛴|kick_scooter|Kick scooter|vehicle,ride',
            '🚨|rotating_light|Police car light|emergency,siren',
            '🚔|oncoming_police_car|Oncoming police car|vehicle,emergency',
            '🚍|oncoming_bus|Oncoming bus|vehicle,transit',
            '🚘|oncoming_car|Oncoming automobile|vehicle,drive',
            '🚖|oncoming_taxi|Oncoming taxi|vehicle,cab',
            '✈️|airplane|Airplane|travel,flight',
            '🚀|rocket|Rocket|space,launch',
            '🛸|flying_saucer|Flying saucer|space,ufo',
            '🚁|helicopter|Helicopter|flight,vehicle',
            '⛵|sailboat|Sailboat|boat,travel',
            '🚤|speedboat|Speedboat|boat,travel',
            '🚢|ship|Ship|boat,travel',
            '🗺️|world_map|World map|travel,geography',
            '🗽|statue_of_liberty|Statue of Liberty|place,new_york',
        ],
        'Objects' => [
            '⌚|watch|Watch|time,clock',
            '📱|iphone,mobile_phone|Mobile phone|device,telephone',
            '💻|computer|Laptop|device,work',
            '⌨️|keyboard|Keyboard|device,type',
            '🖥️|desktop_computer|Desktop computer|device,monitor',
            '🖨️|printer|Printer|device,paper',
            '🖱️|mouse_computer|Computer mouse|device,pointer',
            '🕹️|joystick|Joystick|game,controller',
            '💽|minidisc|Computer disk|storage,media',
            '💾|floppy_disk|Floppy disk|save,storage',
            '💿|cd|Optical disk|music,media',
            '📀|dvd|DVD|movie,media',
            '📷|camera|Camera|photo,device',
            '📸|camera_flash|Camera with flash|photo,device',
            '📹|video_camera|Video camera|record,device',
            '🎥|movie_camera|Movie camera|film,cinema',
            '📞|telephone_receiver|Telephone receiver|call,phone',
            '☎️|phone|Telephone|call,device',
            '📺|tv|Television|screen,video',
            '📻|radio|Radio|audio,broadcast',
            '🎙️|studio_microphone|Studio microphone|audio,podcast',
            '🎚️|level_slider|Level slider|audio,control',
            '🎛️|control_knobs|Control knobs|audio,control',
            '⏱️|stopwatch|Stopwatch|time,timer',
            '⏰|alarm_clock|Alarm clock|time,wake',
            '🧭|compass|Compass|direction,navigate',
            '💡|bulb|Light bulb|idea,light',
            '🔦|flashlight|Flashlight|light,torch',
            '🕯️|candle|Candle|light,flame',
            '🧯|fire_extinguisher|Fire extinguisher|safety,fire',
            '🛒|shopping_cart|Shopping cart|store,buy',
            '🎁|gift|Wrapped gift|present,birthday',
        ],
        'Symbols' => [
            '❤️|heart|Red heart|love,like',
            '🧡|orange_heart|Orange heart|love,like',
            '💛|yellow_heart|Yellow heart|love,like',
            '💚|green_heart|Green heart|love,like',
            '💙|blue_heart|Blue heart|love,like',
            '💜|purple_heart|Purple heart|love,like',
            '🖤|black_heart|Black heart|love,dark',
            '🤍|white_heart|White heart|love,peace',
            '🤎|brown_heart|Brown heart|love,like',
            '💔|broken_heart|Broken heart|sad,love',
            '❣️|heavy_heart_exclamation|Heart exclamation|love,mark',
            '💕|two_hearts|Two hearts|love,affection',
            '💞|revolving_hearts|Revolving hearts|love,affection',
            '💓|heartbeat|Beating heart|love,pulse',
            '💗|heartpulse|Growing heart|love,affection',
            '💖|sparkling_heart|Sparkling heart|love,shine',
            '💘|cupid|Heart with arrow|love,valentine',
            '💝|gift_heart|Heart with ribbon|love,gift',
            '💟|heart_decoration|Heart decoration|love,symbol',
            '☮️|peace|Peace symbol|peace,sign',
            '✝️|latin_cross|Latin cross|religion,christian',
            '☪️|star_and_crescent|Star and crescent|religion,islam',
            '🕉️|om|Om|religion,hindu',
            '☸️|wheel_of_dharma|Wheel of dharma|religion,buddhist',
            '✡️|star_of_david|Star of David|religion,jewish',
            '🔯|six_pointed_star|Dotted six pointed star|symbol,star',
            '🕎|menorah|Menorah|religion,jewish',
            '☯️|yin_yang|Yin yang|balance,symbol',
            '☦️|orthodox_cross|Orthodox cross|religion,christian',
            '🛐|place_of_worship|Place of worship|religion,pray',
            '⛎|ophiuchus|Ophiuchus|zodiac,astrology',
            '♈|aries|Aries|zodiac,astrology',
        ],
        'Flags' => [
            '🇺🇸|flag_us|Flag United States|country,america',
            '🇨🇦|flag_ca|Flag Canada|country,canada',
            '🇲🇽|flag_mx|Flag Mexico|country,mexico',
            '🇧🇷|flag_br|Flag Brazil|country,brazil',
            '🇦🇷|flag_ar|Flag Argentina|country,argentina',
            '🇬🇧|flag_gb|Flag United Kingdom|country,britain',
            '🇮🇪|flag_ie|Flag Ireland|country,ireland',
            '🇫🇷|flag_fr|Flag France|country,france',
            '🇩🇪|flag_de|Flag Germany|country,germany',
            '🇪🇸|flag_es|Flag Spain|country,spain',
            '🇮🇹|flag_it|Flag Italy|country,italy',
            '🇵🇹|flag_pt|Flag Portugal|country,portugal',
            '🇳🇱|flag_nl|Flag Netherlands|country,netherlands',
            '🇧🇪|flag_be|Flag Belgium|country,belgium',
            '🇨🇭|flag_ch|Flag Switzerland|country,switzerland',
            '🇦🇹|flag_at|Flag Austria|country,austria',
            '🇸🇪|flag_se|Flag Sweden|country,sweden',
            '🇳🇴|flag_no|Flag Norway|country,norway',
            '🇩🇰|flag_dk|Flag Denmark|country,denmark',
            '🇫🇮|flag_fi|Flag Finland|country,finland',
            '🇵🇱|flag_pl|Flag Poland|country,poland',
            '🇺🇦|flag_ua|Flag Ukraine|country,ukraine',
            '🇬🇷|flag_gr|Flag Greece|country,greece',
            '🇹🇷|flag_tr|Flag Turkey|country,turkey',
            '🇮🇳|flag_in|Flag India|country,india',
            '🇨🇳|flag_cn|Flag China|country,china',
            '🇯🇵|flag_jp|Flag Japan|country,japan',
            '🇰🇷|flag_kr|Flag South Korea|country,korea',
            '🇦🇺|flag_au|Flag Australia|country,australia',
            '🇳🇿|flag_nz|Flag New Zealand|country,new_zealand',
            '🇿🇦|flag_za|Flag South Africa|country,south_africa',
            '🇳🇬|flag_ng|Flag Nigeria|country,nigeria',
        ],
    ];

    /**
     * @return list<array{emoji:string,name:string,shortcodes:list<string>,keywords:list<string>,category:string}>
     */
    public static function all(): array
    {
        static $rows = null;
        if ($rows !== null) {
            return $rows;
        }

        $rows = [];
        foreach (self::DATA as $category => $sourceRows) {
            foreach ($sourceRows as $source) {
                [$emoji, $shortcodeList, $name, $keywordList] = explode('|', $source, 4);
                $shortcodes = array_values(array_filter(explode(',', $shortcodeList)));
                $keywords = array_values(array_unique(array_filter(array_merge(
                    preg_split('/[^a-z0-9_+-]+/', mb_strtolower($name)) ?: [],
                    explode(',', $keywordList),
                ))));
                $rows[] = [
                    'emoji' => $emoji,
                    'name' => $name,
                    'shortcodes' => $shortcodes,
                    'keywords' => $keywords,
                    'category' => $category,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{emoji:string,name:string,shortcodes:list<string>,keywords:list<string>,category:string}>
     */
    public static function search(string $query): array
    {
        $query = mb_strtolower(trim($query));
        $query = trim($query, ':');
        if ($query === '') {
            return self::all();
        }

        $matches = [];
        foreach (self::all() as $row) {
            $shortcodes = array_map('mb_strtolower', $row['shortcodes']);
            $name = mb_strtolower($row['name']);
            $keywords = array_map('mb_strtolower', $row['keywords']);

            $score = 0;
            if (in_array($query, $shortcodes, true)) {
                $score = 500;
            } elseif (self::anyPrefix($shortcodes, $query)) {
                $score = 400;
            } elseif (str_starts_with($name, $query)) {
                $score = 300;
            } elseif (self::anyPrefix($keywords, $query)) {
                $score = 200;
            } elseif (str_contains(implode(' ', array_merge($shortcodes, [$name], $keywords)), $query)) {
                $score = 100;
            }

            if ($score > 0) {
                $matches[] = ['score' => $score, 'row' => $row];
            }
        }

        usort($matches, static function (array $a, array $b): int {
            $score = $b['score'] <=> $a['score'];
            if ($score !== 0) {
                return $score;
            }
            $name = strcasecmp($a['row']['name'], $b['row']['name']);
            return $name !== 0 ? $name : strcmp($a['row']['shortcodes'][0], $b['row']['shortcodes'][0]);
        });

        return array_values(array_map(static fn (array $match): array => $match['row'], $matches));
    }

    /** @param list<string> $values */
    private static function anyPrefix(array $values, string $query): bool
    {
        foreach ($values as $value) {
            if (str_starts_with($value, $query)) {
                return true;
            }
        }
        return false;
    }
}
