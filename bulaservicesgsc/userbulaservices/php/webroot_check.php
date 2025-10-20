<?php
echo "<h2>Document Root Test</h2>";
echo "<p><strong>\$_SERVER['DOCUMENT_ROOT']:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>__DIR__ (current file path):</strong> " . __DIR__ . "</p>";
echo "<p><strong>Full realpath():</strong> " . realpath(__DIR__) . "</p>";
echo "<hr>";
echo "<p><em>Delete this file after checking for security.</em></p>";
?>
