<?php
// create_artwork.php (保護版)

require_once 'db_connect.php';
// ★★★ 門番を配置！ ★★★
// これ以降のコードは、認証に成功した場合にのみ実行される。
// 認証に成功すると、$authenticated_user_id 変数が使えるようになる。
require_once 'authenticate.php';

header('Content-Type: application/json');

try {
    // --- 処理の開始 ---

    // 1. 仮のユーザーではなく、認証されたユーザーのIDを使う
    // $authenticated_user_id は authenticate.php から引き継がれる
    $user_id = $authenticated_user_id;

    // 2. 新しい作品を作成
    $stmt = $pdo->prepare("INSERT INTO artworks (user_id) VALUES (:user_id)");
    $stmt->execute([':user_id' => $user_id]);
    $artwork_id = $pdo->lastInsertId();

    // --- 処理の終了 ---

    // 成功した場合は、新しい作品IDをJSON形式で返す
    echo json_encode(['artwork_id' => $artwork_id]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}
