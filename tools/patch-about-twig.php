<?php

$path = __DIR__ . '/../templates/components/cv/admin/_about_customization.html.twig';
$content = file_get_contents($path);
$start = strpos($content, '{# REMOVE_OLD_DOTS_START');
$end = strpos($content, '{# REMOVE_OLD_DOTS_END #}');
if ($start === false || $end === false) {
    fwrite(STDERR, "markers not found\n");
    exit(1);
}
$end = strpos($content, "\n", $end) + 1;
$content = substr($content, 0, $start) . substr($content, $end);
file_put_contents($path, $content);
echo "patched\n";
