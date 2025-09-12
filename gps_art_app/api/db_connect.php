<?php
// db_connect.php
// このファイルは、データベース接続に関するすべての情報を持っています。

$host = 'localhost';
$dbname = 'gps_art_app';
$user = 'gps_art_user';
// ↓↓↓↓↓↓ ここに、XAMPPのphpMyAdminで設定した正しいパスワードを入力してください ↓↓↓↓↓↓
$password = 'Age0o3TkgoeSseiq'; // 例: 'root' や 'password123'など

// これ以降のAPIファイルは、このファイルさえ読み込めば、
// 以下のPDOオブジェクト($pdo)をすぐに使えるようになります。
try {
    // データベースに接続
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    
    // エラーが発生した場合に、例外を投げるように設定（エラーハンドリングの基本）
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // 接続自体でエラーが起きた場合は、ここで処理を停止してエラーを返す
    http_response_code(500);
    // 画面には、より一般的なエラーメッセージを表示するのが親切です。
    // 詳細なエラー($e->getMessage())は、開発中のデバッグでのみ使うようにしましょう。
    echo json_encode(['error' => 'データベース接続に失敗しました。']);
    exit(); // 処理を終了
}
