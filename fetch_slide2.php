<?php
// แสดงข้อผิดพลาดทั้งหมดเพื่อการดีบัก
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ตั้งค่า Header ให้เป็น JSON และใช้ UTF-8
header("Content-Type: application/json; charset=utf-8");

// ข้อมูลการเชื่อมต่อฐานข้อมูล
$config = [
    'host' => 'localhost',
    'dbname' => 'ssraexhi_hittest',
    'username' => 'ssraexhi_hittest',
    'password' => 'l6-lyo9N',
    'charset' => 'utf8mb4'
];

try {
    // สร้างการเชื่อมต่อ PDO
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);

    // ตรวจสอบและกรองข้อมูล Input
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id === null || $id === false) {
        // หากไม่มี ID หรือ ID ไม่ถูกต้อง, ส่งค่าผิดพลาดกลับไป
        http_response_code(400); // 400 Bad Request
        echo json_encode(['error' => true, 'message' => 'Invalid or missing slide ID']);
        exit;
    }

    // *** MODIFIED ***: แก้ไข SQL query ให้ดึงคอลัมน์ spoken_form เพิ่ม
    $stmt = $pdo->prepare("SELECT word, image_path, spoken_form FROM wordstest WHERE id = ?");
    $stmt->execute([$id]);
    
    $result = $stmt->fetch();
    
    if ($result) {
        // *** MODIFIED ***: สร้าง Array สำหรับการตอบกลับเพื่อให้มีโครงสร้างที่ชัดเจน
        $response_data = [
            'error' => false,
            'word' => $result['word'],
            'image_path' => $result['image_path'],
            'spoken_word' => $result['spoken_form'] // ส่งคำอ่านกลับไปใน key 'spoken_word'
        ];

        http_response_code(200);
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE);

    } else {
        // ถ้าไม่พบข้อมูล
        http_response_code(404); // 404 Not Found
        echo json_encode(['error' => true, 'message' => 'Slide not found']);
    }

} catch (PDOException $e) {
    // จัดการข้อผิดพลาดของฐานข้อมูล
    http_response_code(500); // 500 Internal Server Error
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // จัดการข้อผิดพลาดทั่วไป
    http_response_code(500); // 500 Internal Server Error
    echo json_encode([
        'error' => true,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>