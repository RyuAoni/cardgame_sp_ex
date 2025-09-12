<?php
// get_template_detail.php (データベース連携版)

require_once 'db_connect.php';

header('Content-Type: application/json');

// 1. どの型の詳細を見たいかtemplate_idを受け取る
$template_id = $_GET['template_id'] ?? null;

if (!$template_id) {
    http_response_code(400);
    echo json_encode(['error' => 'template_idが指定されていません。']);
    exit();
}

try {
    // 2. まず、型の基本情報（スタート地点など）を取得
    $stmt = $pdo->prepare(
        "SELECT id as template_id, start_latitude, start_longitude FROM templates WHERE id = :id"
    );
    $stmt->execute([':id' => $template_id]);
    $template_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template_info) {
        http_response_code(404);
        echo json_encode(['error' => '指定された型が見つかりません。']);
        exit();
    }

    // 3. 次に、その型に関連する全てのGPS座標を、順番通りに取得
    $stmt = $pdo->prepare(
        "SELECT latitude, longitude FROM template_points WHERE template_id = :template_id ORDER BY sequence ASC"
    );
    $stmt->execute([':template_id' => $template_id]);
    $gps_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. フロントエンドに返すデータを整形する
    $response = [
        'template_id' => (int)$template_info['template_id'],
        'start_point' => [
            'latitude' => $template_info['start_latitude'],
            'longitude' => $template_info['start_longitude']
        ],
        'gps_points' => $gps_points
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}

