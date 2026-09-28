<?php
$db = new PDO('sqlite:' . __DIR__ . '/database/database.sqlite');
foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name") as $r) {
    echo $r['name'] . PHP_EOL;
}
