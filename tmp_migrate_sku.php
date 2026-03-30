<?php
require 'c:/xampp/htdocs/my_store/config/database.php';
try {
    $pdo->exec('ALTER TABLE medicines ADD SKU VARCHAR(50) UNIQUE NULL AFTER ItemID');
} catch (Exception $e) { echo "Modify Error mostly already exists: " . $e->getMessage() . "\n"; }

$stmt = $pdo->query('SELECT ItemID, ItemName, BatchNumber FROM medicines WHERE SKU IS NULL');
while ($row = $stmt->fetch()) {
    $medcode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $row['ItemName']), 0, 4));
    if (empty($medcode)) $medcode = 'MEDX';
    preg_match('/\d+/', $row['ItemName'], $matches);
    $strength = $matches[0] ?? '000';
    $sku = $medcode . '-' . $strength . '-' . strtoupper($row['BatchNumber']);
    
    $check = $pdo->prepare('SELECT COUNT(*) FROM medicines WHERE SKU = ?');
    $check->execute([$sku]);
    if ($check->fetchColumn() > 0) {
        $sku .= '-' . $row['ItemID'];
    }
    
    $upd = $pdo->prepare('UPDATE medicines SET SKU = ? WHERE ItemID = ?');
    $upd->execute([$sku, $row['ItemID']]);
}
echo "SKU Migration Done\n";
