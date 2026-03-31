<?php
// api.php
//
// This file acts as a RESTful API endpoint for the pharmacy inventory system.
// It handles all data manipulation requests (add, update, delete) and returns
// a JSON response, allowing the front-end to perform actions without a full
// page refresh.

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/subscription_check.php';

// Function to handle errors and return a JSON response
function json_error($message) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// Function to get POST data
function get_post_data() {
    $data = [
        'sku'           => $_POST['sku'] ?? '',
        'name'          => $_POST['name'] ?? '',
        'category'      => $_POST['category'] ?? '',
        'form'          => $_POST['form'] ?? '',
        'batch'         => $_POST['batch'] ?? '',
        'supplier'      => $_POST['supplier'] ?? '',
        'cost_price'    => isset($_POST['cost_price']) ? floatval($_POST['cost_price']) : 0,
        'selling_price' => isset($_POST['selling_price']) ? floatval($_POST['selling_price']) : 0,
        'quantity'      => isset($_POST['quantity']) ? intval($_POST['quantity']) : 0,
        'expiry'        => !empty($_POST['expiry']) ? $_POST['expiry'] : NULL,
        'seasonality'   => $_POST['seasonality'] ?? 'NONE',
    ];
    return $data;
}

// Parse input (handles both standard POST/GET and raw JSON bodies like the import fetch)
$inputJSON = file_get_contents('php://input');
$inputData = json_decode($inputJSON, true);
$action = $_GET['action'] ?? ($_POST['action'] ?? ($inputData['action'] ?? ''));

