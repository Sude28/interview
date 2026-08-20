<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$main = new Turkpin\InterviewTest\Main();
$main->run();
