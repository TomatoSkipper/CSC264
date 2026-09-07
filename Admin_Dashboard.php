<?php
session_start();

$host       = "localhost";
$db_name    = "language_learning";
$db_username = "root";
$db_password = "";

$conn = mysqli_connect($host, $db_username, $db_password, $db_name);
if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}

if (!isset($_SESSION['Admin_ID'])) {
    header("Location: login.php");
    exit();
}

$action_message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $target_user_id = (int)$_POST['target_user_id'];

    if(isset($_POST['ban'])){
        $stmt = $conn->prepare("UPDATE user_detail SET Is_Banned = 1
                                WHERE User_ID = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $stmt->close();
        $action_message = "User has been banned Successfully.";
    }

    elseif(isset($_POST['unban'])){
        $stmt = $conn->prepare("UPDATE user_detail SET Is_Banned = 0
                                WHERE User_ID = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $stmt->close();
        $action_message = "User has been unbanned Successfully.";
    }

    elseif(isset($_POST['delete'])){
        $stmt = $conn->prepare("DELETE FROM user_detail
                                WHERE User_ID = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $stmt->close();
        $action_message = "User has been deleted Successfully.";
    }
} 

$users = [];
$result = $conn->query("SELECT u.User_ID, u.Username, u.Email, u.Is_Banned,
                               cp.Language, cp.Current_Level, cp.Coins
                        FROM User_Detail u
                        LEFT JOIN Current_Progress cp ON u.User_ID = cp.User_ID
                        ORDER BY u.User_ID ASC");
while($row = $result->fetch_assoc()){
    $users[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — User Management</title>
  <style>
    :root {
      --bg: #f5f0e8;
      --surface: #fffdf7;
      --border: #1a1a2e;
      --accent-green: #22c55e;
      --accent-green-dark: #16a34a;
      --accent-blue: #3b5bdb;
      --accent-blue-light: #e8eeff;
      --accent-orange: #f97316;
      --accent-orange-dark: #ea580c;
      --text-primary: #1a1a2e;
      --text-secondary: #4a4a6a;
      --text-muted: #8888aa;
      --error-bg: #ffebe9;
      --error-border: #ea4335;
      --radius: 14px;
      --shadow: 5px 5px 0px #1a1a2e;
      --shadow-sm: 2px 2px 0px #1a1a2e;
      --shadow-btn: 3px 3px 0px #1a1a2e;
    }

    * { 
      box-sizing: border-box; 
      margin: 0; 
      padding: 0; 
    }
 
    body {
      font-family: 'Nunito', sans-serif;
      background: var(--bg);
      color: var(--text-primary);
      padding: 40px 20px;
      min-height: 100vh;
    }
 
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .container {
      max-width: 1040px;
      margin: 0 auto;
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: var(--radius);
      padding: 36px;
      box-shadow: var(--shadow);
      animation: cardIn 0.4s ease both;
    }
 
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 32px;
      gap: 20px;
      flex-wrap: wrap;
    }
 
    h2 { 
      font-size: 1.6rem; 
      font-weight: 900;
      letter-spacing: -0.5px;
      color: var(--text-primary); 
    }
 
    .nav-links {
      display: flex;
      align-items: center;
      gap: 12px;
    }
 
    .nav-links a {
      padding: 10px 20px;
      border: 2.5px solid var(--border);
      border-radius: 8px;
      text-decoration: none;
      font-size: 0.95rem;
      font-weight: 800;
      color: var(--text-primary);
      background: var(--surface);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
 
    .nav-links a:hover { 
      background: var(--accent-blue-light); 
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }

    .nav-links a.active { 
      background: var(--accent-blue); 
      color: #ffffff; 
      box-shadow: none;
      transform: none;
    }

    .nav-links a.logout-link {
      background: #f1f5f9;
      color: var(--text-secondary);
      border-color: var(--border);
    }

    .nav-links a.logout-link:hover {
      background: var(--error-bg);
      color: var(--error-border);
    }
 
    .action-message {
      background: #d4edda;
      border: 2.5px solid var(--accent-green-dark);
      color: var(--text-primary);
      padding: 14px 20px;
      border-radius: 8px;
      margin-bottom: 28px;
      font-size: 0.95rem;
      font-weight: 800;
      box-shadow: var(--shadow-sm);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
 
    .table-wrapper {
      width: 100%;
      overflow-x: auto;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      box-shadow: var(--shadow-sm);
      background: var(--surface);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
      text-align: left;
      background: var(--surface);
    }
 
    thead tr { 
      background: var(--accent-blue-light); 
      border-bottom: 2.5px solid var(--border); 
    }

    thead th { 
      padding: 14px 16px; 
      color: var(--text-primary);
      font-weight: 800; 
      text-transform: uppercase;
      font-size: 0.85rem;
      letter-spacing: 0.5px;
    }
 
    tbody tr { 
      border-bottom: 2px solid var(--border); 
    }

    tbody tr:last-child {
      border-bottom: none;
    }

    tbody tr:hover { 
      background: #fbf9f3; 
    }

    tbody td { 
      padding: 14px 16px; 
      color: var(--text-primary); 
      vertical-align: middle; 
      font-weight: 700;
    }

    /* Style identifiers and numbers distinctly */
    tbody td:first-child {
      font-family: 'Space Mono', monospace;
      font-weight: 700;
    }
 
    .badge-banned {
      display: inline-block;
      background: var(--error-bg);
      color: var(--error-border);
      padding: 4px 10px;
      border: 2px solid var(--border);
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 800;
    }
 
    .badge-active {
      display: inline-block;
      background: #e6fcf5;
      color: var(--accent-green-dark);
      padding: 4px 10px;
      border: 2px solid var(--border);
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 800;
    }
 
    .actions { 
      display: flex; 
      gap: 8px; 
    }

    .actions form {
      display: flex;
      gap: 8px;
    }
 
    .btn {
      display: inline-flex;
      align-items: center;
      padding: 8px 14px;
      border: 2px solid var(--border);
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 800;
      cursor: pointer;
      font-family: 'Nunito', sans-serif;
      box-shadow: var(--shadow-sm);
      transition: all 0.1s ease;
    }
 
    .btn-ban { 
      background: var(--accent-orange); 
      color: #ffffff; 
    }
    .btn-ban:hover { 
      background: var(--accent-orange-dark); 
      transform: translate(-1px, -1px);
      box-shadow: var(--shadow-btn);
    }
 
    .btn-unban { 
      background: var(--accent-green); 
      color: #ffffff; 
    }
    .btn-unban:hover { 
      background: var(--accent-green-dark); 
      transform: translate(-1px, -1px);
      box-shadow: var(--shadow-btn);
    }
 
    .btn-delete { 
      background: var(--error-border); 
      color: #ffffff; 
    }
    .btn-delete:hover { 
      background: #b91c1c; 
      transform: translate(-1px, -1px);
      box-shadow: var(--shadow-btn);
    }
 
    .btn:active {
      transform: translate(0, 0);
      box-shadow: none;
    }

    @media (max-width: 840px) {
      .top-bar {
        flex-direction: column;
        align-items: flex-start;
      }
      .nav-links {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
      }
      .container {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
 
<div class="container">
  <div class="top-bar">
    <h2>Admin Dashboard — User Management</h2>
    <div class="nav-links">
      <a href="admin_dashboard.php" class="active">Users</a>
      <a href="Create_question.php">Questions</a>
      <a href="logout.php" class="logout-link">Logout</a>
    </div>
  </div>
 
  <?php if ($action_message): ?>
    <div class="action-message">✅ <?= htmlspecialchars($action_message) ?></div>
  <?php endif; ?>
 
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Email</th>
          <th>Language</th>
          <th>Level</th>
          <th>Coins</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($users)): ?>
          <?php foreach ($users as $user): ?>
            <tr>
              <td><?= $user['User_ID'] ?></td>
              <td><?= htmlspecialchars($user['Username']) ?></td>
              <td><?= htmlspecialchars($user['Email']) ?></td>
              <td><?= $user['Language'] ?? '—' ?></td>
              <td><?= $user['Current_Level'] ?? '—' ?></td>
              <td><?= $user['Coins'] ?? '—' ?></td>
              <td>
                <?php if ($user['Is_Banned']): ?>
                  <span class="badge-banned">Banned</span>
                <?php else: ?>
                  <span class="badge-active">Active</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="actions">
                  <form method="POST" action="admin_dashboard.php">
                    <input type="hidden" name="target_user_id" value="<?= $user['User_ID'] ?>"/>
                    <?php if ($user['Is_Banned']): ?>
                      <button type="submit" name="unban" class="btn btn-unban">Unban</button>
                    <?php else: ?>
                      <button type="submit" name="ban" class="btn btn-ban">Ban</button>
                    <?php endif; ?>
                    <button type="submit" name="delete" class="btn btn-delete"
                            onclick="return confirm('Delete this user permanently?')">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px; font-weight:800;">No users discovered in records database system.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
 
</body>
</html>