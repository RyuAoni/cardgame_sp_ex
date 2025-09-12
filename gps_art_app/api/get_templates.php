<?php
// get_templates.php (データベース連携版)

require_once 'db_connect.php';

header('Content-Type: application/json');

// 1. フロントエンドから、どの都道府県の型を見たいかprefecture_idを受け取る
$prefecture_id = $_GET['prefecture_id'] ?? null;

if (!$prefecture_id) {
    http_response_code(400);
    echo json_encode(['error' => 'prefecture_idが指定されていません。']);
    exit();
}

try {
    // 2. 指定されたprefecture_idに属する全ての「型」をDBから取得する
    $stmt = $pdo->prepare(
        "SELECT
            id as template_id,
            template_name,
            thumbnail_url
        FROM
            templates
        WHERE
            prefecture_id = :prefecture_id
        ORDER BY
            id ASC"
    );
    $stmt->execute([':prefecture_id' => $prefecture_id]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($templates);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}

