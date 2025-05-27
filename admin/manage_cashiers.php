<?php
session_start();
require_once '../config.php';
require_once '../assets/images/icons.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle cashier management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = $_POST['full_name'];

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, 2)");
            $stmt->execute([$username, $password]);
            $success = "Kasir berhasil ditambahkan";
        } catch (Exception $e) {
            $error = "Username sudah digunakan";
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['user_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role_id = 2");
            $stmt->execute([$_POST['user_id']]);
            $success = "Kasir berhasil dihapus";
        } catch (Exception $e) {
            $error = "Terjadi kesalahan saat menghapus kasir";
        }
    } elseif ($_POST['action'] === 'reset_password') {
        $user_id = $_POST['user_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role_id = 2");
            $stmt->execute([$new_password, $user_id]);
            $success = "Password kasir berhasil direset";
        } catch (Exception $e) {
            $error = "Terjadi kesalahan saat mereset password";
        }
    }
}

// Get all cashiers
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(t.id) as total_transactions,
           SUM(CASE WHEN DATE(t.created_at) = CURDATE() THEN 1 ELSE 0 END) as today_transactions
    FROM users u 
    LEFT JOIN transactions t ON u.id = t.cashier_id
    WHERE u.role_id = 2 
    GROUP BY u.id
    ORDER BY u.username
");
$stmt->execute();
$cashiers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kasir - JasPay</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            transition: all 0.3s;
        }

        .back-button:hover {
            transform: translateX(-5px);
        }

        .add-button {
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .add-button:hover {
            background-color: #ff8a66;
            transform: translateY(-2px);
        }

        .cashiers-grid {
            display: grid;
            gap: 20px;
        }

        .cashier-card {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .cashier-info h3 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }

        .cashier-meta {
            color: var(--text-secondary);
            font-size: 0.9em;
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }

        .cashier-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .stat {
            background-color: var(--primary-bg);
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9em;
        }

        .cashier-actions {
            display: flex;
            gap: 10px;
        }

        .action-button {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }

        .reset-button {
            background-color: var(--secondary-bg);
            color: var(--text-primary);
        }

        .delete-button {
            background-color: #ff4444;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .close-button {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: var(--text-secondary);
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
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-button:hover {
            background-color: #ff8a66;
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
        <div class="header">
            <a href="dashboard.php" class="back-button">
                <?= getBackIcon() ?>
                Kembali ke Dashboard
            </a>
            <button class="add-button" onclick="showAddModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Tambah Kasir
            </button>
        </div>

        <?php if (isset($success)): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <div class="cashiers-grid">
            <?php foreach ($cashiers as $cashier): ?>
                <div class="cashier-card">
                    <div class="cashier-info">
                        <h3><?= htmlspecialchars($cashier['username']) ?></h3>
                        <div class="cashier-stats">
                            <div class="stat">
                                Total Transaksi: <?= number_format($cashier['total_transactions']) ?>
                            </div>
                            <div class="stat">
                                Transaksi Hari Ini: <?= number_format($cashier['today_transactions']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="cashier-actions">
                        <button class="action-button reset-button" 
                                onclick="showResetPasswordModal(<?= $cashier['id'] ?>, '<?= htmlspecialchars($cashier['username']) ?>')">
                            Reset Password
                        </button>
                        <button class="action-button delete-button" 
                                onclick="confirmDelete(<?= $cashier['id'] ?>, '<?= htmlspecialchars($cashier['username']) ?>')">
                            Hapus
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Cashier Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <button class="close-button" onclick="hideAddModal()">&times;</button>
            <div class="modal-header">
                <h2>Tambah Kasir Baru</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="submit-button">Tambah Kasir</button>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <button class="close-button" onclick="hideResetPasswordModal()">&times;</button>
            <div class="modal-header">
                <h2>Reset Password Kasir</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>

                <button type="submit" class="submit-button">Reset Password</button>
            </form>
        </div>
    </div>

    <script>
    function showAddModal() {
        document.getElementById('addModal').style.display = 'flex';
    }

    function hideAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function showResetPasswordModal(userId, username) {
        document.getElementById('reset_user_id').value = userId;
        document.getElementById('resetPasswordModal').style.display = 'flex';
    }

    function hideResetPasswordModal() {
        document.getElementById('resetPasswordModal').style.display = 'none';
    }

    function confirmDelete(userId, username) {
        if (confirm(`Apakah Anda yakin ingin menghapus kasir "${username}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = 'none';
        }
    }
    </script>
</body>
</html>
