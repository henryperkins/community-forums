<?php
declare(strict_types=1);
$root='/home/ubuntu/community-forums';
require $root.'/vendor/autoload.php';
App\Core\Env::load($root.'/.env');
$c=App\Core\Config::fromFile($root.'/config/config.php');
if ($c->get('db.database') !== 'retroboards_e2e_inbox_harden_20261008') { throw new RuntimeException('Private DB guard'); }
$d=new App\Core\Database($c->get('db'));
$u=(new App\Repository\UserRepository($d))->findByUsername('alice');
(new App\Repository\UserRepository($d))->setOnboarded((int)$u['id'],true);
$r=new App\Repository\ThreadUserRepository($d);
foreach ($d->fetchAll('SELECT id,last_post_id FROM threads') as $t) {
$r->markRead((int)$u['id'],(int)$t['id'],(int)$t['last_post_id']);
$r->setStar((int)$u['id'],(int)$t['id'],(int)$t['id'] <= 4);
$r->setSnooze((int)$u['id'],(int)$t['id'],null);
}
$mode=$argv[1] ?? 'single';
if ($mode === 'drain') {
 $visible=$r->inbox((int)$u['id'],'for_you','active',false,App\Repository\ThreadUserRepository::NO_CUTOVER,100,0,true,true);
 foreach (array_slice($visible,0,21) as $row) { $r->markUnread((int)$u['id'],(int)$row['id']); }
 echo json_encode(['database'=>$c->get('db.database'),'unreadCount'=>count(array_slice($visible,0,21))]);
 exit;
}
$t=$d->fetch("SELECT id FROM threads WHERE slug='mobile-layout-looks-great'");
$r->markUnread((int)$u['id'],(int)$t['id']);
$d->run('UPDATE threads SET title=:title WHERE id=:id',['title'=>'مرحبا '.str_repeat('界你好🪴אב',15).' end','id'=>(int)$t['id']]);
echo json_encode(['database'=>$c->get('db.database'),'singleUnreadId'=>(int)$t['id']]);
