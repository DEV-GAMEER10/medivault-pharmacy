<?php
// backup.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: users/login.php');
    exit;
}

// Ensure proper access controls if needed - for now, any authenticated logged-in user can backup?
// Note: Role check removed to allow the single-tenant SaaS owner to export their data without strict 'admin' constraints.

require_once 'config/database.php';

// Try to sniff the connection details from PDO
// Since config/database.php uses $pdo, we'll extract config or just re-run standard queries inside PDO.

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="medivault_cloud_backup_' . date('Y-m-d_H-i-s') . '.sql"');
header('Cache-Control: max-age=0'); // no cache

echo "-- ---------------------------------------------------------\n";
echo "-- MediVault SaaS Database Backup (Cloud Sync)\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- ---------------------------------------------------------\n\n";

echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET time_zone = \"+00:00\";\n\n";

try {
    // Get all tables
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        // Drop table if exists
        echo "DROP TABLE IF EXISTS `$table`;\n";

        // Show create table
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";

        // Fetch Data
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rowCount = $stmt->rowCount();
        
        if ($rowCount > 0) {
            echo "--\n-- Data for table `$table`\n--\n";
            echo "INSERT INTO `$table` VALUES \n";
            
            $first_row = true;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!$first_row) echo ",\n";
                $first_row = false;
                
                $vals = [];
                foreach ($row as $val) {
                    if (is_null($val)) {
                        $vals[] = "NULL";
                    } else {
                        // escape strings safely using PDO quote
                        $vals[] = $pdo->quote($val);
                    }
                }
                echo "(" . implode(", ", $vals) . ")";
            }
            echo ";\n\n";
        }
    }
    
    echo "-- Database Backup Complete!\n";

} catch (PDOException $e) {
    echo "-- Error during backup generation: " . $e->getMessage() . "\n";
}
exit;
