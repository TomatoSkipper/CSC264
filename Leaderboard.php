<?php
session_start();

$host = 'localhost';
$db_name = 'language_learning';
$db_username = 'root';
$db_password = '';

$conn = mysqli_connect($host, $db_username, $db_password, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// if(!isset($_SESSION['User_ID'])){
//      header("Location: MainPage.php");
//      exit();
// }

$language_result = $conn->query("SELECT DISTINCT Language 
                                FROM Current_Progress
                                ORDER BY Language ASC");
$languages = [];
while($row = $language_result->fetch_assoc()){
    $languages[] = $row['Language'];
}

$selected_language = isset($_GET['language']) ? $_GET['language'] : $languages[0];

$leaderboard = [];

if($selected_language) {
    $stmt = $conn->prepare("SELECT u.Username, cp.Current_Level, cp.Points,
                            RANK() OVER (ORDER BY cp.Current_Level DESC, cp.Points DESC) AS Rank
                            FROM Current_Progress cp
                            JOIN User_Detail u ON cp.User_ID = u.User_ID
                            WHERE cp.Language = ?
                            ORDER BY cp.Current_Level DESC, cp.Points DESC
                            LIMIT 10");
    $stmt->bind_param("s", $selected_language);
    $stmt->execute();
    $result = $stmt->get_result(); // ← store once
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = $row;
    }
    $stmt->close();
}

$my_rank = null;
$my_level = null;

if($selected_language) {
    $stmt = $conn->prepare("SELECT Rank, Current_Level 
                            FROM (
                                SELECT User_ID, Current_Level, 
                                RANK() OVER (ORDER BY Current_Level DESC) AS Rank
                                FROM Current_Progress
                                WHERE Language = ?
                            ) ranked
                            WHERE User_ID = ?");
    $stmt->bind_param("si", $selected_language, $_SESSION['User_ID']);
    $stmt->execute();
    $my_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if($my_data){
        $my_rank = $my_data['Rank'];
        $my_level = $my_data['Current_Level'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Leaderboard</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
 
    body {
      font-family: Georgia, serif;
      background: #f5f5f5;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 30px 20px;
    }
 
    .container {
      background: #fff;
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 30px;
      max-width: 600px;
      width: 100%;
    }
 
    h2 {
      font-size: 1.4rem;
      color: #222;
      margin-bottom: 6px;
    }
 
    .subtitle {
      font-size: 0.9rem;
      color: #888;
      margin-bottom: 24px;
    }
 
    /* Language tabs */
    .lang-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }
 
    .lang-tab {
      padding: 8px 18px;
      border: 1px solid #ccc;
      border-radius: 4px;
      text-decoration: none;
      font-size: 0.9rem;
      color: #333;
      background: #fff;
      transition: background 0.15s;
    }
 
    .lang-tab:hover    { background: #f0f0f0; }
    .lang-tab.active   { background: #333; color: #fff; border-color: #333; }
 
    /* My rank banner */
    .my-rank {
      background: #f0f0f0;
      border: 1px solid #ccc;
      border-radius: 4px;
      padding: 12px 16px;
      margin-bottom: 20px;
      font-size: 0.9rem;
      color: #333;
    }
 
    .my-rank span {
      font-weight: bold;
    }
 
    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
    }
 
    thead tr {
      background: #333;
      color: #fff;
    }
 
    thead th {
      padding: 12px 16px;
      text-align: left;
      font-weight: normal;
    }
 
    tbody tr {
      border-bottom: 1px solid #eee;
      transition: background 0.1s;
    }
 
    tbody tr:hover { background: #fafafa; }
 
    tbody td {
      padding: 12px 16px;
      color: #333;
    }
 
    /* Highlight current user */
    tbody tr.is-me {
      background: #fffbea;
      font-weight: bold;
    }
 
    /* Top 3 rank colors */
    .rank-1 { color: #FFD700; font-weight: bold; font-size: 1.1rem; }
    .rank-2 { color: #C0C0C0; font-weight: bold; font-size: 1.1rem; }
    .rank-3 { color: #CD7F32; font-weight: bold; font-size: 1.1rem; }
 
    .no-data {
      text-align: center;
      color: #888;
      padding: 30px 0;
      font-size: 0.95rem;
    }
 
    .back-link {
      display: inline-block;
      margin-top: 20px;
      font-size: 0.9rem;
      color: #333;
      text-decoration: underline;
    }
  </style>
</head>
<body>
 
<div class="container">
  <h2>Leaderboard</h2>
  <p class="subtitle">Top 10 players ranked by level per language.</p>
 
  <!-- Language Tabs -->
  <div class="lang-tabs">
    <?php foreach ($languages as $lang): ?>
      <a href="leaderboard.php?language=<?= urlencode($lang) ?>"
         class="lang-tab <?= $lang === $selected_language ? 'active' : '' ?>">
        <?= htmlspecialchars($lang) ?>
      </a>
    <?php endforeach; ?>
  </div>
 
  <!-- My Rank Banner -->
  <?php if ($my_rank): ?>
    <div class="my-rank">
      Your rank in <span><?= htmlspecialchars($selected_language) ?></span>:
      <span>#<?= $my_rank ?></span> — Level <span><?= $my_level ?></span>
    </div>
  <?php endif; ?>
 
  <!-- Leaderboard Table -->
  <?php if (!empty($leaderboard)): ?>
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Username</th>
          <th>Level</th>
          <th>Points</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leaderboard as $entry): ?>
          <?php $is_me =(strcasecmp($entry['Username'], $_SESSION['username']) === 0);?>
          <tr class="<?= $is_me ? 'is-me' : '' ?>">
            <td>
              <?php
                $rank = $entry['Rank'];
                if      ($rank == 1) echo "<span class='rank-1'>No.1 </span>";
                elseif  ($rank == 2) echo "<span class='rank-2'>No.2 </span>";
                elseif  ($rank == 3) echo "<span class='rank-3'>No.3 </span>";
                else                 echo "#" . $rank;
              ?>
            </td>
            <td><?= htmlspecialchars($entry['Username']) ?> <?= $is_me ? '(You)' : '' ?></td>
            <td><?= $entry['Current_Level'] ?></td>
            <td><?= $entry['Points'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="no-data">No players found for <?= htmlspecialchars($selected_language) ?> yet.</div>
  <?php endif; ?>
 
  <a href="MainPage.php" class="back-link">← Back to Dashboard</a>
</div>
 
</body>
</html>