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
if (!isset($_SESSION['Admin_ID'])) {
    header("Location: login.php");
    exit();
}

// ─────────────────────────────────────────
// Initialize
// ─────────────────────────────────────────
$error             = '';
$success           = '';
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — Manage Questions</title>
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
      padding: 30px 20px; 
    }
    
    .container {
      max-width: 1000px;
      margin: 0 auto;
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: var(--radius);
      padding: 32px;
      box-shadow: var(--shadow);
      animation: cardIn 0.4s ease both;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Top Bar & Navigation ── */
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
      border-bottom: 2.5px solid var(--border);
      padding-bottom: 18px;
    }

    h2 {
      font-size: 1.5rem;
      font-weight: 900;
      letter-spacing: -0.5px;
      color: var(--text-primary);
    }

    h3 {
      font-size: 1.2rem;
      font-weight: 900;
      color: var(--text-primary);
      margin: 28px 0 16px;
      letter-spacing: -0.3px;
    }

    .nav-links {
      display: flex;
      gap: 12px;
    }

    .nav-links a {
      padding: 8px 18px;
      border: 2.5px solid var(--border);
      border-radius: 8px;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 800;
      color: var(--text-secondary);
      background: var(--surface);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }

    .nav-links a:hover {
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
      background: var(--accent-blue-light);
      color: var(--accent-blue);
    }

    .nav-links a.active {
      background: var(--accent-blue);
      color: white;
      border-color: var(--accent-blue);
      box-shadow: 2px 2px 0px var(--border);
    }

    /* ── Form Fields & Inputs ── */
    .field {
      margin-bottom: 20px;
    }

    .field label {
      display: block;
      font-size: 0.9rem;
      font-weight: 800;
      color: var(--text-primary);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .field select,
    .field input[type="text"],
    .field input[type="number"],
    .field textarea {
      width: 100%;
      padding: 12px 16px;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      font-size: 0.95rem;
      font-family: 'Nunito', sans-serif;
      font-weight: 700;
      background: var(--surface);
      color: var(--text-primary);
      box-shadow: inset 2px 2px 0px rgba(0,0,0,0.02);
      outline: none;
    }

    .field select:focus,
    .field input:focus,
    .field textarea:focus {
      border-color: var(--accent-blue);
    }

    .field textarea {
      resize: vertical;
      min-height: 85px;
    }

    .inline-fields {
      display: flex;
      gap: 20px;
    }

    .inline-fields .field {
      flex: 1;
    }

    .hint {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--text-muted);
      margin-top: 4px;
    }

    /* ── Alerts & Notices ── */
    .error {
      background: #fff5f5;
      color: #ef4444;
      border: 2px solid #ef4444;
      padding: 12px 16px;
      border-radius: 8px;
      font-weight: 800;
      font-size: 0.95rem;
      margin-bottom: 20px;
    }

    .success {
      background: #f0fff4;
      color: var(--accent-green-dark);
      border: 2px solid var(--accent-green);
      padding: 12px 16px;
      border-radius: 8px;
      font-weight: 800;
      font-size: 0.95rem;
      margin-bottom: 20px;
    }

    /* ── Action Buttons ── */
    button[type="submit"],
    .btn {
      padding: 12px 24px;
      background: var(--accent-green);
      color: #fff;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      font-size: 0.95rem;
      font-weight: 900;
      cursor: pointer;
      font-family: 'Nunito', sans-serif;
      text-decoration: none;
      display: inline-block;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }

    button[type="submit"]:hover,
    .btn:hover {
      background: var(--accent-green-dark);
      transform: translate(-1px, -1px);
      box-shadow: 4px 4px 0px var(--border);
    }

    /* ── Layout Options / Rows ── */
    .answer-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      background: var(--bg);
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
      outline: none;
    }

    .answer-row input[type="radio"] {
      accent-color: var(--accent-blue);
      width: 18px;
      height: 18px;
      cursor: pointer;
    }

    .answer-row label {
      font-size: 0.9rem;
      font-weight: 800;
      color: var(--text-secondary);
      white-space: nowrap;
      cursor: pointer;
    }

    /* ── Brutalist Table View ── */
    .table-container {
      width: 100%;
      overflow-x: auto;
      margin-bottom: 24px;
      border: 2.5px solid var(--border);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      font-size: 0.9rem;
      background: var(--surface);
    }

    thead tr {
      background: var(--border);
      color: #fff;
    }

    thead th {
      padding: 14px 16px;
      text-align: left;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 0.8rem;
    }

    tbody tr {
      border-bottom: 2px solid var(--border);
    }

    tbody tr:last-child td {
      border-bottom: none;
    }

    tbody tr:hover {
      background: var(--accent-blue-light);
    }

    tbody td {
      padding: 14px 16px;
      color: var(--text-primary);
      vertical-align: top;
      font-weight: 700;
      border-bottom: 2px solid var(--border);
    }

    tbody td:first-child {
      font-family: 'Space Mono', monospace;
    }

    .opt-correct {
      color: var(--accent-green-dark);
      background: #f0fff4;
      padding: 2px 6px;
      border-radius: 4px;
      border: 1.5px solid var(--accent-green);
      font-weight: 800;
    }

    .opt-empty {
      color: var(--text-muted);
      font-weight: 400;
    }

    .badge-type {
      font-family: 'Space Mono', monospace;
      font-size: 0.75rem;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 6px;
      border: 1.5px solid var(--border);
      background: var(--accent-blue-light);
      color: var(--accent-blue);
    }

    /* ── Content Card Sections ── */
    .section-box {
      border: 2.5px solid var(--border);
      border-radius: var(--radius);
      padding: 24px;
      margin-bottom: 28px;
      background: #fffdfc;
      box-shadow: var(--shadow-sm);
      animation: cardIn 0.3s ease both;
    }

    /* ── Multi-Tab Switches ── */
    .toggle-buttons {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
    }

    .toggle-buttons button {
      flex: 1;
      padding: 12px;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      background: var(--surface);
      color: var(--text-primary);
      cursor: pointer;
      font-family: 'Nunito', sans-serif;
      font-weight: 800;
      font-size: 0.95rem;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }

    .toggle-buttons button:hover {
      background: var(--accent-blue-light);
    }

    .toggle-buttons button.active {
      background: var(--accent-blue);
      color: #fff;
      border-color: var(--accent-blue);
    }

    .info-box {
      text-align: center;
      color: var(--text-muted);
      padding: 32px;
      font-size: 1rem;
      font-weight: 700;
      border: 2px dashed var(--text-muted);
      border-radius: 10px;
      background: var(--bg);
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

  <?php if ($error):   ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

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

      <div class="table-container">
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
                     class="btn" style="padding:6px 14px; font-size:0.78rem;">Edit</a>
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

          <form method="POST" action="Create_Question.php?language=<?= urlencode($selected_language) ?>&level=<?= $selected_level ?>" onsubmit="return validateEditForm()">
            <input type="hidden" name="question_id" value="<?= $editing_question['Question_ID'] ?>"/>
            <input type="hidden" name="question_type" id="edit_question_type" value="<?= $editing_question['Question_Type'] ?>"/>

            <div class="field">
              <label>Question Text</label>
              <textarea name="question_text" required><?= htmlspecialchars($editing_question['Question_Text']) ?></textarea>
            </div>

            <?php if ($editing_question['Question_Type'] === 'MCQ'): ?>
              <label style="display:block; font-size:0.9rem; font-weight:bold; color:var(--text-primary); margin-bottom:10px;">
                Answer Options <span class="hint">(select the correct answer)</span>
              </label>
              <div id="edit_mcq_container">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                  <?php $existing_ans = $editing_answers[$i - 1] ?? null; ?>
                  <div class="answer-row edit-option-row" data-index="<?= $i ?>">
                    <input type="hidden" name="answer_id_<?= $i ?>" value="<?= $existing_ans ? $existing_ans['Answer_ID'] : '' ?>"/>
                    <input type="text" name="answer_<?= $i ?>" id="edit_answer_text_<?= $i ?>" placeholder="Option <?= $i ?> (leave blank to remove)"
                           value="<?= $existing_ans ? htmlspecialchars($existing_ans['answer_option']) : '' ?>" />
                    <input type="radio" name="correct_answer" value="<?= $i ?>"
                           <?= ($existing_ans && $existing_ans['Is_Correct'] == 1) ? 'checked' : '' ?> />
                    <label>Correct</label>
                  </div>
                <?php endfor; ?>
              </div>

            <?php elseif ($editing_question['Question_Type'] === 'FITB'): ?>
              <div class="field">
                <label>Answer Key</label>
                <input type="hidden" name="answer_id_1" value="<?= $editing_answers[0]['Answer_ID'] ?? '' ?>"/>
                <input type="text" name="answer_1" value="<?= htmlspecialchars($editing_answers[0]['answer_option'] ?? '') ?>" required style="font-family:'Space Mono', monospace;" />
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
        <form method="POST" action="Create_Question.php?language=<?= urlencode($selected_language) ?>&level=<?= $selected_level ?>" onsubmit="return validateAddForm('add_form_1')" id="add_form_1">
          <input type="hidden" name="language" value="<?= htmlspecialchars($selected_language) ?>"/>
          <input type="hidden" name="level" value="<?= $selected_level ?>"/>

          <div class="field">
            <label>Question Type</label>
            <select name="question_type" class="add_question_type" onchange="toggleAddFields('add_form_1')" required>
              <option value="MCQ">Multiple Choice Question (MCQ)</option>
              <option value="FITB">Fill in the Blank (FITB)</option>
            </select>
          </div>

          <div class="field">
            <label>Question Text</label>
            <textarea name="question_text" placeholder="Enter the question here..." required></textarea>
          </div>

          <div class="add_mcq_fields">
            <div class="field">
              <label>Number of Options</label>
              <select name="num_options" class="num_options" onchange="generateOptionInputs('add_form_1')">
                <option value="2">2 Options</option>
                <option value="3">3 Options</option>
                <option value="4" selected>4 Options</option>
              </select>
            </div>

            <label style="display:block; font-size:0.9rem; font-weight:bold; color:var(--text-primary); margin-bottom:10px;">
              Answer Options <span class="hint">(select the correct one)</span>
            </label>
            
            <div class="dynamic_options_container">
              <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="answer-row option-group-<?= $i ?>">
                  <input type="text" name="answer_<?= $i ?>" class="add_ans_input_<?= $i ?>" placeholder="Option <?= $i ?>" />
                  <input type="radio" name="correct_answer" value="<?= $i ?>" <?= $i === 1 ? 'checked' : '' ?> />
                  <label>Correct</label>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <div class="add_fitb_fields" style="display: none;">
            <div class="field">
              <label>Correct Answer Key</label>
              <input type="text" name="answer_1" class="fitb_answer" placeholder="Exact accepted answer phrase" style="font-family:'Space Mono', monospace;" />
            </div>
          </div>

          <button type="submit" name="add_question" style="margin-top: 10px;">Create Question</button>
        </form>
      </div>

    <?php else: ?>
      <h3>No questions yet for <?= htmlspecialchars($selected_language) ?> — Level <?= $selected_level ?></h3>
      <p class="hint" style="margin-bottom:20px; font-size:0.95rem;">This is a new level. Add the first question below.</p>

      <div class="section-box">
        <form method="POST" action="Create_Question.php?language=<?= urlencode($selected_language) ?>&level=<?= $selected_level ?>" onsubmit="return validateAddForm('add_form_2')" id="add_form_2">
          <input type="hidden" name="language" value="<?= htmlspecialchars($selected_language) ?>"/>
          <input type="hidden" name="level" value="<?= $selected_level ?>"/>

          <div class="field">
            <label>Question Type</label>
            <select name="question_type" class="add_question_type" onchange="toggleAddFields('add_form_2')" required>
              <option value="MCQ">Multiple Choice Question (MCQ)</option>
              <option value="FITB">Fill in the Blank (FITB)</option>
            </select>
          </div>

          <div class="field">
            <label>Question Text</label>
            <textarea name="question_text" placeholder="Enter the question here..." required></textarea>
          </div>

          <div class="add_mcq_fields">
            <div class="field">
              <label>Number of Options</label>
              <select name="num_options" class="num_options" onchange="generateOptionInputs('add_form_2')">
                <option value="2">2 Options</option>
                <option value="3">3 Options</option>
                <option value="4" selected>4 Options</option>
              </select>
            </div>

            <label style="display:block; font-size:0.9rem; font-weight:bold; color:var(--text-primary); margin-bottom:10px;">
              Answer Options <span class="hint">(select the correct one)</span>
            </label>
            
            <div class="dynamic_options_container">
              <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="answer-row option-group-<?= $i ?>">
                  <input type="text" name="answer_<?= $i ?>" class="add_ans_input_<?= $i ?>" placeholder="Option <?= $i ?>" />
                  <input type="radio" name="correct_answer" value="<?= $i ?>" <?= $i === 1 ? 'checked' : '' ?> />
                  <label>Correct</label>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <div class="add_fitb_fields" style="display: none;">
            <div class="field">
              <label>Correct Answer Key</label>
              <input type="text" name="answer_1" class="fitb_answer" placeholder="Exact accepted answer phrase" style="font-family:'Space Mono', monospace;" />
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

  function toggleAddFields(formId) {
    const form = document.getElementById(formId);
    const type = form.querySelector('.add_question_type').value;
    const mcqBox = form.querySelector('.add_mcq_fields');
    const fitbBox = form.querySelector('.add_fitb_fields');
    const fitbInput = form.querySelector('.fitb_answer');

    if (type === 'MCQ') {
      mcqBox.style.display = 'block';
      fitbBox.style.display = 'none';
      if(fitbInput) fitbInput.removeAttribute('required');
      generateOptionInputs(formId);
    } else {
      mcqBox.style.display = 'none';
      fitbBox.style.display = 'block';
      if(fitbInput) fitbInput.setAttribute('required', 'true');
      
      for (let i = 1; i <= 4; i++) {
        const input = form.querySelector(`.add_ans_input_${i}`);
        if(input) input.removeAttribute('required');
      }
    }
  }

  function generateOptionInputs(formId) {
    const form = document.getElementById(formId);
    const type = form.querySelector('.add_question_type').value;

    if (type !== 'MCQ') return;
    const count = parseInt(form.querySelector('.num_options').value);
    for (let i = 1; i <= 4; i++) {
      const row = form.querySelector(`.option-group-${i}`);
      const input = form.querySelector(`.add_ans_input_${i}`);
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
    }
  }

  function validateAddForm(formId) {
    const form = document.getElementById(formId);
    const type = form.querySelector('.add_question_type').value;
    
    if (type === 'MCQ') {
      const selectedRadio = form.querySelector('input[name="correct_answer"]:checked');
      if (!selectedRadio) {
        alert("Please choose which option is the correct answer.");
        return false;
      }
      
      const correctIndex = selectedRadio.value;
      const targetInput = form.querySelector(`.add_ans_input_${correctIndex}`);
      
      if (!targetInput || targetInput.value.trim() === "") {
        alert(`You selected 'Option ${correctIndex}' as correct, but that option text field is empty!`);
        return false;
      }
    } else if (type === 'FITB') {
        const fitbInput = form.querySelector('.fitb_answer');
        if (!fitbInput || fitbInput.value.trim() === "") {
          alert("Please provide a valid answer key text for the Fill in the Blank question.");
          return false;
        }
    }
    return true;
  }

  function validateEditForm() {
    const typeField = document.getElementById('edit_question_type');
    if (typeField && typeField.value === 'MCQ') {
      const selectedRadio = document.querySelector('#edit-section input[name="correct_answer"]:checked');
      if (!selectedRadio) {
        alert("Please choose which option is the correct answer.");
        return false;
      }

      const correctIndex = selectedRadio.value;
      const targetInput = document.getElementById(`edit_answer_text_${correctIndex}`);

      if (!targetInput || targetInput.value.trim() === "") {
        alert(`You marked 'Option ${correctIndex}' as correct, but you cannot leave the correct answer choice empty.`);
        return false;
      }
    }
    return true;
  }
  
  if(document.getElementById('add_form_1')) toggleAddFields('add_form_1');
  if(document.getElementById('add_form_2')) toggleAddFields('add_form_2');
</script>

</body>
</html>