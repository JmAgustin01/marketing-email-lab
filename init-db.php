<?php
require __DIR__ . '/config.php';
$tools = new Lab\LocalDatabaseTools((new Lab\Database())->connect());
$count = $tools->initialize(__DIR__ . '/schema.sql');
echo "Executed $count statement(s) from schema.sql.\n";
