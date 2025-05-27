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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $description = $_POST['description'];

    if ($amount <= 0) {
        $error = "Jumlah pembayaran harus lebih dari 0";
    } elseif ($amount > $balance) {
        $error = "Saldo tidak mencukupi";
    } else {
        try {
            $pdo->beginTransaction();

            // Update user balance
            $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$amount, $_SESSION['user_id']]);

            // Record transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'payment', ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $amount, $description]);

            $pdo->commit();
            $success = "Pembayaran berhasil!";
            
            // Refresh balance
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $balance = $stmt->fetchColumn();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - JasPay</title>
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
            font-size: 2em;
            font-weight: 600;
            margin: 20px 0;
            color: #333;
        }

        .form-container {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        input:focus, textarea:focus {
            border-color: #ff9f7f;
            outline: none;
        }

        .error {
            color: #ff4444;
            margin-bottom: 15px;
            text-align: center;
            padding: 10px;
            background-color: #ffe5e5;
            border-radius: 8px;
        }

        .success {
            color: #28a745;
            margin-bottom: 15px;
            text-align: center;
            padding: 10px;
            background-color: #e5ffe5;
            border-radius: 8px;
        }

        button {
            width: 100%;
            padding: 15px;
            background-color: #ff9f7f;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #ff8a66;
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
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-button">← Kembali</a>

        <div class="header">
            <h1>Pembayaran</h1>
            <div class="balance">
                Saldo: Rp <?= number_format($balance, 0, ',', '.') ?>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="amount">Jumlah Pembayaran (Rp)</label>
                    <input type="number" id="amount" name="amount" required min="1" step="1000">
                </div>

                <div class="form-group">
                    <label for="description">Keterangan</label>
                    <textarea id="description" name="description" rows="3" required></textarea>
                </div>

                <button type="submit">Bayar Sekarang</button>
            </form>
        </div>
    </div>
</body>
</html>
