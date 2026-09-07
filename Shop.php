<?php
session_start();


$host = "localhost";
$db_name = "language_learning";
$db_username = "root";
$db_password = "";

$conn = mysqli_connect($host, $db_username, $db_password, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// if(user === die){
//     good;
// }

// if(!isset($_SESSION['user_id'])){
//     header("Location: login.php");
//     exit();
// }

$user_id = $_SESSION['User_ID'];

$error = '';
$success = '';
$pfp_folder ='images/Profile_Pic/';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    // Handle form submission
    $stmt = $conn ->prepare("SELECT Coins 
                            FROM Current_Progress
                            WHERE User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_coins = $stmt->get_result()->fetch_assoc()['Coins'];
    $stmt->close();

    //BUy PFP
    if(isset($_POST['buy_pfp'])){
        $pfp_id = (int)$_POST['pfp_id'];

        $stmt = $conn ->prepare("SELECT Coin_Cost 
                                FROM Profile_Picture
                                WHERE PFP_ID = ?");
        $stmt->bind_param("i", $pfp_id);
        $stmt->execute();
        $pfp_cost = $stmt->get_result()->fetch_assoc()['Coin_Cost'];
        $stmt->close();

        //Check Owned
        $stmt = $conn ->prepare("SELECT Pfp_ID
                                FROM User_Pfp
                                WHERE User_ID = ? AND Pfp_ID = ?");
        $stmt->bind_param("ii", $user_id, $pfp_id);
        $stmt->execute();
        $owned = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($owned){
            $error = "You already own this profile picture.";
        }else if($user_coins < $pfp_cost){
            $error = "Not enough coins to purchase profile picture.";
        }else{
            $stmt = $conn ->prepare("UPDATE Current_Progress 
                                    SET Coins = Coins - ?
                                    WHERE User_ID = ?");
            $stmt->bind_param("ii", $pfp_cost, $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn ->prepare("INSERT INTO User_Pfp (User_ID, Pfp_ID)
                                        VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $pfp_id);
            $stmt->execute();
            $stmt->close();

            $success = "Profile picture purchased.";
        }
    }
    elseif(isset($_POST['buy_powerup'])){
        $powerup_id = (int)$_POST['powerup_id'];

        $stmt = $conn ->prepare("SELECT Coin_Cost , PowerUp_Name 
                                FROM Power_Up
                                WHERE PowerUp_ID = ?");
        $stmt->bind_param("i", $powerup_id);
        $stmt->execute();
        $powerup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $coin_cost = $powerup['Coin_Cost'];
        $powerup_name = $powerup['PowerUp_Name'];

        if($user_coins < $coin_cost){
            $error = "Not enough coins! You need {$coin_cost} coins.";
        }else{
            $stmt = $conn -> prepare("UPDATE Current_Progress
                                    SET Coins = Coins - ?
                                    WHERE User_ID = ?");
            $stmt->bind_param("ii", $coin_cost, $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn -> prepare("INSERT INTO User_PowerUp(User_ID, PowerUp_ID, Quantity)
                                        VALUES (?, ?, 1)
                                        ON DUPLICATE KEY UPDATE Quantity = Quantity + 1");
            $stmt->bind_param("ii", $user_id, $powerup_id);
            $stmt->execute();
            $stmt->close();

            $success = "'{$powerup_name}' purchased.";   
        }
    }
}

$stmt = $conn->prepare("SELECT Coins 
                        FROM Current_Progress
                        WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_coins = $stmt->get_result()->fetch_assoc()['Coins'];
$stmt->close();

$stmt = $conn->prepare("SELECT pp.Pfp_ID, pp.Pfp_Name, pp.Pfp_File, pp.Coin_Cost,
                        IF(up.Pfp_ID IS NOT NULL, 1, 0) AS Is_Owned
                        FROM Profile_Picture pp
                        LEFT JOIN User_Pfp up On pp.Pfp_ID = up.Pfp_ID AND up.User_ID =?
                        ORDER BY pp.Pfp_ID ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$all_pfp = [];
while($row = $result->fetch_assoc()){
    $all_pfp[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT p.PowerUp_ID, p.PowerUp_Name, p.PowerUp_Desc, p.Coin_Cost,
                        IFNULL(up.Quantity, 0) AS Quantity
                        FROM Power_Up p
                        LEFT JOIN User_PowerUp up ON p.PowerUp_ID = up.PowerUp_ID AND up.User_ID = ?
                        ORDER BY p.Coin_Cost ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$all_powerups =[];
while($row = $result->fetch_assoc()){
    $all_powerups[] = $row;
}
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Shop</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    /* ── DESIGN TOKENS (matches main page) ── */
    :root {
      --bg: #f5f0e8;
      --surface: #fffdf7;
      --border: #1a1a2e;
      --accent-green: #22c55e;
      --accent-green-dark: #16a34a;
      --accent-blue: #3b5bdb;
      --accent-blue-light: #e8eeff;
      --text-primary: #1a1a2e;
      --text-secondary: #4a4a6a;
      --text-muted: #8888aa;
      --gold: #f59e0b;
      --radius: 14px;
      --shadow: 4px 4px 0px #1a1a2e;
      --shadow-sm: 2px 2px 0px #1a1a2e;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Nunito', sans-serif;
      background: var(--bg);
      color: var(--text-primary);
      min-height: 100vh;
      padding: 40px 24px;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── CONTAINER ── */
    .container {
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 32px;
      max-width: 820px;
      margin: 0 auto;
      animation: cardIn 0.4s ease both;
    }

    /* ── TOP BAR ── */
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
      padding-bottom: 20px;
      border-bottom: 2px solid var(--border);
    }

    .top-bar h2 {
      font-size: 1.6rem;
      font-weight: 900;
      color: var(--text-primary);
      letter-spacing: -0.5px;
    }

    .coins-display {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-family: 'Space Mono', monospace;
      font-size: 1rem;
      font-weight: 700;
      color: var(--gold);
      background: #fffbf0;
      border: 2px solid var(--border);
      border-radius: 99px;
      padding: 7px 18px;
      box-shadow: var(--shadow-sm);
    }

    /* ── MESSAGES ── */
    .error {
      background: #fff0f0;
      border: 2px solid #ff4444;
      border-radius: 8px;
      color: #c0392b;
      font-size: 0.9rem;
      font-weight: 700;
      padding: 12px 16px;
      margin-bottom: 20px;
    }
    .success {
      background: #f0fff4;
      border: 2px solid var(--accent-green);
      border-radius: 8px;
      color: var(--accent-green-dark);
      font-size: 0.9rem;
      font-weight: 700;
      padding: 12px 16px;
      margin-bottom: 20px;
    }

    /* ── TABS ── */
    .tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 28px;
    }

    .tab {
      padding: 9px 24px;
      border: 2.5px solid var(--border);
      border-radius: 9px;
      cursor: pointer;
      font-family: 'Nunito', sans-serif;
      font-size: 0.92rem;
      font-weight: 800;
      color: var(--text-secondary);
      background: var(--surface);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
    .tab:hover:not(.active) {
      background: var(--accent-blue-light);
      color: var(--accent-blue);
      transform: translate(-1px, -1px);
    }
    .tab.active {
      background: var(--text-primary);
      color: #fff;
      border-color: var(--text-primary);
      box-shadow: var(--shadow);
    }

    /* ── TAB CONTENT ── */
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ── PFP GRID ── */
    .pfp-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 16px;
    }

    .pfp-item {
      text-align: center;
      position: relative;
      background: var(--bg);
      border: 2px solid var(--border);
      border-radius: var(--radius);
      padding: 14px 8px 12px;
      box-shadow: var(--shadow-sm);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .pfp-item:hover {
      transform: translate(-2px, -2px);
      box-shadow: var(--shadow);
    }

    .pfp-item img {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      border: 2.5px solid var(--border);
      object-fit: cover;
      display: block;
      margin: 0 auto 8px;
    }

    .pfp-item.is-free img  { border-color: var(--accent-green); }
    .pfp-item.is-owned img { border-color: var(--accent-blue); }
    .pfp-item.is-locked img { filter: grayscale(100%) opacity(50%); }

    .lock-overlay {
      position: absolute;
      top: 14px; left: 50%;
      transform: translateX(-50%);
      width: 72px;
      height: 72px;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      background: rgba(26, 26, 46, 0.55);
      pointer-events: none;
    }
    .lock-overlay span  { font-size: 1.3rem; }
    .lock-overlay small {
      font-family: 'Space Mono', monospace;
      font-size: 0.62rem;
      color: #fff;
      font-weight: 700;
      margin-top: 2px;
    }

    .pfp-name {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--text-secondary);
      margin-bottom: 8px;
    }

    /* ── PFP BUTTONS ── */
    .pfp-btn {
      width: 100%;
      padding: 6px 0;
      border: 2px solid var(--border);
      border-radius: 7px;
      font-family: 'Nunito', sans-serif;
      font-size: 0.75rem;
      font-weight: 800;
      cursor: pointer;
      transition: all 0.15s ease;
      box-shadow: var(--shadow-sm);
    }
    .pfp-btn.btn-owned {
      background: var(--bg);
      color: var(--text-muted);
      cursor: default;
      border-color: #ccc;
      box-shadow: none;
    }
    .pfp-btn.btn-free {
      background: #f0fff4;
      color: var(--accent-green-dark);
      border-color: var(--accent-green);
      cursor: default;
      box-shadow: none;
    }
    .pfp-btn.btn-buy {
      background: var(--gold);
      color: var(--text-primary);
      border-color: var(--border);
    }
    .pfp-btn.btn-buy:hover {
      background: #e08c00;
      transform: translate(-1px, -1px);
      box-shadow: var(--shadow);
    }

    /* ── POWERUP LIST ── */
    .powerup-list {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .powerup-card {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 24px;
      background: var(--bg);
      border: 2.5px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      animation: cardIn 0.4s ease both;
    }
    .powerup-card:hover {
      transform: translate(-2px, -2px);
      box-shadow: var(--shadow);
    }
    .powerup-card:nth-child(1) { animation-delay: .04s; }
    .powerup-card:nth-child(2) { animation-delay: .08s; }
    .powerup-card:nth-child(3) { animation-delay: .12s; }

    .powerup-info h3 {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--text-primary);
      margin-bottom: 4px;
    }
    .powerup-info p {
      font-size: 0.85rem;
      color: var(--text-secondary);
      font-weight: 600;
      margin-bottom: 8px;
      line-height: 1.4;
    }
    .powerup-info .cost {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-family: 'Space Mono', monospace;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--gold);
      background: #fffbf0;
      border: 1.5px solid var(--border);
      border-radius: 99px;
      padding: 3px 12px;
    }

    .powerup-right {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      min-width: 100px;
    }

    .quantity-badge {
      font-family: 'Space Mono', monospace;
      font-size: 0.78rem;
      font-weight: 500;
      background: var(--accent-blue-light);
      border: 2px solid var(--border);
      border-radius: 8px;
      padding: 4px 12px;
      color: var(--accent-blue);
      width: 100%;
      text-align: center;
    }

    .btn-buy-powerup {
      width: 100%;
      padding: 9px 0;
      background: var(--accent-green);
      color: white;
      border: 2.5px solid var(--border);
      border-radius: 9px;
      font-family: 'Nunito', sans-serif;
      font-size: 0.88rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
    .btn-buy-powerup:hover {
      background: var(--accent-green-dark);
      transform: translate(-1px, -1px);
      box-shadow: var(--shadow);
    }
    .btn-buy-powerup:active {
      transform: translate(1px, 1px);
      box-shadow: none;
    }

    /* ── BACK LINK ── */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-top: 28px;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--text-secondary);
      text-decoration: none;
      border: 2px solid var(--border);
      border-radius: 8px;
      padding: 8px 18px;
      background: var(--surface);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
    .back-link:hover {
      background: var(--accent-blue-light);
      color: var(--accent-blue);
      transform: translate(-1px, -1px);
      box-shadow: var(--shadow);
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 680px) {
      .pfp-grid { grid-template-columns: repeat(3, 1fr); }
      body { padding: 20px 12px; }
    }
    @media (max-width: 420px) {
      .pfp-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>
 
<div class="container">
 
  <div class="top-bar">
    <h2>🛒 Shop</h2>
    <div class="coins-display">🪙 <?= $user_coins ?> Coins</div>
  </div>
 
  <?php if ($error):   ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
 
  <!-- ── Tabs ── -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('pfp', this)">🖼️ Profile Pictures</button>
    <button class="tab"        onclick="switchTab('powerup', this)">⚡ Power-Ups</button>
  </div>
 
  <!-- ── PFP Tab ── -->
  <div class="tab-content active" id="tab-pfp">
    <div class="pfp-grid">
      <?php foreach ($all_pfp as $pfp): ?>
        <?php
          $is_free   = ($pfp['Coin_Cost'] == 0);
          $is_owned  = ($pfp['Is_Owned'] == 1);
          $is_locked = !$is_free && !$is_owned;
        ?>
        <div class="pfp-item <?= $is_free ? 'is-free' : ($is_owned ? 'is-owned' : 'is-locked') ?>">
          <img src="<?= $pfp_folder . htmlspecialchars($pfp['Pfp_File']) ?>"
               alt="<?= htmlspecialchars($pfp['Pfp_Name']) ?>" />
 
          <?php if ($is_locked): ?>
            <div class="lock-overlay">
              <span>🔒</span>
              <small><?= $pfp['Coin_Cost'] ?>🪙</small>
            </div>
          <?php endif; ?>
 
          <div class="pfp-name"><?= htmlspecialchars($pfp['Pfp_Name']) ?></div>
 
          <?php if ($is_free): ?>
            <button class="pfp-btn btn-free" disabled>Free</button>
 
          <?php elseif ($is_owned): ?>
            <button class="pfp-btn btn-owned" disabled>✓ Owned</button>
 
          <?php else: ?>
            <form method="POST" action="shop.php">
              <input type="hidden" name="pfp_id" value="<?= $pfp['Pfp_ID'] ?>"/>
              <button type="submit" name="buy_pfp" class="pfp-btn btn-buy"
                      onclick="return confirm('Buy for <?= $pfp['Coin_Cost'] ?> coins?')">
                🪙 <?= $pfp['Coin_Cost'] ?>
              </button>
            </form>
          <?php endif; ?>
 
        </div>
      <?php endforeach; ?>
    </div>
  </div>
 
  <!-- ── Power-Up Tab ── -->
  <div class="tab-content" id="tab-powerup">
    <div class="powerup-list">
      <?php foreach ($all_powerups as $pu): ?>
        <div class="powerup-card">
          <div class="powerup-info">
            <h3><?= htmlspecialchars($pu['PowerUp_Name']) ?></h3>
            <p><?= htmlspecialchars($pu['PowerUp_Desc']) ?></p>
            <div class="cost">🪙 <?= $pu['Coin_Cost'] ?> Coins</div>
          </div>
          <div class="powerup-right">
            <div class="quantity-badge">Owned: <?= $pu['Quantity'] ?></div>
            <form method="POST" action="shop.php">
              <input type="hidden" name="powerup_id" value="<?= $pu['PowerUp_ID'] ?>"/>
              <button type="submit" name="buy_powerup" class="btn-buy-powerup"
                      onclick="return confirm('Buy <?= htmlspecialchars($pu['PowerUp_Name']) ?> for <?= $pu['Coin_Cost'] ?> coins?')">
                Buy
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
 
  <a href="MainPage.php" class="back-link">← Back to Dashboard</a>
 
</div>
 
<script>
  function switchTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
  }
</script>
 
</body>
</html>