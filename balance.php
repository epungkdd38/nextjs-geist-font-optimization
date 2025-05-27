<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user balance
$stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$balance = $stmt->fetchColumn();

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT type, amount, description, created_at 
    FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();

// Get monthly statistics
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'topup' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as total_expense
    FROM transactions 
    WHERE user_id = ? 
    AND created_at >= DATE_FORMAT(NOW() ,'%Y-%m-01')
");
$stmt->execute([$_SESSION['user_id']]);
$monthly_stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Saldo - JasPay</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f4f4f4;
            min-height: 100vh;
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff7cc;
            min-height: 100vh;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 20px;
        }

        .balance {
            font-size: 2.5em;
            font-weight: 600;
            margin: 20px 0;
            color: #333;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-box {
            background-color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-box .label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .stat-box .value {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }

        .stat-box.income .value {
            color: #28a745;
        }

        .stat-box.expense .value {
            color: #dc3545;
        }

        .transactions-container {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .transactions-header {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-description {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .transaction-date {
            font-size: 0.85em;
            color: #666;
        }

        .transaction-amount {
            font-weight: 600;
        }

        .transaction-amount.topup {
            color: #28a745;
        }

        .transaction-amount.payment {
            color: #dc3545;
        }

        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #e6d4ff;
            color: black;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .no-transactions {
            text-align: center;
            color: #666;
            padding: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-button">← Kembali</a>

        <div class="header">
            <h1>Saldo Kamu</h1>
            <div class="balance">
                Rp <?= number_format($balance, 0, ',', '.') ?>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-box income">
                <div class="label">Pemasukan Bulan Ini</div>
                <div class="value">Rp <?= number_format($monthly_stats['total_income'] ?? 0, 0, ',', '.') ?></div>
            </div>
            <div class="stat-box expense">
                <div class="label">Pengeluaran Bulan Ini</div>
                <div class="value">Rp <?= number_format($monthly_stats['total_expense'] ?? 0, 0, ',', '.') ?></div>
            </div>
        </div>

        <div class="transactions-container">
            <div class="transactions-header">Transaksi Terakhir</div>
            
            <?php if (empty($transactions)): ?>
                <div class="no-transactions">
                    Belum ada transaksi
                </div>
            <?php else: ?>
                <?php foreach ($transactions as $transaction): ?>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-description">
                                <?= htmlspecialchars($transaction['description'] ?: ($transaction['type'] === 'topup' ? 'Top Up Saldo' : 'Pembayaran')) ?>
                            </div>
                            <div class="transaction-date">
                                <?= date('d M Y H:i', strtotime($transaction['created_at'])) ?>
                            </div>
                        </div>
                        <div class="transaction-amount <?= $transaction['type'] ?>">
                            <?= $transaction['type'] === 'topup' ? '+' : '-' ?>
                            Rp <?= number_format($transaction['amount'], 0, ',', '.') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
