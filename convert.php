<?php
$path = 'donate-to-campaign.php';
$raw = file_get_contents($path);
if ($raw === false) {
    fwrite(STDERR, "Cannot read file\n");
    exit(1);
}
$decoded = utf8_decode($raw);
$converted = mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1');
file_put_contents($path, $converted);
?>
