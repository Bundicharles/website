<?php
require __DIR__.'/api/config/database.php';
$db=getDB();
$r=$db->query("SELECT id, media_type, path, filename FROM media ORDER BY id DESC LIMIT 30")->fetchAll();
foreach($r as $x) echo $x['id'].' | '.$x['media_type'].' | '.$x['path'].' | '.$x['filename'].PHP_EOL;
echo "total: ".(int)$db->query('SELECT COUNT(*) FROM media')->fetchColumn().PHP_EOL;
