<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (isset($_GET['school_id']) && is_numeric($_GET['school_id'])) {
    $school_id = (int)$_GET['school_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT MAX(order_number) as max_order FROM stages WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['max_order' => $result['max_order'] ?? 0]);
    } catch(PDOException $e) {
        echo json_encode(['max_order' => 0, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['max_order' => 0]);
}
?>