switch ($action) {
    case 'add_medicine':
        // Subscription Limit Check
        $limit = $_SESSION['limits']['medicine'] ?? 50;
        $countStmt = $pdo->query("SELECT COUNT(*) FROM medicines");
        $currentCount = $countStmt->fetchColumn();
        if ($currentCount >= $limit) {
            json_error("Medicine limit reached for your " . ($_SESSION['plan_name'] ?? 'Free Trial') . " plan. Please upgrade to add more.");
        }

        $data = get_post_data();
        $sku = trim($data['sku']);
        if (empty($sku)) {
            $medcode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $data['name']), 0, 4));
            if(empty($medcode)) $medcode = 'MEDX';
            preg_match('/\d+/', $data['name'], $matches);
            $strength = $matches[0] ?? '000';
            $sku = $medcode . '-' . $strength . '-' . strtoupper($data['batch']);
        }
        $check = $pdo->prepare('SELECT COUNT(*) FROM medicines WHERE SKU = ?');
        $check->execute([$sku]);
        if ($check->fetchColumn() > 0) {
            json_error('SKU already exists. Please ensure SKU or Batch is unique.');
        }

        $sql = "INSERT INTO medicines (SKU, ItemName, Category, TypeForm, BatchNumber, SupplierName, CostPrice, SellingPrice, Quantity, ExpiryDate, SeasonalityTag) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $sku, $data['name'], $data['category'], $data['form'], $data['batch'], 
                $data['supplier'], $data['cost_price'], $data['selling_price'], $data['quantity'], $data['expiry'], $data['seasonality']
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Medicine added successfully!', 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            json_error('Error adding medicine: ' . $e->getMessage());
        }
        break;

    case 'update_medicine':
        $data = get_post_data();
        $id = intval($_POST['update_id']);
        
        $sku = trim($data['sku']);
        if (empty($sku)) {
            $medcode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $data['name']), 0, 4));
            if(empty($medcode)) $medcode = 'MEDX';
            preg_match('/\d+/', $data['name'], $matches);
            $strength = $matches[0] ?? '000';
            $sku = $medcode . '-' . $strength . '-' . strtoupper($data['batch']);
        }
        $check = $pdo->prepare('SELECT COUNT(*) FROM medicines WHERE SKU = ? AND ItemID != ?');
        $check->execute([$sku, $id]);
        if ($check->fetchColumn() > 0) {
            json_error('SKU already exists on another item.');
        }

        $sql = "UPDATE medicines SET SKU=?, ItemName=?, Category=?, TypeForm=?, BatchNumber=?, SupplierName=?, CostPrice=?, SellingPrice=?, Quantity=?, ExpiryDate=?, SeasonalityTag=? WHERE ItemID=?";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $sku, $data['name'], $data['category'], $data['form'], $data['batch'], 
                $data['supplier'], $data['cost_price'], $data['selling_price'], $data['quantity'], $data['expiry'], $data['seasonality'], $id
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Medicine updated successfully!']);
        } catch (PDOException $e) {
            json_error('Error updating medicine: ' . $e->getMessage());
        }
        break;
    
    case 'delete_medicine':
        $id = intval($_GET['id']);
        $sql = "DELETE FROM medicines WHERE ItemID=?";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Medicine deleted successfully!']);
        } catch (PDOException $e) {
            json_error('Error deleting medicine: ' . $e->getMessage());
        }
        break;

    case 'get_inventory':
        $filter_name = $_GET['filter_name'] ?? '';
        $filter_category = $_GET['filter_category'] ?? '';
        $filter_supplier = $_GET['filter_supplier'] ?? '';
        $sort_expiry = $_GET['sort_expiry'] ?? '';

        $sql = "SELECT ItemID, SKU, ItemName, Category, TypeForm, BatchNumber, SupplierName, CostPrice, SellingPrice, Quantity, ExpiryDate, SeasonalityTag FROM medicines WHERE 1";
        $params = [];

        if (!empty($filter_name)) {
            $sql .= " AND (ItemName LIKE ? OR SKU LIKE ?)";
            $params[] = '%' . $filter_name . '%';
            $params[] = '%' . $filter_name . '%';
        }
        if (!empty($filter_category)) {
            $sql .= " AND Category = ?";
            $params[] = $filter_category;
        }
        if (!empty($filter_supplier)) {
            $sql .= " AND SupplierName = ?";
            $params[] = $filter_supplier;
        }
        if (!empty($sort_expiry)) {
            $sql .= " ORDER BY ExpiryDate ASC";
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (PDOException $e) {
            json_error('Execution failed: ' . $e->getMessage());
        }
        break;

    case 'import_inventory':
        // Subscription Limit Check
        $limit = $_SESSION['limits']['medicine'] ?? 50;
        $countStmt = $pdo->query("SELECT COUNT(*) FROM medicines");
        $currentCount = $countStmt->fetchColumn();
        
        // Expects JSON array in raw input body
        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];

        if (($currentCount + count($items)) > $limit) {
             json_error("Importing " . count($items) . " items would exceed your " . ($_SESSION['plan_name'] ?? 'Free Trial') . " plan limit ($limit). Please upgrade.");
        }

        if (empty($items)) {
            echo json_encode(['status' => 'error', 'message' => 'No items provided for import.']);
            exit;
        }

        $sql = "INSERT INTO medicines (SKU, ItemName, Category, TypeForm, BatchNumber, SupplierName, CostPrice, SellingPrice, Quantity, ExpiryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare($sql);
            
            $count = 0;
            foreach ($items as $row) {
                // Mapping typical column names from Excel/CSV to database columns
                $name = $row['ItemName'] ?? ($row['Name'] ?? ($row['Medicine'] ?? ''));
                if (empty($name)) continue; // Skip rows without name
                
                $category = $row['Category'] ?? 'Uncategorized';
                $form = $row['TypeForm'] ?? ($row['Form'] ?? 'General');
                $batch = $row['BatchNumber'] ?? ($row['Batch'] ?? 'N/A');
                $supplier = $row['SupplierName'] ?? ($row['Supplier'] ?? 'Unknown');
                $cost = floatval($row['CostPrice'] ?? ($row['Cost'] ?? 0));
                $sell = floatval($row['SellingPrice'] ?? ($row['Price'] ?? 0));
                $qty = intval($row['Quantity'] ?? ($row['Qty'] ?? 0));
                
                // Handle ExpiryDate
                $expiry = NULL;
                $rawExpiry = $row['ExpiryDate'] ?? ($row['Expiry'] ?? '');
                if (!empty($rawExpiry)) {
                    // Check if Excel serial date
                    if (is_numeric($rawExpiry)) {
                        $unixDate = ($rawExpiry - 25569) * 86400;
                        $expiry = gmdate("Y-m-d", $unixDate);
                    } else {
                        $expiry = date('Y-m-d', strtotime($rawExpiry));
                    }
                }

                $rawSku = $row['SKU'] ?? ($row['sku'] ?? '');
                if (empty($rawSku)) {
                    $medcode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4));
                    if(empty($medcode)) $medcode = 'MEDX';
                    preg_match('/\d+/', $name, $matches);
                    $strength = $matches[0] ?? '000';
                    $rawSku = $medcode . '-' . $strength . '-' . strtoupper($batch);
                }
                
                $check = $pdo->prepare('SELECT COUNT(*) FROM medicines WHERE SKU = ?');
                $check->execute([$rawSku]);
                if ($check->fetchColumn() > 0) {
                    $rawSku .= '-' . uniqid();
                }

                $stmt->execute([$rawSku, $name, $category, $form, $batch, $supplier, $cost, $sell, $qty, $expiry]);
                $count++;
            }
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => "$count items imported successfully!"]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_error('Error importing inventory: ' . $e->getMessage());
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action requested.']);
        break;
}
?>
