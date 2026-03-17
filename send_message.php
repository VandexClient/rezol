<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sender_id = $_SESSION['user_id'];
    $receiver_id = $_POST['receiver_id'];
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Обработка файла
    $file_path = null;
    $file_name = null;
    $file_size = null;
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
        $filename = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_path = uniqid() . '.' . $ext;
            $file_name = $filename;
            $file_size = $_FILES['file']['size'];
            
            move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $file_path);
        }
    }
    
    // Сохранение сообщения
    if (!empty($message) || $file_path) {
        $stmt = $db->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, file_path, file_name, file_size) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$sender_id, $receiver_id, $message, $file_path, $file_name, $file_size]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Пустое сообщение']);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>