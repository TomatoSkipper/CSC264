<?php
session_start();

// ─────────────────────────────────────────
// DB Config
// ─────────────────────────────────────────
$host        = "localhost";
$db_name     = "language_learning";
$db_username = "root";
$db_password = "";

$conn = mysqli_connect($host, $db_username, $db_password, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ─────────────────────────────────────────
// Guard — must be admin
// ─────────────────────────────────────────
// if (!isset($_SESSION['Admin_ID'])) {
//     header("Location: login.php");
//     exit();
// }

// ─────────────────────────────────────────
// Initialize
// ─────────────────────────────────────────
$error              = '';
$success            = '';
$allowed_languages = ['Malay', 'Chinese'];
$allowed_types      = ['MCQ', 'FITB'];

$selected_language = $_GET['language'] ?? ($_POST['language'] ?? '');
$selected_level     = isset($_GET['level']) ? (int)$_GET['level'] : (isset($_POST['level']) ? (int)$_POST['level'] : null);

$existing_questions = [];
$max_level           = 1;
$editing_question    = null;
$editing_answers     = [];

// ─────────────────────────────────────────
// Get the highest level currently in DB for this language
// ─────────────────────────────────────────
if ($selected_language) {
    $stmt = $conn->prepare("SELECT MAX(Level) AS max_level FROM Question WHERE Language = ?");
    $stmt->bind_param("s", $selected_language);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $max_level = ($row && $row['max_level']) ? (int)$row['max_level'] : 0;
}
$max_allowed_level = $max_level + 1;
if ($max_allowed_level < 1) $max_allowed_level = 1;

// ─────────────────────────────────────────
// Handle: Edit existing question (update)
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
    $question_id   = (int) $_POST['question_id'];
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];

    if (empty($question_text)) {
        $error = "Question text cannot be empty.";
    } else {
        $stmt = $conn->prepare("UPDATE Question SET Question_Text = ? WHERE Question_ID = ?");
        $stmt->bind_param("si", $question_text, $question_id);
        $stmt->execute();
        $stmt->close();

        if ($question_type === 'MCQ') {
            for ($i = 1; $i <= 4; $i++) {
                $answer_id   = isset($_POST["answer_id_$i"]) ? (int)$_POST["answer_id_$i"] : null;
                $answer_text = trim($_POST["answer_$i"] ?? '');
                $is_correct  = (isset($_POST['correct_answer']) && $_POST['correct_answer'] == $i) ? 1 : 0;

                if ($answer_id && !empty($answer_text)) {
                    $stmt = $conn->prepare("UPDATE Answer SET answer_option = ?, Is_Correct = ? WHERE Answer_ID = ?");
                    $stmt->bind_param("sii", $answer_text, $is_correct, $answer_id);
                    $stmt->execute();
                    $stmt->close();
                } elseif (!$answer_id && !empty($answer_text)) {
                    $stmt = $conn->prepare("INSERT INTO Answer (Question_ID, answer_option, Is_Correct) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                    $stmt->execute();
                    $stmt->close();
                } elseif ($answer_id && empty($answer_text)) {
                    $stmt = $conn->prepare("DELETE FROM Answer WHERE Answer_ID = ?");
                    $stmt->bind_param("i", $answer_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } elseif ($question_type === 'FITB') {
            $answer_id   = (int) ($_POST['answer_id_1'] ?? 0);
            $answer_text = trim($_POST['answer_1'] ?? '');
            if ($answer_id && !empty($answer_text)) {
                $stmt = $conn->prepare("UPDATE Answer SET answer_option = ? WHERE Answer_ID = ?");
                $stmt->bind_param("si", $answer_text, $answer_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        $success = "Question updated successfully!";
    }
}

// ─────────────────────────────────────────
// Handle: Add new question
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $language      = $_POST['language'];
    $level         = (int) $_POST['level'];
    $question_type = $_POST['question_type'];
    $question_text = trim($_POST['question_text']);
    $num_options   = isset($_POST['num_options']) ? (int) $_POST['num_options'] : 1;

    if (!in_array($language, $allowed_languages)) {
        $error = "Invalid language selected.";
    } elseif ($level < 1 || $level > $max_allowed_level) {
        $error = "Level must be between 1 and {$max_allowed_level}.";
    } elseif (!in_array($question_type, $allowed_types)) {
        $error = "Invalid question type.";
    } elseif (empty($question_text)) {
        $error = "Question text cannot be empty.";
    } else {
        $stmt = $conn->prepare("INSERT INTO Question (Question_Type, Question_Text, Language, Level) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $question_type, $question_text, $language, $level);
        $stmt->execute();
        $question_id = $conn->insert_id;
        $stmt->close();

        if ($question_type === 'MCQ') {
            for ($i = 1; $i <= $num_options; $i++) {
                $answer_text = trim($_POST["answer_$i"] ?? '');
                $is_correct  = isset($_POST['correct_answer']) && $_POST['correct_answer'] == $i ? 1 : 0;

                if (!empty($answer_text)) {
                    $stmt = $conn->prepare("INSERT INTO Answer (Question_ID, answer_option, Is_Correct) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } elseif ($question_type === 'FITB') {
            $answer_text = trim($_POST['answer_1'] ?? '');
            if (!empty($answer_text)) {
                $stmt = $conn->prepare("INSERT INTO Answer (Question_ID, answer_option, Is_Correct) VALUES (?, ?, 1)");
                $stmt->bind_param("is", $question_id, $answer_text);
                $stmt->execute();
                $stmt->close();
            }
        }

        $success = "Question added successfully!";
        
        $stmt = $conn->prepare("SELECT MAX(Level) AS max_level FROM Question WHERE Language = ?");
        $stmt->bind_param("s", $language);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $max_level = ($row && $row['max_level']) ? (int)$row['max_level'] : 0;
        $max_allowed_level = $max_level + 1;
    }
}

// ─────────────────────────────────────────
// Fetch questions for table view
// ─────────────────────────────────────────
if ($selected_language && $selected_level !== null) {
    $stmt = $conn->prepare("SELECT Question_ID, Question_Type, Question_Text FROM Question WHERE Language = ? AND Level = ? ORDER BY Question_ID ASC");
    $stmt->bind_param("si", $selected_language, $selected_level);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stmt2 = $conn->prepare("SELECT Answer_ID, answer_option, Is_Correct FROM Answer WHERE Question_ID = ? ORDER BY Answer_ID ASC");
        $stmt2->bind_param("i", $row['Question_ID']);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $opts = [];
        while ($a = $res2->fetch_assoc()) {
            $opts[] = $a;
        }
        $stmt2->close();
        $row['Answers'] = $opts;
        $existing_questions[] = $row;
    }
    $stmt->close();
}

// ─────────────────────────────────────────
// Fetch single question for editing
// ─────────────────────────────────────────
if (isset($_GET['edit_id'])) {
    $edit_id = (int) $_GET['edit_id'];

    $stmt = $conn->prepare("SELECT Question_ID, Question_Type, Question_Text, Language, Level FROM Question WHERE Question_ID = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editing_question = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($editing_question) {
        $stmt = $conn->prepare("SELECT Answer_ID, answer_option, Is_Correct FROM Answer WHERE Question_ID = ? ORDER BY Answer_ID ASC");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $editing_answers[] = $row;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — Manage Questions</title>
  <style>
    :root {
      --bg: #f5f0e8;
      --surface: #fffdf7;
      --border: #1a1a2e;
      --accent-blue: #3b5bdb;
      --accent-blue-light: #e8eeff;
      --accent-green: #22c55e;
      --accent-green-dark: #16a34a;
      --accent-orange: #f97316;
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

    * { box-sizing: border-box; margin: 0; padding: 0; }
    
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

    h3 { 
      font-size: 1.25rem; 
      font-weight: 800;
      color: var(--text-primary); 
      margin: 32px 0 16px; 
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

    .nav-links a:last-child {
      background: #f1f5f9;
      color: var(--text-secondary);
    }
    .nav-links a:last-child:hover {
      background: var(--error-bg);
      color: var(--error-border);
    }

    .field { margin-bottom: 22px; }
    
    .field label { 
      display: block; 
      font-size: 0.95rem; 
      font-weight: 800; 
      color: var(--text-primary); 
      margin-bottom: 8px; 
    }

    .field select, 
    .field input[type="text"], 
    .field input[type="number"], 
    .field textarea { 
      width: 100%; 
      padding: 12px 16px; 
      border: 2.5px solid var(--border); 
      border-radius: 8px; 
      font-size: 1rem; 
      font-family: 'Nunito', sans-serif;
      font-weight: 700;
      background: var(--surface);
      color: var(--text-primary);
      transition: all 0.15s ease;
    }

    .field select:focus, 
    .field input:focus, 
    .field textarea:focus { 
      outline: none; 
      background: #ffffff;
      box-shadow: var(--shadow-sm);
    }

    .field textarea { resize: vertical; min-height: 90px; }
    .inline-fields { display: flex; gap: 20px; flex-wrap: wrap; }
    .inline-fields .field { flex: 1; min-width: 200px; }
    
    .hint { 
      font-size: 0.85rem; 
      color: var(--text-secondary); 
      margin-top: 6px; 
      font-weight: 600;
    }

    .error { 
      background: var(--error-bg);
      border: 2.5px solid var(--error-border);
      color: var(--text-primary); 
      padding: 12px 18px;
      border-radius: 8px;
      font-size: 0.95rem; 
      font-weight: 800; 
      margin-bottom: 24px; 
      box-shadow: var(--shadow-sm);
    }

    .success { 
      background: #e6fcf5;
      border: 2.5px solid var(--accent-green-dark);
      color: var(--text-primary); 
      padding: 12px 18px;
      border-radius: 8px;
      font-size: 0.95rem; 
      font-weight: 800; 
      margin-bottom: 24px; 
      box-shadow: var(--shadow-sm);
    }

    button[type="submit"], .btn { 
      padding: 10px 22px; 
      background: var(--text-primary); 
      color: #fff; 
      border: 2.5px solid var(--border); 
      border-radius: 8px; 
      font-size: 0.95rem; 
      font-weight: 800;
      cursor: pointer; 
      font-family: 'Nunito', sans-serif; 
      text-decoration: none; 
      display: inline-flex; 
      align-items: center;
      box-shadow: var(--shadow-sm);
      transition: all 0.1s ease;
    }

    button[type="submit"]:hover, .btn:hover { 
      background: #2a2a44; 
      transform: translate(-1px, -1px);
      box-shadow: var(--shadow-btn);
    }
    
    button[type="submit"]:active, .btn:active {
      transform: translate(0,0);
      box-shadow: none;
    }

    .answer-row { 
      display: flex; 
      align-items: center; 
      gap: 12px; 
      margin-bottom: 12px; 
      background: #fbf9f3;
      padding: 10px 14px;
      border: 2px solid var(--border);
      border-radius: 8px;
    }
    
    .answer-row input[type="text"] { 
      flex: 1; 
      padding: 8px 12px;
      border: 2px solid var(--border);
      border-radius: 6px;
      font-family: 'Nunito', sans-serif;
      font-weight: 700;
    }
    
    .answer-row input[type="radio"] { 
      accent-color: var(--accent-blue); 
      width: 20px; 
      height: 20px; 
      cursor: pointer; 
    }
    
    .answer-row label { 
      font-size: 0.9rem; 
      font-weight: 800;
      color: var(--text-secondary); 
      white-space: nowrap; 
    }

    .table-wrapper {
      width: 100%;
      overflow-x: auto;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      box-shadow: var(--shadow-sm);
      background: var(--surface);
      margin-bottom: 28px;
    }

    table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
    thead tr { background: var(--accent-blue-light); border-bottom: 2.5px solid var(--border); }
    thead th { padding: 14px 16px; text-align: left; font-weight: 800; color: var(--text-primary); }
    tbody tr { border-bottom: 2px solid var(--border); }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: #fbf9f3; }
    tbody td { padding: 14px 16px; color: var(--text-primary); vertical-align: middle; font-weight: 700; }
    tbody td:first-child { font-family: 'Space Mono', monospace; font-weight: 700; }

    .opt-correct { 
      color: var(--accent-green-dark); 
      font-weight: 800; 
      background: #e6fcf5;
      padding: 2px 6px;
      border-radius: 4px;
      border: 1px dashed var(--accent-green);
    }
    
    .opt-empty { color: var(--text-muted); font-weight: 400; }
    
    .badge-type { 
      font-size: 0.8rem; 
      font-weight: 800;
      padding: 4px 10px; 
      border-radius: 6px; 
      background: var(--accent-blue-light); 
      color: var(--accent-blue); 
      border: 2px solid var(--border);
    }
    
    .section-box { 
      border: 2.5px solid var(--border); 
      border-radius: var(--radius); 
      padding: 28px; 
      margin-bottom: 32px; 
      background: var(--surface); 
      box-shadow: var(--shadow-sm);
    }
    
    .toggle-buttons { display: flex; gap: 14px; margin: 32px 0 20px; }
    
    .toggle-buttons button { 
      flex: 1; 
      padding: 12px; 
      border: 2.5px solid var(--border); 
      border-radius: 8px; 
      background: var(--surface); 
      cursor: pointer; 
      font-family: 'Nunito', sans-serif; 
      font-size: 1rem; 
      font-weight: 800;
      color: var(--text-primary);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
    
    .toggle-buttons button:hover {
      background: #f1f5f9;
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }

    .toggle-buttons button.active { 
      background: var(--text-primary); 
      color: #fff; 
      box-shadow: none; 
      transform: none;
    }
    
    .info-box { 
      text-align: center; 
      color: var(--text-secondary); 
      padding: 30px; 
      font-size: 1rem; 
      font-weight: 700;
      background: #fbf9f3;
      border: 2px dashed var(--border);
      border-radius: 8px;
    }

    @media (max-width: 840px) {
      .top-bar { flex-direction: column; align-items: flex-start; }
      .nav-links { width: 100%; justify-content: flex-start; flex-wrap: wrap; }
      .container { padding: 20px; }
      .inline-fields { flex-direction: column; gap: 0; }
    }
  </style>
</head>
<body>

<div class="container">
  <div class="top-bar">
    <h2>Admin Dashboard — Manage Questions</h2>
    <div class="nav-links">
      <a href="admin_dashboard.php">Users</a>
      <a href="Create_Question.php" class="active">Questions</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <?php if ($error):   ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="GET" action="Create_Question.php" id="picker-form">
    <div class="inline-fields">
      <div class="field">
        <label>Language</label>
        <select name="language" id="language-picker" onchange="this.form.submit()" required>
          <option value="">-- Select Language --</option>
          <?php foreach ($allowed_languages as $lang): ?>
            <option value="<?= $lang ?>" <?= ($selected_language === $lang) ? 'selected' : '' ?>><?= $lang ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($selected_language): ?>
        <div class="field">
          <label>Level (1 - <?= $max_allowed_level ?>)</label>
          <select name="level" onchange="this.form.submit()" required>
            <option value="">-- Select Level --</option>
            <?php for ($lvl = 1; $lvl <= $max_allowed_level; $lvl++): ?>
              <option value="<?= $lvl ?>" <?= ($selected_level == $lvl) ? 'selected' : '' ?>>
                Level <?= $lvl ?><?= ($lvl > $max_level) ? ' (New)' : '' ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($selected_language && $selected_level !== null): ?>

    <?php if (!empty($existing_questions)): ?>
      <h3><?= htmlspecialchars($selected_language) ?> — Level <?= $selected_level ?> (<?= count($existing_questions) ?> questions)</h3>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th style="width:60px">ID</th>
              <th style="width:80px">Type</th>
              <th>Question</th>
              <th>Option 1</th>
              <th>Option 2</th>
              <th>Option 3</th>
              <th>Option 4</th>
              <th style="width:70px">Edit</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($existing_questions as $q): ?>
              <tr>
                <td><?= $q['Question_ID'] ?></td>
                <td><span class="badge-type"><?= $q['Question_Type'] ?></span></td>
                <td><?= htmlspecialchars($q['Question_Text']) ?></td>
                <?php for ($i = 0; $i < 4; $i++): ?>
                  <td>
                    <?php if (isset($q['Answers'][$i])): ?>
                      <span class="<?= $q['Answers'][$i]['Is_Correct'] == 1 ? 'opt-correct' : '' ?>">
                        <?= htmlspecialchars($q['Answers'][$i]['answer_option']) ?>
                        <?= $q['Answers'][$i]['Is_Correct'] == 1 ? ' ✓' : '' ?>
                      </span>
                    <?php else: ?>
                      <span class="opt-empty">—</span>
                    <?php endif; ?>
                  </td>
                <?php endfor; ?>
                <td>
                  <a href="Create_Question.php?language=<?= urlencode($selected_language) ?>&level=<?= $selected_level ?>&edit_id=<?= $q['Question_ID'] ?>#edit-section"
                     class="btn" style="padding:5px 14px; font-size:0.78rem; box-shadow: var(--shadow-sm);">Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="toggle-buttons">
        <button type="button" id="btn-show-edit" class="<?= $editing_question ? 'active' : '' ?>" onclick="showSection('edit')">✏️ Edit a Question</button>
        <button type="button" id="btn-show-add" class="<?= !$editing_question ? 'active' : '' ?>" onclick="showSection('add')">➕ Add New Question</button>
      </div>

      <div class="section-box" id="edit-section" style="display:<?= $editing_question ? 'block' : 'none' ?>;">
        <?php if ($editing_question): ?>
          <h3>Editing Question #<?= $editing_question['Question_ID'] ?> (<?= $editing_question['Question_Type'] ?>)</h3>

          <form method="POST" action="Create_Question.php?language=<?= urlencode($selected_language) ?>&level=<?= $selected_level ?>">
            <input type="hidden" name="question_id" value="<?= $editing_question['Question_ID'] ?>"/>
            <input type="hidden" name="question_type" value="<?= $editing_question['Question_Type'] ?>"/>

            <div class="field">
              <label>Question Text</label>
              <textarea name="question_text" required><?= htmlspecialchars($editing_question['Question_Text']) ?></textarea>
            </div>

            <?php if ($editing_question['Question_Type'] === 'MCQ'): ?>
              <label style="display:block; font-size:0.95rem; font-weight:800; color:var(--text-primary); margin-bottom:12px;">
                Answer Options <span class="hint">(select the correct answer)</span>
              </label>
              <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php $existing_ans = $editing_answers[$i - 1] ?? null; ?>
                <div class="answer-row">
                  <input type="hidden" name="answer_id_<?= $i ?>" value="<?= $existing_ans ? $existing_ans['Answer_ID'] : '' ?>"/>
                  <input type="text" name="answer_<?= $i ?>" placeholder="Option <?= $i ?> (leave blank to remove)"
                         value="<?= $existing_ans ? htmlspecialchars($existing_ans['answer_option']) : '' ?>" />
                  <input type="radio" name="correct_answer" value="<?= $i ?>"
                         <?= ($existing_ans && $existing_ans['Is_Correct'] == 1) ? 'checked' : '' ?> />
                  <label>Correct</label>
                </div>
              <?php endfor; ?>

            <?php elseif ($editing_question['Question_Type'] === 'FITB'): ?>
              <div class="field">
                <label>Answer Key</label>
                <input type="hidden" name="answer_id_1" value="<?= $editing_answers[0]['Answer_ID'] ?? '' ?>"/>
                <input type="text" name="answer_1" value="<?= htmlspecialchars($editing_answers[0]['answer_option'] ?? '') ?>" required />
              </div>
            <?php endif; ?>

            <button type="submit" name="update_question">Save Changes</button>
          </form>
        <?php else: ?>
          <p class="info-box">Click "Edit" next to a question in the table above to modify it here.</p>
        <?php endif; ?>
      </div>

      <div class="section-box" id="add-section" style="display:<?= $editing_question ? 'none' : 'block' ?>;">
        <h3>Add New Question — <?= htmlspecialchars($selected_language) ?>, Level <?= $selected_level ?></h3>
        <form method="POST" action="Create_Question.php?language=<?= urlencode($selected_language) ?>&level=<?= $selected_level ?>">
          <input type="hidden" name="language" value="<?= htmlspecialchars($selected_language) ?>"/>
          <input type="hidden" name="level" value="<?= $selected_level ?>"/>

          <div class="field">
            <label>Question Type</label>
            <select name="question_type" id="add_question_type" onchange="toggleAddFields()" required>
              <option value="MCQ">Multiple Choice Question (MCQ)</option>
              <option value="FITB">Fill in the Blank (FITB)</option>
            </select>
          </div>

          <div class="field">
            <label>Question Text</label>
            <textarea name="question_text" placeholder="Enter the question here..." required></textarea>
          </div>

          <div id="add_mcq_fields">
            <div class="field">
              <label>Number of Options</label>
              <select name="num_options" id="num_options" onchange="generateOptionInputs()">
                <option value="2">2 Options</option>
                <option value="3">3 Options</option>
                <option value="4" selected>4 Options</option>
              </select>
            </div>

            <label style="display:block; font-size:0.95rem; font-weight:800; color:var(--text-primary); margin-bottom:12px;">
              Answer Options <span class="hint">(select the correct one)</span>
            </label>
            
            <div id="dynamic_options_container">
              <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="answer-row option-group-<?= $i ?>">
                  <input type="text" name="answer_<?= $i ?>" placeholder="Option <?= $i ?>" />
                  <input type="radio" name="correct_answer" value="<?= $i ?>" <?= $i === 1 ? 'checked' : '' ?> />
                  <label>Correct</label>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <div id="add_fitb_fields" style="display: none;">
            <div class="field">
              <label>Correct Answer Key</label>
              <input type="text" name="answer_1" id="fitb_answer" placeholder="Exact accepted answer phrase" />
            </div>
          </div>

          <button type="submit" name="add_question" style="margin-top: 10px;">Create Question</button>
        </form>
      </div>

    <?php else: ?>
      <h3>No questions yet for <?= htmlspecialchars($selected_language) ?> — Level <?= $selected_level ?></h3>
      <p class="hint" style="margin-bottom:20px; font-weight: 700;">This is a new level. Add the first question below.</p>

      <div class="section-box">
        <form method="POST" action="Create_Question.php?language=<?= urlencode($selected_language) ?>&level=<?= $selected_level ?>">
          <input type="hidden" name="language" value="<?= htmlspecialchars($selected_language) ?>"/>
          <input type="hidden" name="level" value="<?= $selected_level ?>"/>

          <div class="field">
            <label>Question Type</label>
            <select name="question_type" id="add_question_type" onchange="toggleAddFields()" required>
              <option value="MCQ">Multiple Choice Question (MCQ)</option>
              <option value="FITB">Fill in the Blank (FITB)</option>
            </select>
          </div>

          <div class="field">
            <label>Question Text</label>
            <textarea name="question_text" placeholder="Enter the question here..." required></textarea>
          </div>

          <div id="add_mcq_fields">
            <div class="field">
              <label>Number of Options</label>
              <select name="num_options" id="num_options" onchange="generateOptionInputs()">
                <option value="2">2 Options</option>
                <option value="3">3 Options</option>
                <option value="4" selected>4 Options</option>
              </select>
            </div>

            <label style="display:block; font-size:0.95rem; font-weight:800; color:var(--text-primary); margin-bottom:12px;">
              Answer Options <span class="hint">(select the correct one)</span>
            </label>
            
            <div id="dynamic_options_container">
              <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="answer-row option-group-<?= $i ?>">
                  <input type="text" name="answer_<?= $i ?>" placeholder="Option <?= $i ?>" />
                  <input type="radio" name="correct_answer" value="<?= $i ?>" <?= $i === 1 ? 'checked' : '' ?> />
                  <label>Correct</label>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <div id="add_fitb_fields" style="display: none;">
            <div class="field">
              <label>Correct Answer Key</label>
              <input type="text" name="answer_1" id="fitb_answer" placeholder="Exact accepted answer phrase" />
            </div>
          </div>

          <button type="submit" name="add_question" style="margin-top: 10px;">Create Question</button>
        </form>
      </div>
    <?php endif; ?>

  <?php elseif ($selected_language): ?>
    <p class="info-box">Select a level above to continue.</p>
  <?php else: ?>
    <p class="info-box">Select a language above to get started.</p>
  <?php endif; ?>

</div>

<script>
  function showSection(which) {
    document.getElementById('edit-section').style.display = (which === 'edit') ? 'block' : 'none';
    document.getElementById('add-section').style.display   = (which === 'add')  ? 'block' : 'none';
    document.getElementById('btn-show-edit').classList.toggle('active', which === 'edit');
    document.getElementById('btn-show-add').classList.toggle('active', which === 'add');
  }

  function toggleAddFields() {
    const type = document.getElementById('add_question_type').value;
    const mcqBox = document.getElementById('add_mcq_fields');
    const fitbBox = document.getElementById('add_fitb_fields');
    const fitbInput = document.getElementById('fitb_answer');

    if (type === 'MCQ') {
      mcqBox.style.display = 'block';
      fitbBox.style.display = 'none';
      if(fitbInput) fitbInput.removeAttribute('required');
    } else {
      mcqBox.style.display = 'none';
      fitbBox.style.display = 'block';
      if(fitbInput) fitbInput.setAttribute('required', 'true');
    }
  }

  function generateOptionInputs() {
    const count = parseInt(document.getElementById('num_options').value);
    for (let i = 1; i <= 4; i++) {
      const groups = document.querySelectorAll(`.option-group-${i}`);
      groups.forEach(row => {
        const input = row.querySelector('input[type="text"]');
        if (i <= count) {
          row.style.display = 'flex';
          if(input) input.setAttribute('required', 'true');
        } else {
          row.style.display = 'none';
          if(input) {
            input.removeAttribute('required');
            input.value = '';
          }
        }
      });
    }
  }
  
  // Set initial dynamic attributes on load
  generateOptionInputs();
</script>

</body>
</html>