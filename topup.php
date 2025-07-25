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
    
    if ($amount <= 0) {
        $error = "Jumlah top up harus lebih dari 0";
    } else {
        try {
            $pdo->beginTransaction();

            // Update user balance
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $_SESSION['user_id']]);

            // Record transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'topup', ?, 'Top Up Saldo')");
            $stmt->execute([$_SESSION['user_id'], $amount]);

            $pdo->commit();
            $success = "Top up berhasil!";
            
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

// Predefined top-up amounts
$amounts = [50000, 100000, 200000, 500000, 1000000];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Up - JasPay</title>
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

        .amount-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .amount-option {
            padding: 15px;
            border: 2px solid #ff9f7f;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background-color: white;
        }

        .amount-option:hover {
            background-color: #fff7cc;
        }

        .amount-option.selected {
            background-color: #ff9f7f;
            color: white;
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

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        input:focus {
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

        .or-divider {
            text-align: center;
            margin: 20px 0;
            color: #666;
            position: relative;
        }

        .or-divider::before,
        .or-divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background-color: #ddd;
        }

        .or-divider::before {
            left: 0;
        }

        .or-divider::after {
            right: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-button">← Kembali</a>

        <div class="header">
            <h1>Top Up Saldo</h1>
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
                <div class="amount-grid">
                    <?php foreach ($amounts as $preset): ?>
                        <div class="amount-option" onclick="selectAmount(<?= $preset ?>)">
                            Rp <?= number_format($preset, 0, ',', '.') ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="or-divider">atau</div>

                <div class="form-group">
                    <label for="amount">Jumlah Top Up (Rp)</label>
                    <input type="number" id="amount" name="amount" required min="1" step="1000">
                </div>

                <button type="submit">Top Up Sekarang</button>
            </form>
        </div>
    </div>

    <script>
        function selectAmount(amount) {
            document.getElementById('amount').value = amount;
            
            // Remove selected class from all options
            document.querySelectorAll('.amount-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
        }
    </script>
</body>
</html>
