<?php

$file = __DIR__.'/../vendor/pestphp/pest-plugin-browser/src/Playwright/Locator.php';

if (! file_exists($file)) {
    echo "Locator.php not found — skipping patch.\n";

    exit(0);
}

$content = file_get_contents($file);

$old = "\$this->sendMessage('click', \$options ?? [])";
$new = "\$this->sendMessage('click', array_merge(['force' => true], \$options ?? []))";

if (str_contains($content, $new)) {
    echo "Locator::click() already patched.\n";

    exit(0);
}

$content = str_replace($old, $new, $content);

file_put_contents($file, $content);

echo "Patched Locator::click() with force: true\n";
