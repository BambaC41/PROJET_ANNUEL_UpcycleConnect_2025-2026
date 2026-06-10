<?php
// Script to fix the problematic line with curly quote
$file = 'particulier.php';
$content = file_get_contents($file);

// The problematic line contains a RIGHT SINGLE QUOTATION MARK (U+2019)
// We need to replace it with the correct PHP code

// Read line by line
$lines = explode("\n", $content);
$newLines = [];
$skip_next = 0;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    
    // Look for the line containing "onclick" and "Annulation d" and "inscription"
    // This line should be around line 936
    if (strpos($line, 'onclick="toast(') !== false && 
        strpos($line, 'Annulation d') !== false && 
        strpos($line, 'inscription') !== false &&
        strpos($line, 'Annuler') !== false) {
        // Skip this line and replace with the correct form
        $newLines[] = '                                    <td>';
        $newLines[] = '                                        <?php if (($ins[\'statut\'] ?? \'\') !== \'annulee\'): ?>';
        $newLines[] = '                                            <form method="POST" style="display:inline;">';
        $newLines[] = '                                                <input type="hidden" name="cancel_inscription_id" value="<?= e($ins[\'id_inscription\'] ?? \'\') ?>">';
        $newLines[] = '                                                <button class="btn btn-danger btn-sm" type="submit">Annuler</button>';
        $newLines[] = '                                            </form>';
        $newLines[] = '                                        <?php else: ?>';
        $newLines[] = '                                            <span class="pill pill-gray">Annulée</span>';
        $newLines[] = '                                        <?php endif; ?>';
        $newLines[] = '                                    </td>';
    } else {
        $newLines[] = $line;
    }
}

$newContent = implode("\n", $newLines);
file_put_contents($file, $newContent);
echo "Fixed particulier.php!\n";
?>
