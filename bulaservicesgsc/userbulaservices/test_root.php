<?php
echo 'DOCROOT = ' . realpath(__DIR__) . PHP_EOL;
echo 'Exists? ' . (file_exists(__DIR__ . '/uploads/announcements/2025/10') ? 'yes' : 'no');
