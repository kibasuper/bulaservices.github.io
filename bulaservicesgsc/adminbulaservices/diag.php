<?php
header('Content-Type: text/plain');
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . PHP_EOL;
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? '(unknown)') . PHP_EOL;
echo "__FILE__: " . __FILE__ . PHP_EOL;
echo "__DIR__:  " . __DIR__ . PHP_EOL;

$phpDir = __DIR__ . '/php';
echo PHP_EOL . "Checking " . $phpDir . PHP_EOL;
if (is_dir($phpDir)) {
  echo "php/ exists." . PHP_EOL;
  $files = @scandir($phpDir) ?: [];
  echo "php/ contents:" . PHP_EOL;
  foreach ($files as $f) echo " - $f" . PHP_EOL;
} else {
  echo "php/ does NOT exist here." . PHP_EOL;
}
