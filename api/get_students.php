<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (isset($_GET['school_id']) && is_numeric($_GET['school_id'])) {
    $school_id = (int)$_GET['school_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM students WHERE school_id = ? ORDER BY name");
        $stmt->execute([$school_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($students);
    } catch(PDOException $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>