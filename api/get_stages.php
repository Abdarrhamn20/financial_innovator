<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (isset($_GET['school_id']) && is_numeric($_GET['school_id'])) {
    $school_id = (int)$_GET['school_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM stages WHERE school_id = ? ORDER BY name");
        $stmt->execute([$school_id]);
        $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($stages);
    } catch(PDOException $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>