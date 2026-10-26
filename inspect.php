<?php
require __DIR__ . '/config.php';
(new Lab\LocalDatabaseTools((new Lab\Database())->connect()))->display();
