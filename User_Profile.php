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

if(!isset($_SESSION['User_ID'])){
    header("Location: register.php");
    exit();
}

$user_id = $_SESSION['User_ID'];
$pfp_folder = 'images/Profile_Pic/';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(isset($_POST['update_profile'])){
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        $new_password = $_POST['password'];

        if(empty($new_username) || empty($new_email)){
            $error = "Username and email cannot be empty.";
        } else{
            $stmt = $conn->prepare("SELECT User_ID 
                                    FROM user_detail 
                                    WHERE Username = ? AND User_ID != ?");
            $stmt->bind_param("si", $new_username, $user_id);
            $stmt->execute();
            $dup_username = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if($dup_username){
                $error = "Username already exists.";
            } else{
                if(!empty($new_password)){
                    $stmt = $conn->prepare("UPDATE user_detail 
                                            SET Username = ?, Email = ?, Password = ? 
                                            WHERE User_ID = ?");
                    $stmt->bind_param("sssi", $new_username, $new_email, $new_password, $user_id);
                } else{
                    $stmt = $conn->prepare("UPDATE user_detail 
                                            SET Username = ?, Email = ? 
                                            WHERE User_ID = ?");
                    $stmt->bind_param("ssi", $new_username, $new_email, $user_id);
                }
                $stmt->execute();
                $stmt->close();

                $_SESSION['username'] = $new_username;
                $success = "Profile updated successfully.";
            }
        }
    }
    elseif(isset($_POST['equip_pfp'])){
        $pfp_id = (int)$_POST['pfp_id'];

        $stmt = $conn->prepare("SELECT Pfp_ID 
                                FROM User_Pfp
                                WHERE User_ID = ? AND Pfp_ID = ?");
        $stmt->bind_param("ii", $user_id, $pfp_id);
        $stmt->execute();
        $owned = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT Coin_Cost 
                                FROM Profile_Picture 
                                WHERE Pfp_ID = ?");
        $stmt->bind_param("i", $pfp_id);
        $stmt->execute();
        $pfp_cost = $stmt->get_result()->fetch_assoc()['Coin_Cost'];
        $stmt->close();

        if($owned || $pfp_cost == 0){
            $stmt = $conn->prepare("UPDATE User_Detail
                                    SET Current_Pfp = ?
                                    WHERE User_ID = ?");
            $stmt->bind_param("ii", $pfp_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $success = "Profile picture updated.";
        } else {
            $error = "You do not own this profile picture.";
        }
    }
    elseif(isset($_POST['buy_pfp'])){
        $pfp_id = (int)$_POST['pfp_id'];

        $stmt = $conn->prepare("SELECT Coin_Cost 
                                FROM Profile_Picture
                                WHERE Pfp_ID = ?");
        $stmt->bind_param("i", $pfp_id);
        $stmt->execute();
        $coin_cost = $stmt->get_result()->fetch_assoc()['Coin_Cost'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT Coins 
                                FROM Current_Progress
                                WHERE User_ID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_coins = $stmt->get_result()->fetch_assoc()['Coins'];
        $stmt->close();

        if($user_coins < $coin_cost){
            $error = "You do not have enough coins to buy this profile picture.";
        } else {
            $new_coin_balance = $user_coins - $coin_cost;
            $stmt = $conn->prepare("UPDATE Current_Progress 
                                    SET Coins = ? 
                                    WHERE User_ID = ?");
            $stmt->bind_param("ii", $new_coin_balance, $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO User_Pfp (User_ID, Pfp_ID) 
                                    VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $pfp_id);
            $stmt->execute();
            $stmt->close();

            $success = "Profile picture purchased.";
        }
    } elseif (isset($_POST['delete_account'])){
        $stmt = $conn->prepare("DELETE FROM User_Detail
                                WHERE User_ID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        session_unset();
        session_destroy();
        header("Location: Register.php");
        exit();
    }
}

$stmt = $conn->prepare("SELECT Username, Email, Password, Current_Pfp
                        FROM user_detail 
                        WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT pp.Pfp_ID, pp.Pfp_Name, pp.Pfp_File, pp.Coin_Cost,
                        IF(up.Pfp_ID IS NOT NULL, 1, 0) AS Is_Owned
                        FROM Profile_Picture pp
                        LEFT JOIN User_Pfp up ON pp.Pfp_ID = up.Pfp_ID AND up.User_ID = ?
                        ORDER BY pp.Pfp_ID ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$all_pfp = [];
while ($row = $result->fetch_assoc()) {
    $all_pfp[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT a.Achievement_Name, a.Badge
                        FROM User_Achievement ua
                        JOIN Achievement a ON ua.Achievement_ID = a.Achievement_ID
                        WHERE ua.User_ID = ?
                        ORDER BY ua.Achievement_ID DESC
                        LIMIT 3");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_badges = [];
while($row = $result->fetch_assoc()){
    $user_badges[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profile — Learning Language</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:               #f5f0e8;
      --surface:          #fffdf7;
      --border:           #1a1a2e;
      --accent-green:     #22c55e;
      --accent-green-dk:  #16a34a;
      --accent-blue:      #3b5bdb;
      --accent-blue-lt:   #e8eeff;
      --accent-red:       #ef4444;
      --accent-red-dk:    #b91c1c;
      --accent-orange:    #f97316;
      --accent-orange-dk: #c2410c;
      --gold:             #f59e0b;
      --text-primary:     #1a1a2e;
      --text-secondary:   #4a4a6a;
      --text-muted:       #8888aa;
      --radius:           14px;
      --shadow:           4px 4px 0px #1a1a2e;
      --shadow-sm:        2px 2px 0px #1a1a2e;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Nunito', sans-serif;
      background: var(--bg);
      color: var(--text-primary);
      min-height: 100vh;
      padding: 32px 20px;
    }

    /* ── BACK LINK ── */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 800;
      font-size: 0.88rem;
      color: var(--accent-blue);
      text-decoration: none;
      margin-bottom: 24px;
      padding: 8px 16px;
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: 9px;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
    .back-link:hover {
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0 var(--border);
    }

    /* ── CONTAINER ── */
    .container {
      max-width: 740px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    /* ── CARD BASE ── */
    .card {
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 24px;
      position: relative;
      overflow: hidden;
      animation: cardIn 0.35s ease both;
    }
    .card:nth-child(2) { animation-delay: 0.05s; }
    .card:nth-child(3) { animation-delay: 0.10s; }
    .card:nth-child(4) { animation-delay: 0.15s; }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── SECTION TITLE ── */
    .section-title {
      font-size: 0.75rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--text-muted);
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .section-title::after {
      content: '';
      flex: 1;
      height: 2px;
      background: var(--bg);
      border-radius: 2px;
    }

    /* ── MESSAGES ── */
    .msg {
      font-size: 0.88rem;
      font-weight: 800;
      padding: 12px 18px;
      border-radius: 10px;
      border: 2px solid;
      box-shadow: var(--shadow-sm);
    }
    .msg-error   { background: #fff0f0; color: var(--accent-red-dk);    border-color: var(--accent-red); }
    .msg-success { background: #f0fff4; color: var(--accent-green-dk);  border-color: var(--accent-green); }

    /* ── PROFILE HEADER CARD ── */
    .profile-header-card {
      background: var(--accent-blue-lt);
      border-color: var(--accent-blue);
      box-shadow: 4px 4px 0 var(--accent-blue);
    }
    .profile-header-inner {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .current-pfp {
      position: relative;
      flex-shrink: 0;
    }
    .current-pfp img {
      width: 88px;
      height: 88px;
      border-radius: 50%;
      border: 3px solid var(--border);
      object-fit: cover;
      box-shadow: var(--shadow);
      cursor: zoom-in;
      display: block;
    }

    .profile-info h2 {
      font-size: 1.5rem;
      font-weight: 900;
      color: var(--text-primary);
      letter-spacing: -0.5px;
    }
    .profile-info .profile-tag {
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--accent-blue);
      margin-top: 4px;
    }

    /* ── FORM FIELDS ── */
    .field { margin-bottom: 16px; }

    .field label {
      display: block;
      font-size: 0.78rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--text-muted);
      margin-bottom: 7px;
    }

    .field input {
      width: 100%;
      padding: 11px 16px;
      border: 2.5px solid var(--border);
      border-radius: 9px;
      font-size: 0.92rem;
      font-family: 'Nunito', sans-serif;
      font-weight: 600;
      background: var(--bg);
      color: var(--text-primary);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
    .field input:focus {
      outline: none;
      border-color: var(--accent-blue);
      background: var(--accent-blue-lt);
      box-shadow: 2px 2px 0 var(--accent-blue);
    }
    .field input::placeholder { color: var(--text-muted); }

    .hint {
      font-size: 0.75rem;
      color: var(--text-muted);
      font-weight: 600;
      margin-top: 5px;
    }

    /* ── BUTTONS ── */
    .btn-row { display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap; }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 10px 22px;
      border-radius: 9px;
      border: 2.5px solid var(--border);
      font-family: 'Nunito', sans-serif;
      font-weight: 800;
      font-size: 0.9rem;
      cursor: pointer;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
    .btn:hover   { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 var(--border); }
    .btn:active  { transform: translate(1px, 1px);   box-shadow: 1px 1px 0 var(--border); }

    .btn-save   { background: var(--text-primary); color: #fff; }
    .btn-save:hover { background: #2e2e4a; }

    .btn-delete { background: var(--accent-red); color: #fff; border-color: var(--accent-red-dk); box-shadow: 2px 2px 0 var(--accent-red-dk); }
    .btn-delete:hover { background: var(--accent-red-dk); box-shadow: 3px 3px 0 var(--accent-red-dk); }

    /* ── PFP GRID ── */
    .pfp-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 16px;
    }

    .pfp-item {
      text-align: center;
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 7px;
    }

    .pfp-item img {
      width: 76px;
      height: 76px;
      border-radius: 50%;
      border: 2.5px solid var(--border);
      object-fit: cover;
      display: block;
      box-shadow: var(--shadow-sm);
      cursor: zoom-in;
      transition: transform 0.15s ease;
    }
    .pfp-item img:hover { transform: translate(-1px, -1px); }

    .pfp-item.is-active img {
      border-color: var(--accent-blue);
      border-width: 3px;
      box-shadow: 2px 2px 0 var(--accent-blue);
    }

    .pfp-item.is-locked img {
      filter: grayscale(100%) opacity(45%);
    }

    .lock-overlay {
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 76px;
      height: 76px;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      background: rgba(26, 26, 46, 0.55);
      pointer-events: none;
    }
    .lock-overlay span  { font-size: 1.3rem; }
    .lock-overlay small { font-size: 0.62rem; color: #fff; font-weight: 800; margin-top: 2px; font-family: 'Space Mono', monospace; }

    .pfp-name {
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--text-secondary);
    }

    /* PFP action buttons */
    .pfp-btn {
      width: 120%;
      padding: 5px 0;
      border: 2px solid var(--border);
      border-radius: 7px;
      font-family: 'Nunito', sans-serif;
      font-size: 0.72rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 2px 2px 0 var(--border);
      transition: all 0.12s ease;
    }
    .pfp-btn:hover  { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 var(--border); }
    .pfp-btn:active { transform: translate(1px, 1px);   box-shadow: 1px 1px 0 var(--border); }

    .btn-equip  { background: var(--text-primary); color: #fff; }
    .btn-equip:hover { background: #2e2e4a; }

    .btn-active {
      background: #f0fff4;
      color: var(--accent-green-dk);
      border-color: var(--accent-green);
      box-shadow: 2px 2px 0 var(--accent-green-dk);
      cursor: default;
    }
    .btn-active:hover { transform: none; box-shadow: 2px 2px 0 var(--accent-green-dk); }

    .btn-buy {
      background: var(--accent-orange);
      color: #fff;
      border-color: var(--accent-orange-dk);
      box-shadow: 2px 2px 0 var(--accent-orange-dk);
    }
    .btn-buy:hover { background: var(--accent-orange-dk); box-shadow: 3px 3px 0 var(--accent-orange-dk); }

    /* ── BADGE GRID ── */
    .badge-grid {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }

    .badge-item {
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }

    .badge-item img {
      width: 76px;
      height: 76px;
      object-fit: cover;
      border-radius: 50%;
      border: 2.5px solid var(--border);
      box-shadow: var(--shadow-sm);
      background: #fffbf0;
      cursor: zoom-in;
      transition: transform 0.15s ease;
    }
    .badge-item img:hover { transform: translate(-1px, -1px); }

    /* gold accent bar on badge items */
    .badge-wrap {
      position: relative;
      display: inline-block;
    }
    .badge-wrap::after {
      content: '';
      position: absolute;
      bottom: -4px;
      left: 50%;
      transform: translateX(-50%);
      width: 60%;
      height: 3px;
      background: var(--gold);
      border-radius: 2px;
    }

    .badge-name {
      font-size: 0.72rem;
      font-weight: 800;
      color: var(--text-secondary);
      margin-top: 4px;
    }

    .no-badges {
      font-size: 0.88rem;
      color: var(--text-muted);
      font-weight: 600;
      padding: 16px 0;
    }

    /* ── IMAGE MODAL ── */
    .image-modal {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(26,26,46,0.75);
      justify-content: center;
      align-items: center;
      z-index: 9999;
      cursor: zoom-out;
      backdrop-filter: blur(2px);
    }
    .image-modal.show { display: flex; }
    .image-modal img {
      max-width: 80%;
      max-height: 80%;
      border-radius: 14px;
      border: 4px solid var(--surface);
      box-shadow: 8px 8px 0 var(--border);
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 600px) {
      .pfp-grid { grid-template-columns: repeat(3, 1fr); }
      .pfp-item img, .lock-overlay { width: 68px; height: 68px; }
    }
    @media (max-width: 400px) {
      .pfp-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

  <div class="container">

    <a href="MainPage.php" class="back-link">← Back to Dashboard</a>

    <?php if ($error):   ?><div class="msg msg-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg msg-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- ── PROFILE HEADER ── -->
    <div class="card profile-header-card">
      <div class="profile-header-inner">
        <div class="current-pfp">
          <?php
            $active_pfp_file = 'Jimbo.png';
            foreach ($all_pfp as $pfp) {
                if ($pfp['Pfp_ID'] == $user['Current_Pfp']) {
                    $active_pfp_file = $pfp['Pfp_File'];
                    break;
                }
            }
          ?>
          <img src="<?= $pfp_folder . htmlspecialchars($active_pfp_file) ?>"
               alt="Profile Picture"
               onclick="enlargeImage(this.src)" />
        </div>
        <div class="profile-info">
          <h2><?= htmlspecialchars($user['Username']) ?></h2>
          <div class="profile-tag">👤 Your Profile</div>
        </div>
      </div>
    </div>

    <!-- ── EDIT PROFILE ── -->
    <div class="card">
      <div class="section-title">✏️ Edit Profile</div>

      <form method="POST" action="user_profile.php">
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" value="<?= htmlspecialchars($user['Username']) ?>" required />
        </div>

        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($user['Email']) ?>" required />
        </div>

        <div class="field">
          <label>New Password</label>
          <input type="password" name="password" placeholder="Leave blank to keep current password" />
          <div class="hint">Only fill this in if you want to change your password.</div>
        </div>

        <div class="btn-row">
          <button type="submit" name="update_profile" class="btn btn-save">
            💾 Save Changes
          </button>
          <button type="submit" name="delete_account" class="btn btn-delete"
                  onclick="return confirm('Are you sure you want to delete your account? This cannot be undone.')">
            🗑️ Delete Account
          </button>
        </div>
      </form>
    </div>

    <!-- ── PROFILE PICTURES ── -->
    <div class="card">
      <div class="section-title">🖼️ Profile Pictures</div>

      <div class="pfp-grid">
        <?php foreach ($all_pfp as $pfp): ?>
          <?php
            $is_active = ($pfp['Pfp_ID'] == $user['Current_Pfp']);
            $is_owned  = ($pfp['Is_Owned'] == 1) || ($pfp['Coin_Cost'] == 0);
            $is_locked = !$is_owned;
          ?>
          <div class="pfp-item <?= $is_active ? 'is-active' : '' ?> <?= $is_locked ? 'is-locked' : '' ?>">

            <img src="<?= $pfp_folder . htmlspecialchars($pfp['Pfp_File']) ?>"
                 alt="<?= htmlspecialchars($pfp['Pfp_Name']) ?>"
                 onclick="enlargeImage(this.src)" />

            <?php if ($is_locked): ?>
              <div class="lock-overlay">
                <span>🔒</span>
                <small><?= $pfp['Coin_Cost'] ?>🪙</small>
              </div>
            <?php endif; ?>

            <div class="pfp-name"><?= htmlspecialchars($pfp['Pfp_Name']) ?></div>

            <?php if ($is_active): ?>
              <button class="pfp-btn btn-active" disabled>✓ Equipped</button>

            <?php elseif ($is_owned): ?>
              <form method="POST" action="user_profile.php">
                <input type="hidden" name="pfp_id" value="<?= $pfp['Pfp_ID'] ?>"/>
                <button type="submit" name="equip_pfp" class="pfp-btn btn-equip">Equip</button>
              </form>

            <?php else: ?>
              <form method="POST" action="user_profile.php">
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

    <!-- ── RECENT BADGES ── -->
    <div class="card">
      <div class="section-title">🎖️ Recent Badges</div>

      <?php if (!empty($user_badges)): ?>
        <div class="badge-grid">
          <?php foreach ($user_badges as $badge): ?>
            <div class="badge-item">
              <div class="badge-wrap">
                <img src="images/badges/<?= htmlspecialchars($badge['Badge']) ?>"
                     alt="<?= htmlspecialchars($badge['Achievement_Name']) ?>"
                     onclick="enlargeImage(this.src)" />
              </div>
              <div class="badge-name"><?= htmlspecialchars($badge['Achievement_Name']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="no-badges">No badges earned yet. Complete a Monthly Challenge to earn your first badge!</p>
      <?php endif; ?>
    </div>

  </div><!-- end .container -->

  <!-- IMAGE MODAL -->
  <div id="image-modal" class="image-modal" onclick="this.classList.remove('show')">
    <img id="modal-img" src="" alt="" />
  </div>

  <script>
    function enlargeImage(src) {
      document.getElementById('modal-img').src = src;
      document.getElementById('image-modal').classList.add('show');
    }
  </script>

</body>
</html>