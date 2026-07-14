<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\CustomEmojiService;
use App\Service\PostingService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use App\Support\MentionLinker;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$admin = $users->findByUsername('admin');
$alice = $users->findByUsername('alice');
$general = $db->fetch("SELECT id FROM boards WHERE slug = 'general' LIMIT 1");
if ($admin === null || $alice === null || $general === null) {
    throw new RuntimeException('Run tests/browser/prepare.sh before the rich-content fixture.');
}

$writeGate = new WriteGate();
$customEmoji = new CustomEmojiService($db, $writeGate);
$customEmoji->create($users->findEntity((int) $admin['id']), [
    'shortcode' => 'render_spark',
    'name' => 'Rendering sparkle',
    'image_path' => '/emoji/rich-content.png',
    'mime' => 'image/png',
    'allow_reactions' => '1',
]);

$markdown = new Markdown(
    new HtmlSanitizer(allowGiphyImages: true),
    $customEmoji,
    new MentionLinker($users, true),
);
$posting = new PostingService(
    $db,
    new ThreadRepository($db),
    new PostRepository($db),
    new BoardRepository($db),
    $users,
    $markdown,
    $writeGate,
    new BoardPolicy(),
    $config,
);

$title = 'Rich content rendering contract';
$body = <<<'MARKDOWN'
## Rendering contract

This paragraph combines **bold**, *italic*, ~~strikethrough~~, `inline code`, a [safe link](https://example.com), a mention for @bob, and an inline custom emoji :render_spark:. ThisUnbrokenTokenIsIntentionallyVeryLongToProveThatAuthoredContentWrapsWithoutWideningThePageShellBeyondItsViewport.

### Ordered from four

4. Preserve the authored start value.
5. Keep later items in sequence.

- [x] Verified server rendering
- [ ] Verified narrow layout

> A blockquote should remain distinct, readable, and contained beside the rest of the discussion.

```php
<?php
echo "semantic code language";
```

| Left aligned | Center aligned | Right aligned | Compatibility target | Operational note |
| :--- | :---: | ---: | :--- | :--- |
| Alpha | Middle | 42 | Desktop-and-mobile-rendering-contract | Wide tables scroll inside their own labelled region |
| Beta | Center | 9000 | Keyboard-focusable-overflow-container | The page itself must never gain horizontal overflow |

![Wide rendering evidence](https://media4.giphy.com/media/rich-content/giphy.gif)

||Spoiler text remains available through the canonical delimiter.||
MARKDOWN;

$existing = $db->fetch(
    'SELECT id FROM threads WHERE board_id = ? AND title = ? AND is_deleted = 0 ORDER BY id ASC LIMIT 1',
    [(int) $general['id'], $title],
);
if ($existing === null) {
    $result = $posting->createThread($users->findEntity((int) $alice['id']), [
        'board_id' => (int) $general['id'],
        'title' => $title,
        'body' => $body,
    ]);
    $threadId = (int) $result['thread_id'];
} else {
    $threadId = (int) $existing['id'];
    $db->run(
        'UPDATE posts SET body = ?, body_html = ? WHERE thread_id = ? AND is_op = 1',
        [$body, $markdown->render($body, ['link_mentions' => true]), $threadId],
    );
}

fwrite(STDOUT, (string) $threadId . PHP_EOL);
