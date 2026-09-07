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

if(!isset($_SESSION['points'])) $_SESSION['points'] = 0;
if(!isset($_SESSION['answered_ids'])) $_SESSION['answered_ids'] = [];

$user_id = $_SESSION['User_ID'] ;

$stmt = $conn->prepare("SELECT Language, Current_Level 
                        FROM current_progress 
                        WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progress = $stmt->get_result()->fetch_assoc();
$stmt->close();

$language = $progress['Language'];
$level    = $progress['Current_Level'] ;

// Initialize 
$feedback       = '';
$feedback_class = '';
$submitted      = false;
$selected_id    = null;
$correct_id     = null;
$user_answer    = '';
$answers        = [];
$question       = null;
$quiz_done      = false;
$coins          = $_SESSION['points'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_id'])) {
    $submitted   = true;
    $question_id = (int) $_POST['question_id'];

    $stmt = $conn->prepare("SELECT Question_ID, Question_Text, Question_Type, Language, Level 
                            FROM Question 
                            WHERE Question_ID = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $question = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT Answer_ID, answer_option, Is_Correct 
                            FROM Answer 
                            WHERE Question_ID = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $answers[] = $row;
        if ($row['Is_Correct'] == 1)
           $correct_id = $row['Answer_ID'];
    }
    $stmt->close();

    if($question['Question_Type'] === 'MCQ') {
      $selected_id = (int) $_POST['answer_id'];
        if ($selected_id == $correct_id) {
            $_SESSION['points'] += 100;

            $stmt = $conn->prepare("UPDATE Current_Progress 
                                    SET Points = Points + 100 
                                    WHERE User_ID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            $feedback = "Correct! +100 points.";
            $feedback_class = "correct";
        } else {
            $feedback = "Wrong! The correct answer was highlighted.";
            $feedback_class = "wrong";
        }
    }
    elseif($question['Question_Type'] === 'FITB') {
        $user_answer = trim($_POST['user_input']);
        $correct_answer = '';
        foreach ($answers as $ans) {
        if ($ans['Is_Correct'] == 1) {
            $correct_answer = trim($ans['answer_option']);
            break;
          }
      }
        if (strtolower($user_answer) === strtolower($correct_answer)) {
            $_SESSION['points'] += 100;
            $feedback = "Correct! +100 points.";
            $feedback_class = "correct";

            $stmt = $conn->prepare("UPDATE Current_Progress 
                                    SET Points = Points + 100 
                                    WHERE User_ID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $feedback = "Wrong! The correct answer was: " . htmlspecialchars($correct_answer);
            $feedback_class = "wrong";
        }
    }
    if(!in_array($question_id, $_SESSION['answered_ids'])) {
        $_SESSION['answered_ids'][] = $question_id;
    }
}

$stmt = $conn->prepare("SELECT COUNT(*) as total 
                        FROM Question 
                        WHERE Language = ? AND Level = ?");
$stmt->bind_param("si", $language, $level);
$stmt->execute();
$total_questions = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

if(!$submitted) {
    $excluded = $_SESSION['answered_ids'];

    if(!empty($excluded)) {
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));
        $types = str_repeat('i', count($excluded));
        $sql = "SELECT Question_ID , Question_Text, Question_Type, Language, Level 
                FROM Question 
                WHERE Language = ? AND Level = ? 
                AND Question_ID NOT IN ($placeholders)
                ORDER BY RAND() LIMIT 1";
        $stmt = $conn->prepare($sql);
        $bind_params = array_merge([$language, $level], $excluded);
        $types = 'si' . $types;
        $stmt->bind_param($types, ...$bind_params);
    } else {
        $sql = "SELECT Question_ID , Question_Text, Question_Type, Language, Level 
                FROM Question 
                WHERE Language = ? AND Level = ? 
                ORDER BY RAND() LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $language, $level);
    }
    $stmt->execute();
    $question = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if(!$question) {
      $quiz_done = true;

      $coins = $_SESSION['points'];
      $points = $_SESSION['points'];
      $stmt = $conn->prepare("UPDATE Current_Progress 
                              SET Current_Level = Current_Level + 1, Coins = Coins + ?
                              WHERE User_ID = ?");
      $stmt->bind_param("ii", $coins, $user_id);
      $stmt->execute();
      $stmt->close(); 

      $_SESSION['answered_ids'] = [];
      $_SESSION['points'] = 0;
    }

    if($question){
        if($question['Question_Type'] === 'MCQ') {
          $stmt = $conn->prepare("SELECT Answer_ID, answer_option, Is_Correct 
                                  FROM Answer 
                                  WHERE Question_ID = ?");
          $stmt->bind_param("i", $question['Question_ID']);
          $stmt->execute();
          $result = $stmt->get_result();
          while($row = $result->fetch_assoc()) {
              $answers[] = $row;
          }
          $stmt->close();
        }
    }    
}

$points = $quiz_done ? $coins : $_SESSION['points'];
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Question — Learning Language</title>
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
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── CARD ── */
    .container {
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 32px 28px;
      width: 100%;
      max-width: 460px;
      animation: cardIn 0.4s ease both;
    }

    /* ── HEADER ROW ── */
    .quiz-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }

    .quiz-lang-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--accent-blue-light);
      border: 2px solid var(--border);
      border-radius: 8px;
      padding: 5px 14px;
      font-weight: 800;
      font-size: 0.85rem;
      color: var(--accent-blue);
      box-shadow: var(--shadow-sm);
    }

    .points-box {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-family: 'Space Mono', monospace;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--gold);
      background: #fffbf0;
      border: 2px solid var(--border);
      border-radius: 8px;
      padding: 5px 14px;
      box-shadow: var(--shadow-sm);
    }

    /* ── QUESTION TEXT ── */
    .question-text {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-primary);
      line-height: 1.55;
      margin-bottom: 22px;
    }

    /* ── MCQ OPTIONS ── */
    .options {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 24px;
    }

    .option {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      background: var(--surface);
      border: 2px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      box-shadow: var(--shadow-sm);
      transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease;
    }

    .option:hover {
      background: var(--accent-blue-light);
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }

    .option input[type="radio"] {
      accent-color: var(--accent-blue);
      width: 16px;
      height: 16px;
      flex-shrink: 0;
    }

    .option label {
      cursor: pointer;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-primary);
      line-height: 1.4;
    }

    /* Feedback states */
    .option.correct {
      background: #f0fff4;
      border-color: var(--accent-green);
      box-shadow: 2px 2px 0px var(--accent-green-dark);
    }
    .option.correct label { color: var(--accent-green-dark); font-weight: 800; }

    .option.wrong {
      background: #fff0f0;
      border-color: #ef4444;
      box-shadow: 2px 2px 0px #b91c1c;
    }
    .option.wrong label { color: #b91c1c; font-weight: 800; }

    /* ── FITB INPUT ── */
    .fitb-wrap {
      margin-bottom: 24px;
    }

    .fitb-wrap input[type="text"],
    input[type="text"] {
      width: 100%;
      padding: 12px 16px;
      font-family: 'Nunito', sans-serif;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-primary);
      background: var(--surface);
      border: 2px solid var(--border);
      border-radius: 10px;
      box-shadow: var(--shadow-sm);
      outline: none;
      transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }

    input[type="text"]:focus {
      border-color: var(--accent-blue);
      box-shadow: 3px 3px 0px var(--accent-blue);
    }

    input[type="text"].correct {
      border-color: var(--accent-green);
      background: #f0fff4;
      box-shadow: 2px 2px 0px var(--accent-green-dark);
    }

    input[type="text"].wrong {
      border-color: #ef4444;
      background: #fff0f0;
      box-shadow: 2px 2px 0px #b91c1c;
    }

    /* ── FEEDBACK MESSAGE ── */
    .feedback {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
      font-weight: 800;
      padding: 10px 14px;
      border-radius: 9px;
      border: 2px solid;
      margin-bottom: 20px;
    }

    .feedback.correct {
      background: #f0fff4;
      border-color: var(--accent-green);
      color: var(--accent-green-dark);
    }

    .feedback.wrong {
      background: #fff0f0;
      border-color: #ef4444;
      color: #b91c1c;
    }

    /* ── DIVIDER ── */
    .divider {
      border: none;
      border-top: 2px solid var(--border);
      margin: 20px 0;
      opacity: 0.12;
    }

    /* ── BUTTONS ── */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: var(--accent-green);
      color: white;
      border: 2.5px solid var(--border);
      border-radius: 9px;
      font-family: 'Nunito', sans-serif;
      font-weight: 800;
      font-size: 0.95rem;
      padding: 10px 24px;
      cursor: pointer;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
      text-decoration: none;
    }

    .btn:hover {
      background: var(--accent-green-dark);
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }

    .btn:active {
      transform: translate(1px, 1px);
      box-shadow: 1px 1px 0px var(--border);
    }

    .btn-secondary {
      background: var(--accent-blue-light);
      color: var(--accent-blue);
      border-color: var(--border);
    }

    .btn-secondary:hover {
      background: var(--accent-blue);
      color: white;
    }

    .btn-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    /* ── END SCREEN ── */
    .end-screen {
      text-align: center;
      padding: 8px 0;
    }

    .end-screen .end-title {
      font-size: 1.6rem;
      font-weight: 900;
      letter-spacing: -0.5px;
      margin-bottom: 6px;
    }

    .end-screen .end-sub {
      font-size: 0.9rem;
      color: var(--text-muted);
      font-weight: 600;
      margin-bottom: 20px;
    }

    .end-screen .final-points {
      font-family: 'Space Mono', monospace;
      font-size: 3rem;
      font-weight: 700;
      color: var(--gold);
      margin: 16px 0 6px;
      line-height: 1;
    }

    .end-screen .final-label {
      font-size: 0.8rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--text-muted);
      margin-bottom: 28px;
    }

    /* ── NO QUESTION ── */
    .no-question {
      text-align: center;
      color: var(--text-muted);
      font-size: 0.95rem;
      font-weight: 600;
      padding: 20px 0;
    }

    @media (max-width: 480px) {
      .container { padding: 24px 18px; }
      .btn-row { flex-direction: column; }
      .btn { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

<div class="container">

  <?php if ($quiz_done): ?>
    <!-- ── End Screen ── -->
    <div class="end-screen">
      <div class="end-title">🎉 Level Complete!</div>
      <div class="end-sub">
        <?= htmlspecialchars($language) ?> — Level <?= htmlspecialchars($level) ?> &nbsp;·&nbsp;
        <?= $total_questions ?> questions answered
      </div>
      <div class="final-points"><?= number_format($points) ?></div>
      <div class="final-label">points earned</div>
      <div class="btn-row" style="justify-content:center;">
        <a href="MainPage.php" class="btn btn-secondary">🏠 Dashboard</a>
        <a href="Question.php" class="btn">▶ Next Level</a>
      </div>
    </div>

  <?php elseif ($question): ?>

    <!-- ── Quiz Header ── -->
    <div class="quiz-header">
      <div class="quiz-lang-badge">
        ⚡ <?= htmlspecialchars($question['Language']) ?> — Level <?= htmlspecialchars($question['Level']) ?>
      </div>
      <div class="points-box">
        🪙 <?= number_format($points) ?> pts
      </div>
    </div>

    <!-- ── Question Text ── -->
    <div class="question-text">
      <?= htmlspecialchars($question['Question_Text']) ?>
    </div>

    <?php if ($submitted): ?>
      <!-- ── POST: feedback + result ── -->

      <?php if ($feedback): ?>
        <div class="feedback <?= $feedback_class ?>">
          <?= $feedback_class === 'correct' ? '✅' : '❌' ?>
          <?= $feedback ?>
        </div>
      <?php endif; ?>

      <?php if ($question['Question_Type'] === 'MCQ'): ?>
        <div class="options">
          <?php foreach ($answers as $ans): ?>
            <?php
              $cls = '';
              if ($ans['Is_Correct'] == 1)               $cls = 'correct';
              elseif ($ans['Answer_ID'] == $selected_id) $cls = 'wrong';
            ?>
            <div class="option <?= $cls ?>">
              <input type="radio" <?= ($ans['Answer_ID'] == $selected_id) ? 'checked' : '' ?> disabled />
              <label><?= htmlspecialchars($ans['answer_option']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($question['Question_Type'] === 'FITB'): ?>
        <div class="fitb-wrap">
          <input type="text"
                 value="<?= htmlspecialchars($user_answer) ?>"
                 class="<?= $feedback_class ?>"
                 disabled />
        </div>
      <?php endif; ?>

      <form method="GET" action="question.php">
        <button type="submit" class="btn">Next Question →</button>
      </form>

    <?php else: ?>
      <!-- ── GET: fresh question ── -->

      <form method="POST" action="question.php">
        <input type="hidden" name="question_id" value="<?= $question['Question_ID'] ?>"/>

        <?php if ($question['Question_Type'] === 'MCQ'): ?>
          <div class="options">
            <?php foreach ($answers as $ans): ?>
              <div class="option" onclick="this.querySelector('input').checked=true">
                <input type="radio"
                       name="answer_id"
                       value="<?= $ans['Answer_ID'] ?>"
                       required />
                <label><?= htmlspecialchars($ans['answer_option']) ?></label>
              </div>
            <?php endforeach; ?>
          </div>

        <?php elseif ($question['Question_Type'] === 'FITB'): ?>
          <div class="fitb-wrap">
            <input type="text"
                   name="user_input"
                   placeholder="Type your answer here…"
                   required />
          </div>
        <?php endif; ?>

        <button type="submit" class="btn">Submit</button>
      </form>

    <?php endif; ?>

  <?php else: ?>
    <div class="no-question">No questions available for this language and level.</div>
  <?php endif; ?>

</div>

<script>
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const submitBtn = document.querySelector('button[type="submit"]');
      const nextBtn   = document.querySelector('form[method="GET"] button');

      if (submitBtn) {
        submitBtn.click();
      } else if (nextBtn) {
        nextBtn.click();
      }
    }
  });
</script>
</body>
</html>