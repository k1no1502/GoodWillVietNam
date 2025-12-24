<?php
$file = "donate-to-campaign.php";
$text = file_get_contents($file);
if ($text === false) {
    fwrite(STDERR, "Cannot read file\n");
    exit(1);
}
$pattern1 = '/<span class="badge bg-warning text-dark">\s*<\?php echo \$item\[''remaining''\]; \?> <\?php echo \$item\[''unit''\]; \?>\s*<\/span>/';
$replacement1 = <<<'HTML'
<?php $remaining = (int)$item['remaining']; ?>
<span class="badge <?php echo $remaining > 0 ? 'bg-warning text-dark' : 'bg-success'; ?>">
    <?php if ($remaining > 0): ?>
        <?php echo htmlspecialchars($remaining . ' ' . $item['unit']); ?>
    <?php else: ?>
        &#272;&#227; &#273;&#7911; quy&#234;n g&#243;p
    <?php endif; ?>
</span>
HTML;
$text = preg_replace($pattern1, $replacement1, $text, 1, $count1);
if ($count1 === 0) {
    fwrite(STDERR, "pattern1 not replaced\n");
    exit(1);
}
$pattern2 = '/<\?php foreach \(\$items as \$item\): \?>\s*<option value="<\?php echo \$item\[''item_id''\]; \"\s*data-name="<\?php echo htmlspecialchars\(\$item\[''item_name''\]\); \"\s*data-category="<\?php echo \$item\[''category_id''\]; \"\s*data-unit="<\?php echo \$item\[''unit''\]; \">\s*<\?php echo htmlspecialchars\(\$item\[''item_name''\]\); \>\s*\(C.*?<\?php endforeach; \?>/s';
$replacement2 = <<<'HTML'
<?php foreach ($items as $item): ?>
    <?php $selectRemaining = (int)$item['remaining']; ?>
    <option value="<?php echo $item['item_id']; ?>" 
            data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
            data-category="<?php echo $item['category_id']; ?>"
            data-unit="<?php echo $item['unit']; ?>">
        <?php echo htmlspecialchars($item['item_name']); ?> 
        (
        <?php if ($selectRemaining > 0): ?>
            C&#7847;n: <?php echo htmlspecialchars($selectRemaining . ' ' . $item['unit']); ?>
        <?php else: ?>
            &#272;&#227; &#273;&#7911; quy&#234;n g&#243;p
        <?php endif; ?>
        )
    </option>
<?php endforeach; ?>
HTML;
$text = preg_replace($pattern2, $replacement2, $text, 1, $count2);
if ($count2 === 0) {
    fwrite(STDERR, "pattern2 not replaced\n");
    exit(1);
}
file_put_contents($file, $text);
?>
