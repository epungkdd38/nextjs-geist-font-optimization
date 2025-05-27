<?php
session_start();
require_once '../config.php';
require_once '../assets/images/icons.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Check if student ID is provided
if (!isset($_GET['id'])) {
    header('Location: manage_students.php');
    exit();
}

$student_id = $_GET['id'];

// Get student details
$stmt = $pdo->prepare("
    SELECT s.*, u.username 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: manage_students.php');
    exit();
}

// Handle balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adjust_balance') {
        $amount = (float)$_POST['amount'];
        $type = $_POST['type'];
        $description = $_POST['description'];

        try {
            $pdo->beginTransaction();

            if ($type === 'add') {
                $stmt = $pdo->prepare("UPDATE students SET balance = balance + ? WHERE id = ?");
                $transaction_type = 'topup';
            } else {
                $stmt = $pdo->prepare("UPDATE students SET balance = balance - ? WHERE id = ?");
                $transaction_type = 'payment';
            }
            $stmt->execute([$amount, $student_id]);

            // Record transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (student_id, cashier_id, type, amount, description) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $_SESSION['user_id'], $transaction_type, $amount, $description]);

            $pdo->commit();
            $success = "Saldo berhasil disesuaikan";

            // Refresh student data
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
}

// Get transactions
$stmt = $pdo->prepare("
    SELECT t.*, u.username as cashier_name
    FROM transactions t
    JOIN users u ON t.cashier_id = u.id
    WHERE t.student_id = ?
    ORDER BY t.created_at DESC
    LIMIT 50
");
$stmt->execute([$student_id]);
$transactions = $stmt->fetchAll();

// Get monthly statistics
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as total_payments,
        SUM(CASE WHEN type = 'topup' THEN amount ELSE 0 END) as total_topups,
        COUNT(*) as total_transactions
    FROM transactions 
    WHERE student_id = ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$stmt->execute([$student_id]);
$monthly_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Siswa - JasPay</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .student-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .student-name {
            font-size: 2em;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .student-meta {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .student-balance {
            font-size: 2.5em;
            font-weight: 600;
            color: var(--accent-color);
            margin: 20px 0;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: var(--secondary-bg);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .back-button:hover {
            transform: translateX(-5px);
        }

        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .section {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .transaction-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .transaction-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .transaction-icon.payment {
            background-color: #ffe5e5;
            color: var(--error-color);
        }

        .transaction-icon.topup {
            background-color: #e5ffe5;
            color: var(--success-color);
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-description {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .transaction-meta {
            color: var(--text-secondary);
            font-size: 0.85em;
            display: flex;
            gap: 15px;
        }

        .transaction-amount {
            font-weight: 600;
            text-align: right;
        }

        .transaction-amount.payment {
            color: var(--error-color);
        }

        .transaction-amount.topup {
            color: var(--success-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            outline: none;
        }

        .submit-button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .add-button {
            background-color: var(--success-color);
            color: white;
        }

        .subtract-button {
            background-color: var(--error-color);
            color: white;
        }

        .submit-button:hover {
            transform: translateY(-2px);
        }

        .monthly-stats {
            display: grid;
            gap: 15px;
        }

        .month-stat {
            background-color: var(--primary-bg);
            padding: 15px;
            border-radius: 8px;
        }

        .month-header {
            font-weight: 500;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .month-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 0.9em;
        }

        .month-detail {
            padding: 8px;
            background-color: white;
            border-radius: 6px;
            text-align: center;
        }

        .detail-label {
            color: var(--text-secondary);
            font-size: 0.85em;
            margin-bottom: 4px;
        }

        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
        }

        .detail-value.income {
            color: var(--success-color);
        }

        .detail-value.expense {
            color: var(--error-color);
        }

        .error-message {
            color: var(--error-color);
            background-color: #ffe5e5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .success-message {
            color: var(--success-color);
            background-color: #e5ffe5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="manage_students.php" class="back-button">
            <?= getBackIcon() ?>
            Kembali ke Daftar Siswa
        </a>

        <div class="header">
            <div class="student-info">
                <div class="student-name"><?= htmlspecialchars($student['full_name']) ?></div>
                <div class="student-meta">
                    Kelas <?= htmlspecialchars($student['class']) ?> | 
                    Username: <?= htmlspecialchars($student['username']) ?> | 
                    NFC ID: <?= htmlspecialchars($student['nfc_id']) ?>
                </div>
                <div class="student-balance">
                    Rp <?= number_format($student['balance'], 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <div class="main-content">
            <div>
                <div class="section">
                    <div class="section-title">
                        <?= getTransactionIcon() ?>
                        Riwayat Transaksi
                    </div>
                    <?php foreach ($transactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-icon <?= $transaction['type'] ?>">
                                <?php if ($transaction['type'] === 'payment'): ?>
                                    <?= getPaymentIcon() ?>
                                <?php else: ?>
                                    <?= getTopupIcon() ?>
                                <?php endif; ?>
                            </div>
                            <div class="transaction-info">
                                <div class="transaction-description">
                                    <?= htmlspecialchars($transaction['description'] ?: ($transaction['type'] === 'topup' ? 'Top Up Saldo' : 'Pembayaran')) ?>
                                </div>
                                <div class="transaction-meta">
                                    <span><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></span>
                                    <span>Kasir: <?= htmlspecialchars($transaction['cashier_name']) ?></span>
                                </div>
                            </div>
                            <div class="transaction-amount <?= $transaction['type'] ?>">
                                <?= $transaction['type'] === 'payment' ? '-' : '+' ?>
                                Rp <?= number_format($transaction['amount'], 0, ',', '.') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <div class="section">
                    <div class="section-title">Penyesuaian Saldo</div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="adjust_balance">
                        
                        <div class="form-group">
                            <label>Jumlah (Rp)</label>
                            <input type="number" 
                                   name="amount" 
                                   class="form-control" 
                                   required 
                                   min="1000"
                                   step="1000">
                        </div>

                        <div class="form-group">
                            <label>Keterangan</label>
                            <input type="text" 
                                   name="description" 
                                   class="form-control" 
                                   required>
                        </div>

                        <div style="display: grid; gap: 10px;">
                            <button type="submit" 
                                    name="type" 
                                    value="add" 
                                    class="submit-button add-button">
                                <?= getTopupIcon() ?>
                                Tambah Saldo
                            </button>
                            <button type="submit" 
                                    name="type" 
                                    value="subtract" 
                                    class="submit-button subtract-button">
                                <?= getPaymentIcon() ?>
                                Kurangi Saldo
                            </button>
                        </div>
                    </form>
                </div>

                <div class="section">
                    <div class="section-title">Statistik Bulanan</div>
                    <div class="monthly-stats">
                        <?php foreach ($monthly_stats as $stat): ?>
                            <div class="month-stat">
                                <div class="month-header">
                                    <?= date('F Y', strtotime($stat['month'] . '-01')) ?>
                                </div>
                                <div class="month-details">
                                    <div class="month-detail">
                                        <div class="detail-label">Total Transaksi</div>
                                        <div class="detail-value">
                                            <?= number_format($stat['total_transactions']) ?>
                                        </div>
                                    </div>
                                    <div class="month-detail">
                                        <div class="detail-label">Total Top Up</div>
                                        <div class="detail-value income">
                                            Rp <?= number_format($stat['total_topups'], 0, ',', '.') ?>
                                        </div>
                                    </div>
                                    <div class="month-detail">
                                        <div class="detail-label">Total Pembayaran</div>
                                        <div class="detail-value expense">
                                            Rp <?= number_format($stat['total_payments'], 0, ',', '.') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
