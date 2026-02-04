<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['role'])) {
    header('Location: /dashboard/login.php');
    exit;
}

$role = $_SESSION['role'];
$siteId = $_SESSION['site_id'] ?? null;

$platformPdo = getDbConnection();
if (!$platformPdo) {
    http_response_code(500);
    echo 'Platform database not available';
    exit;
}

// 사이트 관리자: 자신의 사이트 결제 목록
if ($role === 'site_admin') {
    if (!$siteId) {
        http_response_code(400);
        echo 'Site context missing';
        exit;
    }

    $sitePdo = getSiteDbConnectionBySiteId($siteId);
    if (!$sitePdo) {
        http_response_code(500);
        echo 'Site database not available';
        exit;
    }

    $stmt = $sitePdo->query('
        SELECT merchant_uid, amount, status, payment_date, created_at
        FROM payments
        ORDER BY COALESCE(payment_date, created_at) DESC
        LIMIT 50
    ');
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $title = '사이트 결제 내역';
} else {
    // 플랫폼 관리자: 사이트 목록
    $stmt = $platformPdo->query('
        SELECT site_id, name, domain, status, created_at
        FROM sites
        ORDER BY created_at DESC
        LIMIT 100
    ');
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $title = '사이트 목록';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: sans-serif; background: #020617; color: #e5e7eb; margin: 0; }
        header { padding: 16px 24px; border-bottom: 1px solid #111827; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-weight: 700; font-size: 18px; }
        .role { font-size: 13px; color: #9ca3af; }
        main { padding: 24px; }
        h1 { font-size: 20px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #111827; text-align: left; }
        th { background: #020617; color: #9ca3af; font-weight: 500; }
        tr:nth-child(even) td { background: #020617; }
        .chip { padding: 2px 8px; border-radius: 999px; font-size: 11px; display: inline-block; }
        .chip-active { background: #064e3b; color: #bbf7d0; }
        .chip-inactive { background: #4b5563; color: #e5e7eb; }
        .chip-paid { background: #064e3b; color: #bbf7d0; }
        .chip-cancelled { background: #7f1d1d; color: #fecaca; }
        .chip-pending { background: #78350f; color: #fed7aa; }
        a { color: #60a5fa; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header>
        <div class="logo">Payment Dashboard</div>
        <div class="role">
            <?= htmlspecialchars($role === 'platform_admin' ? '플랫폼 관리자' : '사이트 관리자', ENT_QUOTES, 'UTF-8') ?>
            <?php if ($siteId && $role === 'site_admin'): ?>
                · <?= htmlspecialchars($siteId, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </div>
    </header>
    <main>
        <?php if ($role === 'site_admin'): ?>
            <h1>최근 결제 내역</h1>
            <table>
                <thead>
                    <tr>
                        <th>merchant_uid</th>
                        <th>금액</th>
                        <th>상태</th>
                        <th>결제일</th>
                        <th>생성일</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['merchant_uid'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((int)$row['amount']) ?>원</td>
                        <td>
                            <?php
                            $status = $row['status'];
                            $class = 'chip chip-pending';
                            if ($status === 'paid') $class = 'chip chip-paid';
                            elseif ($status === 'cancelled') $class = 'chip chip-cancelled';
                            ?>
                            <span class="<?= $class ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td><?= htmlspecialchars($row['payment_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <h1>등록된 사이트</h1>
            <table>
                <thead>
                    <tr>
                        <th>site_id</th>
                        <th>이름</th>
                        <th>도메인</th>
                        <th>상태</th>
                        <th>생성일</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sites as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['site_id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['domain'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php $active = $row['status'] === 'active'; ?>
                            <span class="chip <?= $active ? 'chip-active' : 'chip-inactive' ?>">
                                <?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>

