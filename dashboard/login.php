<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = '이메일과 비밀번호를 입력해주세요.';
    } else {
        $pdo = getDbConnection();
        if (!$pdo) {
            $error = '데이터베이스 연결에 실패했습니다.';
        } else {
            // 플랫폼 관리자 우선
            $stmt = $pdo->prepare('SELECT id, email, password_hash, name, role FROM platform_admins WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['role'] = 'platform_admin';
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['site_id'] = null;
                header('Location: /dashboard/index.php');
                exit;
            }

            // 사이트 관리자
            $stmt = $pdo->prepare('SELECT id, site_id, email, password_hash, name, role FROM site_admins WHERE email = ? AND status = "active" LIMIT 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['role'] = 'site_admin';
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['site_id'] = $admin['site_id'];
                header('Location: /dashboard/index.php');
                exit;
            }

            $error = '로그인 정보가 올바르지 않습니다.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>결제 대시보드 로그인</title>
    <style>
        body { font-family: sans-serif; background: #111; color: #eee; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #1e1e1e; padding: 32px; border-radius: 8px; width: 360px; box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
        h1 { font-size: 20px; margin-bottom: 16px; }
        label { display: block; margin-bottom: 4px; font-size: 13px; color: #ccc; }
        input { width: 100%; padding: 8px 10px; margin-bottom: 12px; border-radius: 4px; border: 1px solid #444; background: #121212; color: #eee; }
        button { width: 100%; padding: 10px; border-radius: 4px; border: none; background: #3b82f6; color: white; font-weight: 600; cursor: pointer; }
        button:hover { background: #2563eb; }
        .error { color: #f97373; margin-bottom: 12px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>결제 대시보드 로그인</h1>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
            <label for="email">이메일</label>
            <input type="email" id="email" name="email" required>

            <label for="password">비밀번호</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">로그인</button>
        </form>
    </div>
</body>
</html>

