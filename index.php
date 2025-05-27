<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$balance = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND created_at >= DATE_FORMAT(NOW() ,'%Y-%m-01')");
$stmt->execute([$_SESSION['user_id']]);
$monthly_income = $stmt->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JasPay - Digital Payment</title>
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
            position: relative;
        }

        .mascot {
            width: 120px;
            margin-bottom: 10px;
        }

        .balance {
            font-size: 2.5em;
            font-weight: 600;
            margin: 20px 0;
        }

        .monthly-stats {
            background-color: #ff9f7f;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            cursor: pointer;
            text-align: left;
        }

        .action-buttons {
            display: grid;
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-button {
            padding: 20px;
            border-radius: 10px;
            border: none;
            font-size: 1.2em;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: black;
        }

        .pay-button {
            background-color: #e6d4ff;
        }

        .balance-button {
            background-color: #fff7cc;
        }

        .topup-button {
            background-color: #ff9f7f;
        }

        .footer-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: auto;
        }

        .stat-box {
            background-color: #ff9f7f;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            color: white;
        }

        .stat-box img {
            width: 40px;
            margin-bottom: 10px;
        }

        .welcome-text {
            font-size: 1.2em;
            margin-bottom: 10px;
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="mascot.png" alt="JasPay Mascot" class="mascot">
            <div class="welcome-text">Welcome to JasPay!</div>
            <div class="balance">Rp <?= number_format($balance, 0, ',', '.') ?></div>
        </div>

        <div class="monthly-stats">
            Keren! Rp <?= number_format($monthly_income, 0, ',', '.') ?> total pemasukan bulan ini.. ->
        </div>

        <div class="action-buttons">
            <a href="payment.php" class="action-button pay-button">
                Ayo Bayar
                <img src="pay-icon.png" alt="Pay" width="30">
            </a>
            <a href="balance.php" class="action-button balance-button">
                Cek Saldo-mu
                <img src="balance-icon.png" alt="Balance" width="30">
            </a>
            <a href="topup.php" class="action-button topup-button">
                Gas, Top Up!
                <img src="topup-icon.png" alt="Top Up" width="30">
            </a>
        </div>

        <div class="footer-text">
            Makasih kamu udah kerja keras hari ini! 👑
        </div>

        <div class="footer-stats">
            <a href="transactions.php" class="stat-box">
                <img src="transaction-icon.png" alt="Transactions">
                Total Transaksi
            </a>
            <a href="income.php" class="stat-box">
                <img src="income-icon.png" alt="Income">
                Cuan Hari Ini
            </a>
            <a href="expenses.php" class="stat-box">
                <img src="expense-icon.png" alt="Expenses">
                Pengeluaran
            </a>
        </div>
    </div>
</body>
</html>
