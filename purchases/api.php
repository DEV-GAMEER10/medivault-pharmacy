<?php
// purchases/api.php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN');
header('Content-Type: application/json');

// Get raw JSON body if receiving JSON
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
if(!$input && !empty($_POST)) $input = $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? '');

if ($action === 'get_purchases') {
    // Both Admin and Staff can view history
    try {
        $query = "SELECT p.*, s.Name as SupplierName, (SELECT COUNT(*) FROM purchase_items pi WHERE pi.PurchaseID = p.PurchaseID) as TotalItems 
                  FROM purchases p 
                  JOIN suppliers s ON p.SupplierID = s.SupplierID 
                  ORDER BY p.PurchaseDate DESC";
        $stmt = $pdo->query($query);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_purchase_items') {
    $purchase_id = $_GET['id'] ?? 0;
    try {
        $stmt = $pdo->prepare("SELECT * FROM purchase_items WHERE PurchaseID = ?");
        $stmt->execute([$purchase_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_ai_predictions') {
    try {
        // AI Predictive Reorder Logic
        // 1. Calculate 30-day trailing sales velocity
        $query = "SELECT 
                    m.ItemName, 
                    MAX(m.CostPrice) as CostPrice,
                    m.SeasonalityTag,
                    SUM(m.Quantity) as TotalCurrentStock,
                    (SELECT COALESCE(SUM(si.Quantity), 0) 
                     FROM sales_items si 
                     JOIN sales s ON si.SaleID = s.SaleID 
                     WHERE si.ItemName = m.ItemName 
                     AND s.SaleDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as Sold30Days
                  FROM medicines m
                  GROUP BY m.ItemName, m.SeasonalityTag
                  HAVING Sold30Days > 0 OR (SeasonalityTag IS NOT NULL AND SeasonalityTag != 'NONE')";
                  
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Define current seasonal keyword
        $month = (int) date('n');
        $currentSeason = 'NONE';
        if ($month >= 6 && $month <= 9) $currentSeason = 'MONSOON';
        if ($month >= 11 || $month <= 2) $currentSeason = 'WINTER';
        if ($month >= 3 && $month <= 5) $currentSeason = 'SUMMER';

        $predictions = [];
        foreach($results as $row) {
            $sold_30 = (float) $row['Sold30Days'];
            $current_stock = (float) $row['TotalCurrentStock'];
            $cost_price = (float) $row['CostPrice'];
            $tag = strtoupper($row['SeasonalityTag'] ?? 'NONE');
            $has_tag = ($tag !== 'NONE');
            $is_current_season = ($has_tag && $tag === $currentSeason);
            
            // If it's the current season, use 50% buffer. Otherwise 20% standard.
            $buffer_multiplier = $is_current_season ? 1.5 : 1.2;
            $predicted_demand_with_buffer = $sold_30 * $buffer_multiplier;
            
            // Demo/Proactive Logic: If a user has manually tagged a medicine for ANY season,
            // we assume they want to keep a healthy stock of at least 25 units.
            if ($has_tag && $predicted_demand_with_buffer < 25) {
                $predicted_demand_with_buffer = 25; 
            }

            if ($current_stock < $predicted_demand_with_buffer) {
                $suggested_order = ceil($predicted_demand_with_buffer - $current_stock);
                if ($suggested_order > 0) {
                    $predictions[] = [
                        'name' => $row['ItemName'],
                        'batch' => 'AI-' . date('mY'), 
                        'qty' => $suggested_order,
                        'price' => round($suggested_order * $cost_price, 2),
                        'expiry' => date('Y-m-d', strtotime('+1 year')),
                        'reason' => ($sold_30 == 0) 
                            ? "Market Anticipation ({$tag}): Stock baseline set to 25 units"
                            : (($buffer_multiplier > 1.2) 
                                ? "Seasonal Boost ({$tag}): {$sold_30}/mo + 50% buffer"
                                : "Velocity: {$sold_30}/mo + 20% buffer")
                    ];
                }
            }
        }
        
        echo json_encode([
            'status' => 'success', 
            'data' => $predictions,
            'message' => 'AI analyzed 30-day velocity vectors successfully.'
        ]);
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'AI Engine Error: ' . $e->getMessage()]);
    }
    exit;
}

// Below requires ADMIN
if (!$isAdmin) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied. Admins only.']);
    exit;
}

if ($action === 'save_purchase') {
    try {
        $supplier_id = $input['supplier_id'];
        $items = $input['items'];
        $user_id = $_SESSION['user_id'];
        
        if (empty($items)) {
            throw new Exception("Cart is empty.");
        }

        $total_amount = array_sum(array_column($items, 'price'));

        $pdo->beginTransaction();

        // 1. Insert Purchase Record
        $stmt_purch = $pdo->prepare("INSERT INTO purchases (SupplierID, TotalAmount, CreatedBy) VALUES (?, ?, ?)");
        $stmt_purch->execute([$supplier_id, $total_amount, $user_id]);
        $purchase_id = $pdo->lastInsertId();

        // 2. Insert Items & Sync Inventory
        $stmt_item = $pdo->prepare("INSERT INTO purchase_items (PurchaseID, MedicineName, BatchNumber, Quantity, PurchasePrice, ExpiryDate) VALUES (?, ?, ?, ?, ?, ?)");
        
        // Match inventory (MedicineName + Batch)
        $stmt_check_inv = $pdo->prepare("SELECT ItemID FROM medicines WHERE LOWER(ItemName) = LOWER(?) AND LOWER(BatchNumber) = LOWER(?)");
        // Update Inventory (Quantity & PurchasePrice mappings)
        $stmt_update_inv = $pdo->prepare("UPDATE medicines SET Quantity = Quantity + ?, CostPrice = ?, SupplierID = ? WHERE ItemID = ?");
        // Insert new Inventory
        $stmt_insert_inv = $pdo->prepare("INSERT INTO medicines (SKU, ItemName, Category, TypeForm, BatchNumber, SupplierID, Quantity, CostPrice, SellingPrice, ExpiryDate) VALUES (?, ?, 'Uncategorized', 'General', ?, ?, ?, ?, ?, ?)");
        
        $stmt_check_sku = $pdo->prepare("SELECT COUNT(*) FROM medicines WHERE SKU = ?");

        foreach ($items as $item) {
            $name = trim($item['name']);
            $batch = trim($item['batch']);
            $qty = (int)$item['qty'];
            $price = (float)$item['price']; // This is Total Purchase Price for the row
            $unit_cost = $qty > 0 ? ($price / $qty) : 0;
            $expiry = empty($item['expiry']) ? null : $item['expiry'];

            // Insert into purchase_items
            $stmt_item->execute([$purchase_id, $name, $batch, $qty, $price, $expiry]);

            // Check inventory
            $stmt_check_inv->execute([$name, $batch]);
            $existing_med = $stmt_check_inv->fetch(PDO::FETCH_ASSOC);

            if ($existing_med) {
                // UPDATE Quantity, CostPrice, & Supplier
                $stmt_update_inv->execute([$qty, $unit_cost, $supplier_id, $existing_med['ItemID']]);
            } else {
                // Try to find ANY medicine with the same name to inherit Category, Form, and Seasonality
                $stmt_meta = $pdo->prepare("SELECT Category, TypeForm, SeasonalityTag FROM medicines WHERE LOWER(ItemName) = LOWER(?) ORDER BY ItemID DESC LIMIT 1");
                $stmt_meta->execute([$name]);
                $meta = $stmt_meta->fetch(PDO::FETCH_ASSOC);
                
                $category = $meta['Category'] ?? 'Uncategorized';
                $type_form = $meta['TypeForm'] ?? 'General';
                $seasonality = $meta['SeasonalityTag'] ?? 'NONE';

                // INSERT net new
                $selling_price = $unit_cost * 1.3;
                
                $medcode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4));
                if(empty($medcode)) $medcode = 'MEDX';
                preg_match('/\d+/', $name, $matches);
                $strength = $matches[0] ?? '000';
                $sku = $medcode . '-' . $strength . '-' . strtoupper($batch);
                
                $stmt_check_sku->execute([$sku]);
                if ($stmt_check_sku->fetchColumn() > 0) {
                    $sku .= '-' . uniqid();
                }

                $stmt_insert_inv = $pdo->prepare("INSERT INTO medicines (SKU, ItemName, Category, TypeForm, BatchNumber, SupplierID, Quantity, CostPrice, SellingPrice, ExpiryDate, SeasonalityTag) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert_inv->execute([$sku, $name, $category, $type_form, $batch, $supplier_id, $qty, $unit_cost, $selling_price, $expiry, $seasonality]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Successfully saved purchase #$purchase_id and synced inventory."]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
