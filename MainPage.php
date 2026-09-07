<?php
session_start();

$host = "localhost";
$db_name = "language_learning";
$db_username = "root";
$db_password = "";

$conn = mysqli_connect($host,$db_username,$db_password, $db_name);
if(!$conn){
  die("Connection Failed: ". mysqli_connect_error());
}

if(!isset($_SESSION['User_ID'])){
  header("Location:LogIn.php");
  exit();
}

$user_id = $_SESSION['User_ID'];

$stmt = $conn ->prepare("SELECT u.Username, cp.Language, cp.Current_Level,cp.Coins, cp.Points
                        FROM User_Detail u
                        LEFT JOIN Current_Progress cp ON u.User_ID = cp.User_ID
                        WHERE u.User_ID = ?");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$username = $user['Username'];
$language = $user['Language'];
$current_level = $user['Current_Level'];
$coins = $user['Coins'];

$this_month = date('Y-m-01');
$challenge = null;
$already_done = false;

if($language){
  $stmt = $conn->prepare("SELECT Challenge_ID, Challenge_Name, Badge_Name
                          FROM Monthly_Challenge
                          WHERE Challenge_Month = ? AND Language = ?");
  $stmt->bind_param("ss", $this_month, $language);
  $stmt->execute();
  $challenge = $stmt->get_result()->fetch_assoc();
  $stmt->close();;

  if($challenge){
    $stmt =$conn->prepare("SELECT Score
                          FROM MC_User_Attempt
                          WHERE User_ID =? AND Challenge_ID = ?");
    $stmt->bind_param("ii", $user_id, $challenge['Challenge_ID']);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $already_done = !empty($attempt);
  }
}

$languages_result = $conn->query("SELECT DISTINCT Language
                                FROM Current_Progress 
                                ORDER BY Language ASC");
$languages = [];
while($row = $languages_result->fetch_assoc()){
  $languages[] = $row['Language'];
}

$selected_language = isset($_GET['language']) ? $_GET['language'] : ($language ?? ($languages[0] ?? ''));

$leaderboard = [];
$my_rank = null;
$my_level = null;

if($selected_language){
  $stmt = $conn->prepare("SELECT u.Username, cp.Current_Level, cp.Points,
                          RANK() OVER (ORDER BY cp.Current_Level DESC, cp.Points DESC) AS Rank
                          FROM Current_Progress cp
                          JOIN User_Detail u ON cp.User_ID = u.User_ID
                          WHERE cp.Language = ?
                          ORDER BY cp.Current_Level DESC, cp.Points DESC
                          LIMIT 10");
  $stmt->bind_param("s", $selected_language);
  $stmt->execute();
  $result = $stmt->get_result();
  while($row = $result->fetch_assoc()){
    $leaderboard[] = $row;
  }
  $stmt->close();

  $stmt = $conn->prepare("SELECT Rank, Current_Level
                          FROM
                            (SELECT cp.User_ID, cp.Current_Level,
                              RANK() OVER (ORDER BY cp.Current_Level DESC, cp.Points DESC) AS RANK
                              FROM Current_Progress cp
                              WHERE cp.Language =?
                            ) ranked WHERE User_ID = ?");
  $stmt->bind_param("si", $selected_language, $user_id);
  $stmt->execute();
  $my_data = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if($my_data){
    $my_rank = $my_data['Rank'];
    $my_level = $my_data['Current_Level'];
  }
}

$stmt = $conn->prepare("SELECT a.Achievement_Name, a.Badge
                        FROM User_Achievement ua
                        JOIN Achievement a ON ua.Achievement_ID = a.Achievement_ID
                        WHERE ua.USER_ID = ?
                        ORDER BY ua.Achievement_ID DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result =  $stmt->get_result();
$achievements = [];
while($row = $result->fetch_assoc()){
  $achievements[] = $row;
}
$stmt->close();

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learning Language</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
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
    --silver: #94a3b8;
    --bronze: #c2805a;
    --sidebar-w: 200px;
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
    display: flex;
    flex-direction: column;
  }
 
  /* ── HEADER ── */
  header {
    background: var(--surface);
    border-bottom: 2.5px solid var(--border);
    padding: 18px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .header-left h1 { font-size: 1.5rem; font-weight: 900; color: var(--text-primary); letter-spacing: -0.5px; }
  .header-left .greeting { font-size: 0.95rem; font-weight: 700; color: var(--accent-blue); margin-top: 2px; }
 
  .header-right { display: flex; align-items: center; gap: 12px; }
 
  .btn-logout {
    background: transparent;
    border: 2.5px solid var(--accent-green);
    color: var(--accent-green-dark);
    font-family: 'Nunito', sans-serif;
    font-weight: 800; font-size: 0.9rem;
    padding: 8px 22px; border-radius: 8px;
    cursor: pointer; box-shadow: var(--shadow-sm);
    transition: all 0.15s ease; text-decoration: none;
    display: inline-block;
  }
  .btn-logout:hover {
    background: var(--accent-green); color: white;
    transform: translate(-1px,-1px); box-shadow: 3px 3px 0px var(--accent-green-dark);
  }
 
  .btn-profile {
    background: var(--accent-blue-light);
    border: 2.5px solid var(--border);
    color: var(--accent-blue);
    font-family: 'Nunito', sans-serif;
    font-weight: 800; font-size: 0.9rem;
    padding: 8px 18px; border-radius: 8px;
    cursor: pointer; box-shadow: var(--shadow-sm);
    transition: all 0.15s ease; text-decoration: none;
    display: inline-block;
  }
  .btn-profile:hover { transform: translate(-1px,-1px); box-shadow: 3px 3px 0px var(--border); }
 
  /* ── LAYOUT ── */
  .layout { display: flex; flex: 1; }
 
  /* ── SIDEBAR ── */
  aside {
    width: var(--sidebar-w);
    background: var(--surface);
    border-right: 2.5px solid var(--border);
    padding: 32px 0;
    display: flex; flex-direction: column; gap: 4px;
    flex-shrink: 0;
  }
  .nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 24px; font-weight: 700; font-size: 1rem;
    color: var(--text-secondary); cursor: pointer;
    border-radius: 0 10px 10px 0; margin-right: 16px;
    transition: all 0.15s ease; position: relative; text-decoration: none;
  }
  .nav-item .icon { font-size: 1.2rem; width: 24px; text-align: center; }
  .nav-item:hover { background: var(--accent-blue-light); color: var(--accent-blue); }
  .nav-item.active { background: var(--accent-blue-light); color: var(--accent-blue); font-weight: 800; }
  .nav-item.active::before {
    content: ''; position: absolute; left: 0; top: 4px; bottom: 4px;
    width: 4px; background: var(--accent-blue); border-radius: 0 4px 4px 0;
  }
 
  /* ── PAGES ── */
  .page { display: none; flex: 1; flex-direction: column; }
  .page.active { display: flex; }
 
  /* ── MAIN ── */
  main {
    flex: 1; padding: 32px;
    display: flex; flex-direction: column; gap: 24px; overflow-y: auto;
  }
 
  /* ── CARD BASE ── */
  .card {
    background: var(--surface);
    border: 2.5px solid var(--border); border-radius: var(--radius);
    box-shadow: var(--shadow); padding: 24px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    position: relative; overflow: hidden;
    animation: cardIn 0.4s ease both;
  }
  .card:hover { transform: translate(-2px,-2px); box-shadow: 6px 6px 0px var(--border); }
 
  @keyframes cardIn {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
  }
 
  .card-title {
    font-size: 0.85rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1px; color: var(--text-muted); margin-bottom: 12px;
  }
 
  /* ── DASHBOARD CARDS ── */
  .top-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
 
  .coin-card { background: #fffbf0; }
  .coin-card::after {
    content: '🪙'; position: absolute; right: 20px; top: 18px;
    font-size: 2.5rem; opacity: 0.25;
  }
  .coin-amount { font-family:'Space Mono',monospace; font-size:2.8rem; font-weight:700; color:var(--gold); line-height:1; margin-bottom:8px; }
  .coin-label { font-size:0.85rem; color:var(--text-muted); font-weight:600; }
 
  .level-card { background: var(--accent-blue-light); }
  .level-badge {
    display:inline-flex; align-items:center; gap:8px;
    background:var(--accent-blue); color:white; font-weight:800; font-size:1.1rem;
    padding:6px 16px; border-radius:8px; border:2px solid var(--border);
    box-shadow:var(--shadow-sm); margin-bottom:16px;
  }
  .level-desc { font-size:0.9rem; color:var(--text-secondary); font-weight:600; margin-bottom:20px; line-height:1.5; }
 
  .challenge-card { background:#f0fff4; border-color:var(--accent-green); box-shadow:4px 4px 0px var(--accent-green-dark); }
  .challenge-card:hover { box-shadow:6px 6px 0px var(--accent-green-dark); }
  .challenge-header { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
  .challenge-badge-label {
    background:var(--accent-green); color:white; font-size:0.7rem; font-weight:800;
    text-transform:uppercase; letter-spacing:1px; padding:3px 10px;
    border-radius:99px; border:1.5px solid var(--border);
  }
  .challenge-desc { font-size:0.9rem; color:var(--text-secondary); font-weight:600; line-height:1.5; margin-bottom:20px; }
  .challenge-done { font-size:0.9rem; color:var(--accent-green-dark); font-weight:800; margin-bottom:16px; }
 
  .btn-start {
    display:inline-flex; align-items:center; gap:8px;
    background:var(--accent-green); color:white;
    border:2.5px solid var(--border); border-radius:9px;
    font-family:'Nunito',sans-serif; font-weight:800; font-size:0.95rem;
    padding:10px 24px; cursor:pointer; box-shadow:var(--shadow-sm);
    transition:all 0.15s ease; text-decoration: none;
  }
  .btn-start:hover { background:var(--accent-green-dark); transform:translate(-1px,-1px); box-shadow:3px 3px 0px var(--border); }
  .btn-start:active { transform:translate(1px,1px); box-shadow:1px 1px 0px var(--border); }
 
  /* ── LEADERBOARD ── */
  .lb-header-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; }
  .lb-page-title { font-size:1.6rem; font-weight:900; letter-spacing:-0.5px; }
 
  .lang-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
  .lang-tab {
    padding:7px 18px; border:2px solid var(--border); border-radius:8px;
    font-family:'Nunito',sans-serif; font-weight:700; font-size:0.85rem;
    cursor:pointer; background:var(--surface); color:var(--text-primary);
    box-shadow:var(--shadow-sm); transition:all 0.15s ease; text-decoration:none;
  }
  .lang-tab:hover { background:var(--accent-blue-light); }
  .lang-tab.active { background:var(--accent-blue); color:white; border-color:var(--accent-blue); }
 
  .my-rank-banner {
    background:var(--accent-blue-light); border:2px solid var(--accent-blue);
    border-radius:10px; padding:10px 18px; margin-bottom:16px;
    font-size:0.9rem; font-weight:700; color:var(--accent-blue);
  }
 
  .lb-table { width:100%; border-collapse:separate; border-spacing:0 8px; }
  .lb-table thead th {
    font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:1px;
    color:var(--text-muted); padding:0 16px 4px; text-align:left;
  }
  .lb-table thead th:last-child { text-align:right; }
 
  .lb-row { background:var(--surface); transition:transform 0.12s ease; animation:cardIn 0.4s ease both; }
  .lb-row:hover { transform:translate(-2px,-2px); }
  .lb-row td {
    padding:14px 16px; border:2px solid var(--border);
    border-left:none; border-right:none;
  }
  .lb-row td:first-child { border-left:2px solid var(--border); border-radius:10px 0 0 10px; padding-left:20px; }
  .lb-row td:last-child  { border-right:2px solid var(--border); border-radius:0 10px 10px 0; text-align:right; padding-right:20px; }
  .lb-row.me { background:var(--accent-blue-light); }
  .lb-row.me td { border-color:var(--accent-blue); }
 
  .rank-num { font-family:'Space Mono',monospace; font-weight:700; font-size:0.9rem; color:var(--text-muted); width:32px; display:inline-block; text-align:center; }
  .rank-medal { font-size:1.1rem; }
  .player-cell { display:flex; align-items:center; gap:12px; }
  .player-name { font-weight:800; font-size:0.95rem; }
  .player-tag  { font-size:0.75rem; color:var(--text-muted); font-weight:600; }
  .lb-score { font-family:'Space Mono',monospace; font-weight:700; color:var(--accent-blue); font-size:0.95rem; }
  .you-badge {
    background:var(--accent-blue); color:white; font-size:0.65rem; font-weight:800;
    text-transform:uppercase; letter-spacing:1px; padding:2px 8px;
    border-radius:99px; border:1.5px solid var(--border); margin-left:6px;
  }
 
  /* ── ACHIEVEMENT ── */
  .ach-section-title { font-size:1.1rem; font-weight:900; letter-spacing:-0.3px; margin-bottom:16px; }
  .ach-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; }
 
  .ach-badge-card {
    background:var(--surface); border:2.5px solid var(--border);
    border-radius:var(--radius); box-shadow:var(--shadow);
    padding:22px 18px; display:flex; flex-direction:column; align-items:center; gap:10px;
    text-align:center; position:relative; overflow:hidden;
    transition:transform 0.15s ease, box-shadow 0.15s ease;
    animation:cardIn 0.4s ease both;
  }
  .ach-badge-card:hover { transform:translate(-2px,-2px); box-shadow:6px 6px 0px var(--border); }
  .ach-badge-card::before {
    content:''; position:absolute; top:0; left:0; right:0;
    height:4px; background:linear-gradient(90deg,var(--gold),#fb923c);
  }
 
  .ach-icon {
    font-size:2.4rem; width:70px; height:70px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    border:2.5px solid var(--border); box-shadow:var(--shadow-sm);
    background:#fffbf0; overflow:hidden;
  }
  .ach-icon img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
  .ach-name { font-weight:800; font-size:0.9rem; }
 
  .no-ach { font-size:0.95rem; color:var(--text-muted); font-weight:600; text-align:center; padding:30px 0; }

  .image-modal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.7);
  justify-content: center;
  align-items: center;
  z-index: 9999;
  cursor: zoom-out;
}
.image-modal.show { display: flex; }
.image-modal img {
  max-width: 80%;
  max-height: 80%;
  border-radius: 12px;
  border: 4px solid #fff;
}
 
  /* ── RESPONSIVE ── */
  @media (max-width:680px) {
    .top-cards { grid-template-columns:1fr; }
    aside { width:60px; }
    .nav-item span:not(.icon){ display:none; }
    .nav-item { justify-content:center; padding:14px; margin-right:0; border-radius:0; }
  }
</style>
</head>
<!-- HEADER -->
<header>
  <div class="header-left">
    <h1>Learning Language</h1>
    <div class="greeting">Hello, <?= htmlspecialchars($username) ?> 👋</div>
  </div>
  <div class="header-right">
    <a href="user_profile.php" class="btn-profile">👤 Profile</a>
    <a href="shop.php" class="btn-profile">🛍️ Shop</a>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</header>
 
<!-- LAYOUT -->
<div class="layout">
 
  <!-- SIDEBAR -->
  <aside>
    <a class="nav-item active" href="#" data-page="dashboard">
      <span class="icon">🏠</span><span>Dashboard</span>
    </a>
    <a class="nav-item" href="#" data-page="leaderboard">
      <span class="icon">🏆</span><span>Leaderboard</span>
    </a>
    <a class="nav-item" href="#" data-page="achievement">
      <span class="icon">🎖️</span><span>Achievement</span>
    </a>
  </aside>
 
  <!-- ════ DASHBOARD PAGE ════ -->
  <div class="page active" id="page-dashboard">
    <main>
      <div class="top-cards">
 
        <!-- Coins -->
        <div class="card coin-card">
          <div class="card-title">Coin Balance</div>
          <div class="coin-amount"><?= number_format($coins) ?></div>
          <div class="coin-label">coins earned</div>
        </div>
 
        <!-- Level -->
        <div class="card level-card">
          <div class="card-title">Current Level — <?= htmlspecialchars($language) ?></div>
          <div class="level-badge">⚡ Level <?= $current_level ?></div>
          <div class="level-desc">
            Keep practising to reach Level <?= $current_level + 1 ?>!
          </div>
          <a href="Question.php" class="btn-start">▶ Continue</a>
        </div>
 
      </div>
 
      <!-- Monthly Challenge -->
      <div class="card challenge-card">
        <div class="challenge-header">
          <div class="card-title" style="margin-bottom:0">Monthly Challenge</div>
          <span class="challenge-badge-label"><?= date('F Y') ?></span>
        </div>
 
        <?php if (!$challenge): ?>
          <div class="challenge-desc">No Monthly Challenge available for this month yet. Check back soon!</div>
 
        <?php elseif ($already_done): ?>
          <div class="challenge-desc"><?= htmlspecialchars($challenge['Challenge_Name']) ?></div>
          <div class="challenge-done">✅ Completed! Badge earned: <?= htmlspecialchars($challenge['Badge_Name']) ?></div>
 
        <?php else: ?>
          <div class="challenge-desc">
            <?= htmlspecialchars($challenge['Challenge_Name']) ?> — Answer 20 questions and earn the
            <strong><?= htmlspecialchars($challenge['Badge_Name']) ?></strong>!
          </div>
          <a href="monthly_challenge.php" class="btn-start">▶ Start Challenge</a>
        <?php endif; ?>
      </div>
 
    </main>
  </div>
 
  <!-- ════ LEADERBOARD PAGE ════ -->
  <div class="page" id="page-leaderboard">
    <main>
 
      <div class="lb-header-row">
        <div class="lb-page-title">🏆 Leaderboard</div>
      </div>
 
      <!-- Language Tabs -->
      <div class="lang-tabs">
        <?php foreach ($languages as $lang): ?>
          <a href="?language=<?= urlencode($lang) ?>#leaderboard"
             class="lang-tab <?= $lang === $selected_language ? 'active' : '' ?>"
             onclick="switchTab('leaderboard', document.querySelector('[data-page=leaderboard]')); return true;">
            <?= htmlspecialchars($lang) ?>
          </a>
        <?php endforeach; ?>
      </div>
 
      <!-- My rank banner -->
      <?php if ($my_rank): ?>
        <div class="my-rank-banner">
          Your rank in <strong><?= htmlspecialchars($selected_language) ?></strong>:
          <strong>#<?= $my_rank ?></strong> — Level <strong><?= $my_level ?></strong>
        </div>
      <?php endif; ?>
 
      <!-- Table -->
      <div class="card" style="padding:20px">
        <?php if (!empty($leaderboard)): ?>
          <table class="lb-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Player</th>
                <th>Level</th>
                <th>Points</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leaderboard as $i => $entry): ?>
                <?php $is_me = ($entry['Username'] === $username); ?>
                <tr class="lb-row <?= $is_me ? 'me' : '' ?>" style="animation-delay:<?= $i * 0.04 ?>s">
                  <td>
                    <?php
                      $rank = $entry['Rank'];
                      if      ($rank == 1) echo '<span class="rank-medal">🥇</span>';
                      elseif  ($rank == 2) echo '<span class="rank-medal">🥈</span>';
                      elseif  ($rank == 3) echo '<span class="rank-medal">🥉</span>';
                      else                 echo '<span class="rank-num">#' . $rank . '</span>';
                    ?>
                  </td>
                  <td>
                    <div class="player-cell">
                      <div>
                        <div class="player-name">
                          <?= htmlspecialchars($entry['Username']) ?>
                          <?php if ($is_me): ?><span class="you-badge">You</span><?php endif; ?>
                        </div>
                        <div class="player-tag">Level <?= $entry['Current_Level'] ?></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="lb-score"><?= $entry['Current_Level'] ?></span></td>
                  <td><span class="lb-score">⭐ <?= number_format($entry['Points']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="text-align:center; color:var(--text-muted); padding:20px;">No players found for <?= htmlspecialchars($selected_language) ?>.</p>
        <?php endif; ?>
      </div>
 
    </main>
  </div>
 
  <!-- ════ ACHIEVEMENT PAGE ════ -->
  <div class="page" id="page-achievement">
    <main>
 
      <div class="ach-section-title">🎖️ Your Achievements</div>
 
      <?php if (!empty($achievements)): ?>
        <div class="ach-grid">
          <?php foreach ($achievements as $i => $ach): ?>
            <div class="ach-badge-card" style="animation-delay:<?= $i * 0.06 ?>s">
              <div class="ach-icon">
                <?php if ($ach['Badge']): ?>
                  <img src="images/badges/<?= htmlspecialchars($ach['Badge']) ?>"
                       alt="<?= htmlspecialchars($ach['Achievement_Name']) ?>"
                       onclick="enlargeImage(this.src)" />
                <?php else: ?>
                  🏅
                <?php endif; ?>
              </div>
              <div class="ach-name"><?= htmlspecialchars($ach['Achievement_Name']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="no-ach">No achievements yet. Complete a Monthly Challenge to earn your first badge!</div>
      <?php endif; ?>
 
    </main>
  </div>
 
      <div id="image-modal" class="image-modal" onclick="this.classList.remove('show')">
  <img id="modal-img" src="" alt="" />
      </div>

</div><!-- end .layout -->
 
<script>
  // Page navigation
  document.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const target = link.dataset.page;
      switchTab(target, link);
    });
  });
 
  function switchTab(target, link) {
    document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
    if (link) link.classList.add('active');
 
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const page = document.getElementById('page-' + target);
    if (page) {
      page.classList.add('active');
      page.querySelectorAll('.card, .lb-row, .ach-badge-card').forEach(el => {
        el.style.animation = 'none';
        el.offsetHeight;
        el.style.animation = '';
      });
    }
  }
 
  // Auto open leaderboard tab if language param in URL
  <?php if (isset($_GET['language'])): ?>
  window.addEventListener('DOMContentLoaded', () => {
    const lbLink = document.querySelector('[data-page="leaderboard"]');
    switchTab('leaderboard', lbLink);
  });
  <?php endif; ?>

  function enlargeImage(src) {
  document.getElementById('modal-img').src = src;
  document.getElementById('image-modal').classList.add('show');
}
</script>
 
</body>
</html>