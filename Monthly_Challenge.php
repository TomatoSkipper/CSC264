<?php
session_start();

$host        = "localhost";
$db_name     = "language_learning";
$db_username = "root";
$db_password = "";

$conn = mysqli_connect($host, $db_username, $db_password, $db_name);

if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}

if(!isset($_SESSION['User_ID'])){
    header("Location: LogIn.php");
    exit();
}

$user_id = $_SESSION['User_ID'];

$stmt = $conn->prepare("SELECT Language
                        FROM Current_Progress
                        WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progress = $stmt->get_result()->fetch_assoc();
$stmt->close();

$language = $progress['Language'] ?? null;

$error = '';
$feedback = '';
$feedback_class = '';
$submitted = false;
$question = null;
$answers = [];
$selected_id = null;
$correct_id = null;
$user_answer = '';
$challenge_done = false;
$no_challenge = false;
$already_completed = false;


$this_month = date('Y-m-01');
$stmt = $conn->prepare("SELECT Challenge_ID, Challenge_Name, Badge_Name, Badge_File, Achievement_ID
                        FROM Monthly_Challenge
                        WHERE Challenge_Month = ? AND Language = ?");
$stmt->bind_param("ss",$this_month, $language);
$stmt->execute();
$challenge = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$challenge){
    $no_challenge = true;
}else{
    $challenge_id = $challenge['Challenge_ID'];

    $stmt = $conn->prepare("SELECT Score
                            FROM MC_User_Attempt
                            WHERE User_ID = ? AND Challenge_ID = ?");
    $stmt->bind_param("ii", $user_id, $challenge_id);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if($attempt){
        $already_completed = true;
        $final_score = $attempt['Score'];
    }
}

$stmt =  $conn->prepare("SELECT p.PowerUp_ID, p.PowerUp_Name, up.Quantity
                        FROM Power_Up p
                        JOIN User_PowerUp up ON p.PowerUp_ID = up.PowerUp_ID
                        WHERE up.User_ID = ? AND up.Quantity > 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$my_powerups = [];
while($row = $result->fetch_assoc()){
    $my_powerups[$row['PowerUp_ID']] = $row;
}
$stmt->close();

if(!$no_challenge && !$already_completed && !isset($_SESSION['mc_questions'])){
    $stmt = $conn->prepare("SELECT MC_Question_ID
                            FROM MC_Question 
                            WHERE Challenge_ID = ?
                            ORDER BY RAND() 
                            LIMIT 20");
    $stmt->bind_param("i", $challenge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $question_ids = [];
    while($row = $result->fetch_assoc()){
        $question_ids[]=$row['MC_Question_ID'];
    }
    $stmt->close();


    $_SESSION['mc_challenge_id'] = $challenge_id;
    $_SESSION['mc_questions'] = $question_ids;
    $_SESSION['mc_question_index'] = 0;
    $_SESSION['mc_score'] = 0;
    $_SESSION['mc_active_powerup'] = null;
}

//Power-up Activation

if(isset($_POST['active_powerup']) && !$no_challenge &&!$already_completed){
    $powerup_id = (int)$_POST['powerup_id'];

    if(isset($my_powerups[$powerup_id]) && $my_powerups[$powerup_id]['Quantity'] > 0){
        $_SESSION['mc_active_powerup'] = $powerup_id;
    }

    header("Location: Monthly_Challenge.php");
    exit();
}

if(isset($_POST['skip_question']) && !$no_challenge && !$already_completed){
    $powerup_id = 3;

    if(isset($my_powerups[$powerup_id]) && $my_powerups[$powerup_id]['Quantity'] > 0){
        $stmt = $conn->prepare("UPDATE User_PowerUp 
                                SET Quantity = Quantity - 1 
                                WHERE User_ID = ? AND PowerUp_ID = ?");
        $stmt->bind_param("ii", $user_id, $powerup_id);
        $stmt->execute();
        $stmt->close();
        
        //Move Next Question
        $_SESSION['mc_question_index']++;
        $_SESSION['mc_active_powerup'] = null;

        if($_SESSION['mc_question_index'] >= count($_SESSION['mc_questions'])){
            $challenge_done= true;
        }
    }

    if(!$challenge_done){
        header("Location: Monthly_Challenge.php");
        exit();
    }
}


if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_id']) && !isset($_POST['active_powerup']) && !isset($_POST['skip_question'])){
    $submitted = true;
    $mc_question_id = (int)$_POST['question_id'];
    $active_powerup = $_SESSION['mc_active_powerup'];

    $stmt = $conn->prepare("SELECT MC_Question_ID, Question_Text, Question_Type
                            FROM MC_Question
                            WHERE MC_Question_ID = ?");
    $stmt->bind_param("i", $mc_question_id);
    $stmt->execute();
    $question = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt =$conn->prepare("SELECT MC_Answer_ID, Answer_Option, Is_Correct 
                            FROM MC_Answer 
                            WHERE MC_Question_ID = ?");
    $stmt->bind_param("i", $mc_question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $answers[] = $row;
        if($row['Is_Correct'] == 1){
            $correct_id = $row['MC_Answer_ID'];
            $correct_answer_text = $row['Answer_Option'];
        }
    }
    $stmt->close();

    $is_correct = false;

    if($question['Question_Type'] === 'MCQ'){
        $selected_id = (int)$_POST['answer_id'];
        $is_correct = ($selected_id == $correct_id);
    }elseif($question['Question_Type'] === 'FITB'){
        $user_answer = trim($_POST['user_input'] ?? '');
        $is_correct = (strtolower($user_answer) === strtolower($correct_answer_text));
    }

    if($active_powerup == 1){
        //Double Point
        if($is_correct){
            $_SESSION['mc_score'] += 200;
            $feedback = "CORRECT!! Double The Points +200 points!!";
            $feedback_class = "correct";
        }else{
            $feedback = "Wrong!";
            $feedback_class = "wrong";
        }

        $stmt = $conn->prepare("UPDATE User_PowerUp
                                SET Quantity = Quantity - 1 
                                WHERE User_ID = ? AND PowerUp_ID = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }elseif($active_powerup == 2){
        //Jeopardy

        if($is_correct){
            $_SESSION['mc_score'] += 300;
            $feedback = "CORRECT!! Jeopardy +300 points!!";
            $feedback_class = "correct";
        }else{
            $_SESSION['mc_score'] = max(0, $_SESSION['mc_score'] - 200);
            $feedback = "Wrong! Jeopardy - 200 points";
            $feedback_class = "wrong";
        }
        
        $stmt = $conn->prepare("UPDATE User_PowerUp
                                SET Quantity = Quantity - 1
                                WHERE User_ID = ? AND PowerUp_ID = 2");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }else{
        //No PowerUp / Skip Question
        if($is_correct){
            $_SESSION['mc_score'] += 100;
            $feedback = "CORRECT!! + 100 points";
            $feedback_class = "correct";
        }else{
            $feedback = "Wrong!";
            $feedback_class = "wrong";
        }
    }

    $_SESSION['mc_active_powerup'] = null;
}

if(isset($_GET['next']) && !$no_challenge &&!$already_completed){
    $_SESSION['mc_question_index']++;

    if($_SESSION['mc_question_index'] >= count($_SESSION['mc_questions'])){
        $challenge_done = true;
    }else{
        header("Location: Monthly_Challenge.php");
        exit();
    }
}

if($challenge_done){
    $final_score = $_SESSION['mc_score'];

    $stmt = $conn->prepare("INSERT INTO MC_User_Attempt(User_ID, Challenge_ID, Score)
                            VALUES (?,?,?)");
    $stmt->bind_param("iii", $user_id, $_SESSION['mc_challenge_id'], $final_score);
    $stmt->execute();
    $stmt->close();

    if(!empty($challenge['Achievement_ID'])){
        $stmt = $conn->prepare("INSERT IGNORE INTO User_Achievement(User_ID, Achievement_ID)
                                VALUES(?,?)");
            $stmt->bind_param("ii", $user_id, $challenge['Achievement_ID']);
            $stmt->execute();
            $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE Current_Progress
                            SET Points = Points + ?
                            WHERE User_ID =?");
    $stmt->bind_param("ii", $final_score, $user_id);
    $stmt->execute();
    $stmt->close();
    
    unset($_SESSION['mc_questions']);
    unset($_SESSION['mc_question_index']);
    unset($_SESSION['mc_score']);
    unset($_SESSION['mc_active_powerup']);
    unset($_SESSION['mc_challenge_id']);
}

if(!$submitted && !$challenge_done &&!$no_challenge && !$already_completed && isset($_SESSION['mc_questions'])){
    $current_index = $_SESSION['mc_question_index'];
    $mc_question_id = $_SESSION['mc_questions'][$current_index];

    $stmt = $conn->prepare("SELECT MC_Question_ID, Question_Text, Question_Type
                            FROM MC_Question
                            WHERE MC_Question_ID =  ?");
    $stmt->bind_param("i", $mc_question_id);
    $stmt->execute();
    $question = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if($question['Question_Type'] === 'MCQ'){
        $stmt = $conn->prepare("SELECT MC_Answer_ID, Answer_Option, Is_Correct
                                FROM MC_Answer
                                WHERE MC_Question_ID = ?");
        $stmt->bind_param("i", $mc_question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $answers[] = $row;
        }
        $stmt->close();
    }
}

$current_score = $_SESSION['mc_score'] ?? 0;
$total = count($_SESSION['mc_questions'] ?? []);
$current_index = $_SESSION['mc_question_index'] ?? 0;
$active_powerup = $_SESSION['mc_active_powerup'] ?? null;

$stmt = $conn->prepare("SELECT p.PowerUp_ID, p.PowerUp_Name, up.Quantity
                        FROM Power_Up p
                        JOIN User_PowerUp up ON p.PowerUp_ID = up.PowerUp_ID
                        WHERE up.User_ID = ? AND up.Quantity > 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$my_powerups = [];
while($row = $result->fetch_assoc()){
    $my_powerups[$row['PowerUp_ID']] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Monthly Challenge</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Space+Mono:wght@400;700&display=swap');

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
      --orange: #f97316;
      --orange-dark: #ea580c;
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
      justify-content: center;
      align-items: center;
      padding: 30px 20px;
    }
 
    .container {
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 32px;
      max-width: 650px;
      width: 100%;
      animation: cardIn 0.4s ease both;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }
 
    /* ── Header ── */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
 
    .header h2 { 
      font-size: 1.4rem; 
      font-weight: 900; 
      letter-spacing: -0.5px;
      color: var(--text-primary); 
    }

    .score-box { 
      font-family: 'Space Mono', monospace;
      font-size: 1.1rem; 
      font-weight: 700; 
      color: var(--gold);
      background: #fffbf0;
      border: 2px solid var(--border);
      padding: 4px 12px;
      border-radius: 8px;
      box-shadow: var(--shadow-sm);
    }
 
    /* ── Progress bar ── */
    .progress-wrap {
      background: var(--bg);
      border: 2.5px solid var(--border);
      border-radius: 8px;
      height: 16px;
      margin-bottom: 20px;
      overflow: hidden;
    }
 
    .progress-bar {
      background: var(--accent-blue);
      height: 100%;
      border-right: 2px solid var(--border);
      transition: width 0.3s ease;
    }
 
    .question-num { 
      font-size: 0.85rem; 
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--text-muted); 
      margin-bottom: 8px; 
    }
 
    .question-text {
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--text-primary);
      margin-bottom: 24px;
      line-height: 1.4;
    }
 
    /* ── Power-up bar ── */
    .powerup-bar {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }
 
    .powerup-bar form { margin: 0; }
 
    .btn-powerup {
      padding: 8px 16px;
      border: 2.5px solid var(--border);
      border-radius: 8px;
      font-family: 'Nunito', sans-serif;
      font-weight: 800;
      font-size: 0.85rem;
      cursor: pointer;
      background: var(--accent-blue-light);
      color: var(--accent-blue);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
 
    .btn-powerup:hover { 
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }

    .btn-powerup.active { 
      background: var(--accent-blue); 
      color: white; 
    }

    .btn-skip {
      padding: 8px 16px;
      border: 2.5px solid var(--border);
      border-radius: 8px;
      font-family: 'Nunito', sans-serif;
      font-weight: 800;
      font-size: 0.85rem;
      cursor: pointer;
      background: var(--orange);
      color: #fff;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
 
    .btn-skip:hover { 
      background: var(--orange-dark);
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }
 
    /* ── Options ── */
    .options {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 24px;
    }
 
    .option {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 18px;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      background: var(--surface);
      box-shadow: var(--shadow-sm);
      transition: all 0.12s ease;
    }

    .option:hover {
      transform: translate(-1px, -1px);
      box-shadow: 4px 4px 0px var(--border);
      background: var(--accent-blue-light);
    }
 
    .option input[type="radio"] { 
      accent-color: var(--accent-blue); 
      width: 18px; 
      height: 18px; 
      cursor: pointer;
    }

    .option label { 
      cursor: pointer; 
      font-size: 1rem; 
      font-weight: 700;
      color: var(--text-primary); 
    }

    /* Option Evaluation Results */
    .option.correct { 
      background: #f0fff4 !important; 
      border-color: var(--accent-green) !important; 
      box-shadow: 3px 3px 0px var(--accent-green-dark) !important;
      transform: none !important;
    }
    .option.wrong { 
      background: #fff5f5 !important; 
      border-color: #ef4444 !important; 
      box-shadow: 3px 3px 0px #b91c1c !important;
      transform: none !important;
    }
 
    /* ── FITB ── */
    .fitb-wrap { margin-bottom: 24px; }
    .fitb-wrap input[type="text"] {
      width: 100%;
      padding: 14px 18px;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      font-size: 1rem;
      font-family: 'Space Mono', monospace;
      font-weight: 700;
      background: var(--surface);
      box-shadow: inset 2px 2px 0px rgba(0,0,0,0.05);
      outline: none;
    }
    .fitb-wrap input.correct { border-color: var(--accent-green); background: #f0fff4; color: var(--accent-green-dark); }
    .fitb-wrap input.wrong   { border-color: #ef4444; background: #fff5f5; color: #b91c1c; }
 
    /* ── Feedback ── */
    .feedback { 
      font-size: 1rem; 
      font-weight: 800; 
      margin-bottom: 20px; 
      padding: 12px 16px;
      border-radius: 8px;
      border: 2px solid var(--border);
    }
    .feedback.correct { background: #f0fff4; color: var(--accent-green-dark); border-color: var(--accent-green); }
    .feedback.wrong   { background: #fff5f5; color: #ef4444; border-color: #ef4444; }
 
    /* ── Active power-up notice ── */
    .powerup-notice {
      background: #fffbf0;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      padding: 10px 16px;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--gold);
      margin-bottom: 20px;
      box-shadow: var(--shadow-sm);
    }
 
    /* ── Action Buttons ── */
    button[type="submit"], .btn-next-action {
      width: 100%;
      padding: 14px;
      background: var(--accent-green);
      color: #fff;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      font-family: 'Nunito', sans-serif;
      font-weight: 900;
      font-size: 1.1rem;
      cursor: pointer;
      box-shadow: var(--shadow);
      transition: all 0.15s ease;
      display: inline-block;
      text-align: center;
      text-decoration: none;
    }
 
    button[type="submit"]:hover:not(:disabled), .btn-next-action:hover { 
      background: var(--accent-green-dark); 
      transform: translate(-2px, -2px);
      box-shadow: 6px 6px 0px var(--border);
    }

    button[type="submit"]:disabled {
      background: var(--silver);
      color: var(--surface);
      cursor: not-allowed;
      box-shadow: none;
      transform: none;
    }
 
    /* ── End / Info screens ── */
    .end-screen {
      text-align: center;
      padding: 10px 0;
    }
 
    .end-screen h2 { font-size: 1.8rem; font-weight: 900; color: var(--text-primary); margin-bottom: 12px; }
    .end-screen p  { color: var(--text-secondary); margin-bottom: 14px; font-size: 1rem; font-weight: 600; }
    
    .end-screen .final-score { 
      font-family: 'Space Mono', monospace; 
      font-size: 3.2rem; 
      font-weight: 700; 
      color: var(--accent-blue); 
      margin: 20px 0;
      text-shadow: 2px 2px 0px var(--border);
    }
 
    .badge-box {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #fffbf0;
      border: 2.5px solid var(--border);
      border-radius: 12px;
      padding: 16px 28px;
      margin: 16px 0;
      font-size: 1.1rem;
      color: var(--gold);
      font-weight: 800;
      box-shadow: var(--shadow);
    }
 
    .back-link {
      display: inline-block;
      margin-top: 24px;
      font-size: 0.95rem;
      color: var(--accent-blue);
      font-weight: 800;
      text-decoration: none;
      transition: transform 0.15s;
    }
    .back-link:hover {
      transform: translateX(-3px);
      text-decoration: underline;
    }
 
    .info-box {
      text-align: center;
      padding: 40px 0;
    }
 
    .info-box h3 { font-size: 1.5rem; font-weight: 900; color: var(--text-primary); margin-bottom: 12px; }
    .info-box p { color: var(--text-secondary); font-weight: 600; }
  </style>
</head>
<body>
 
<div class="container">
 
  <?php if ($no_challenge): ?>
    <div class="info-box">
      <h3>No Challenge Available</h3>
      <p>There is no Monthly Challenge for this month yet.</p>
      <p>Check back later!</p>
      <br>
      <a href="MainPage.php" class="back-link">← Back to Dashboard</a>
    </div>
 
  <?php elseif ($already_completed && !$challenge_done): ?>
    <div class="end-screen">
      <h2>Already Completed!</h2>
      <p>You have already completed this month's challenge.</p>
      <p>Your score:</p>
      <div class="final-score"><?= $final_score ?> pts</div>
      <div class="badge-box">🏅 <?= htmlspecialchars($challenge['Badge_Name']) ?></div>
      <br>
      <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
 
  <?php elseif ($challenge_done): ?>
    <div class="end-screen">
      <h2>Challenge Complete!</h2>
      <p><?= htmlspecialchars($challenge['Challenge_Name']) ?></p>
      <div class="final-score"><?= $final_score ?> pts</div>
      <div class="badge-box">🏅 <?= htmlspecialchars($challenge['Badge_Name']) ?> — Earned!</div>
      <p>Points have been added to your profile.</p>
      <br>
      <a href="MainPage.php" class="back-link">← Back to Dashboard</a>
    </div>
 
  <?php elseif ($question): ?>
    <div class="header">
      <h2><?= htmlspecialchars($challenge['Challenge_Name']) ?></h2>
      <div class="score-box">Score: <?= $current_score ?></div>
    </div>
 
    <div class="progress-wrap">
      <div class="progress-bar" style="width: <?= ($total > 0 ? ($current_index / $total) * 100 : 0) ?>%"></div>
    </div>
 
    <div class="question-num">Question <?= $current_index + 1 ?> of <?= $total ?></div>
 
    <div class="question-text"><?= htmlspecialchars($question['Question_Text']) ?></div>
 
    <?php if ($submitted): ?>
      <div class="feedback <?= $feedback_class ?>"><?= $feedback ?></div>
 
      <?php if ($question['Question_Type'] === 'MCQ'): ?>
        <div class="options">
          <?php foreach ($answers as $ans): ?>
            <?php
              $cls = '';
              if ($ans['Is_Correct'] == 1)                    $cls = 'correct';
              elseif ($ans['MC_Answer_ID'] == $selected_id)   $cls = 'wrong';
            ?>
            <div class="option <?= $cls ?>">
              <input type="radio" <?= ($ans['MC_Answer_ID'] == $selected_id) ? 'checked' : '' ?> disabled />
              <label><?= htmlspecialchars($ans['Answer_Option']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
 
      <?php elseif ($question['Question_Type'] === 'FITB'): ?>
        <div class="fitb-wrap">
          <input type="text" value="<?= htmlspecialchars($user_answer) ?>" class="<?= $feedback_class ?>" disabled />
        </div>
      <?php endif; ?>
 
      <a href="monthly_challenge.php?next=1" class="next-question-link" style="text-decoration:none; display:block;">
        <button type="button" class="btn-next-action">
          <?= ($current_index + 1 >= $total) ? 'See Results' : 'Next Question' ?>
        </button>
      </a>
 
    <?php else: ?>
      <?php if (!empty($my_powerups)): ?>
        <div class="powerup-bar">
          <?php foreach ($my_powerups as $pu): ?>
            <?php if ($pu['PowerUp_ID'] != 3): // Skip shown separately ?>
              <form method="POST" action="monthly_challenge.php">
                <input type="hidden" name="powerup_id" value="<?= $pu['PowerUp_ID'] ?>"/>
                <button type="submit" name="active_powerup"
                        class="btn-powerup <?= ($active_powerup == $pu['PowerUp_ID']) ? 'active' : '' ?>">
                  <?= htmlspecialchars($pu['PowerUp_Name']) ?> (<?= $pu['Quantity'] ?>)
                </button>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
 
          <?php if (isset($my_powerups[3])): ?>
            <form method="POST" action="monthly_challenge.php"
                  onsubmit="return confirm('Use Skip Question power-up?')">
              <button type="submit" name="skip_question" class="btn-skip">
                Skip Question (<?= $my_powerups[3]['Quantity'] ?>)
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
 
      <?php if ($active_powerup): ?>
        <div class="powerup-notice">
          ⚡ <?= htmlspecialchars($my_powerups[$active_powerup]['PowerUp_Name'] ?? '') ?> is active for this question!
        </div>
      <?php endif; ?>
 
      <form method="POST" action="monthly_challenge.php">
        <input type="hidden" name="question_id" value="<?= $question['MC_Question_ID'] ?>"/>
 
        <?php if ($question['Question_Type'] === 'MCQ'): ?>
          <div class="options">
            <?php foreach ($answers as $ans): ?>
              <div class="option" onclick="this.querySelector('input').checked=true; enableSubmit();">
                <input type="radio" name="answer_id" value="<?= $ans['MC_Answer_ID'] ?>" required />
                <label><?= htmlspecialchars($ans['Answer_Option']) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
 
        <?php elseif ($question['Question_Type'] === 'FITB'): ?>
          <div class="fitb-wrap">
            <input type="text" name="user_input" placeholder="Type your answer here..."
                   oninput="enableSubmit()" autocomplete="off" required />
          </div>
        <?php endif; ?>
 
        <button type="submit" id="submit-btn" disabled>Submit</button>
      </form>
 
    <?php endif; ?>
 
  <?php else: ?>
    <div class="info-box">
      <h3>No Questions Available</h3>
      <p>This month's challenge has no questions yet.</p>
      <a href="MainPage.php" class="back-link">← Back to Dashboard</a>
    </div>
  <?php endif; ?>
 
</div>
 
<script>
  function enableSubmit() {
    document.getElementById('submit-btn').disabled = false;
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const submitBtn = document.getElementById('submit-btn');
      const nextLink  = document.querySelector('.next-question-link button');

      if (submitBtn && !submitBtn.disabled) {
        submitBtn.click();
      } else if (nextLink) {
        nextLink.click();
      }
    }
  });
</script>
 
</body>
</html>