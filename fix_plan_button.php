<?php
// Read the particulier.php file
$filePath = __DIR__ . '/particulier.php';
$content = file_get_contents($filePath);

// Split by lines
$lines = explode("\n", $content);

// Find the problematic line (should be around line 936)
$problemLineIndex = -1;
for ($i = 0; $i < count($lines); $i++) {
    // Look for the line with button and onclick
    if (strpos($lines[$i], 'type="button"') !== false &&
        strpos($lines[$i], 'onclick') !== false &&
        strpos($lines[$i], 'Annulation d') !== false) {
        $problemLineIndex = $i;
        echo "Found problem line at index $i (line " . ($i + 1) . "): " . substr($lines[$i], 0, 80) . "\n";
        break;
    }
}

if ($problemLineIndex >= 0) {
    // Replace the problem line and the surrounding lines
    // Find the start of the <td> tag
    $tdStartIndex = $problemLineIndex;
    while ($tdStartIndex >= 0 && strpos($lines[$tdStartIndex], '<td>') === false) {
        $tdStartIndex--;
    }
    
    // Build the replacement lines
    $replacement = [
        '                                    <td>',
        '                                        <?php if (($ins[\'statut\'] ?? \'\') !== \'annulee\'): ?>',
        '                                            <form method="POST" style="display:inline;">',
        '                                                <input type="hidden" name="cancel_inscription_id" value="<?= e($ins[\'id_inscription\'] ?? \'\') ?>">',
        '                                                <button class="btn btn-danger btn-sm" type="submit">Annuler</button>',
        '                                            </form>',
        '                                        <?php else: ?>',
        '                                            <span class="pill pill-gray">Annulée</span>',
        '                                        <?php endif; ?>',
        '                                    </td>'
    ];
    
    // Remove the old lines and insert the new ones
    array_splice($lines, $tdStartIndex, $problemLineIndex - $tdStartIndex + 1, $replacement);
    
    // Write back to file
    $newContent = implode("\n", $lines);
    file_put_contents($filePath, $newContent);
    
    echo "SUCCESS: Fixed the problematic line!\n";
    echo "Replacement done. File updated.\n";
} else {
    echo "ERROR: Could not find the problematic line.\n";
    echo "This might mean the issue was already fixed or the file has changed.\n";
}
?>
