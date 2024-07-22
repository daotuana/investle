<?php
function clearCache($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            clearCache($path);
            rmdir($path);
        } else {
            unlink($path);
        }
    }
    return true;
}

$cacheDir = __DIR__ . '/var/cache';
if (clearCache($cacheDir)) {
    echo "Cache cleared!";
} else {
    echo "Failed to clear cache.";
}
?>
