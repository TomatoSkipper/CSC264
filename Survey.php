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

// if(!isset($_SESSION['User_ID'])){
//      header("Location: register.php");
//      exit();
// }

$user_id = $_SESSION['User_ID'];

$stmt = $conn ->prepare("SELECT User_ID 
                      FROM user_detail 
                      WHERE User_ID = ?");
$stmt -> bind_param("i", $user_id);
$stmt -> execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$exists) {
    header("Location: register.php");
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $age = (int)$_POST['age'];
    $reason_learning = $_POST['reason_learning'];
    $how_user_know = $_POST['how_user_know'];

    $allowed_reasons = ['School', 'Travel', 'Work', 'Personal Interest', 'Other'];
    $allowed_sources = ['Social Media', 'Friend', 'Google', 'Advertisement', 'Other'];

    if($age <= 0){
        $error = "Please enter a valid age.";
    } elseif(!in_array($reason_learning, $allowed_reasons)){
        $error = "Please select a valid reason for learning.";
    } elseif(!in_array($how_user_know, $allowed_sources)){
        $error = "Please select a valid source for how you heard about us.";
    } else {
        $stmt = $conn->prepare("INSERT INTO user_survey (User_ID, Age, Reason_Learning, How_User_Know)
                                VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $user_id, $age, $reason_learning, $how_user_know);
        $stmt->execute();
        $stmt->close();

        header("Location: Choose_Language.php");
        exit();
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
  <title>Survey</title>
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
      --error-bg: #ffebe9;
      --error-border: #ea4335;
      --radius: 14px;
      --shadow: 4px 4px 0px #1a1a2e;
      --shadow-sm: 2px 2px 0px #1a1a2e;
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
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 30px 20px;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }
 
    .container {
      background: var(--surface);
      border: 2.5px solid var(--border); 
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 36px 30px;
      max-width: 440px;
      width: 100%;
      animation: cardIn 0.4s ease both;
    }

    .container:hover {
      transform: translate(-2px, -2px);
      box-shadow: 6px 6px 0px var(--border);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
 
    h2 {
      font-size: 1.75rem;
      font-weight: 900;
      letter-spacing: -0.5px;
      margin-bottom: 4px;
      color: var(--text-primary);
    }
 
    .subtitle {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-secondary);
      margin-bottom: 32px;
      line-height: 1.4;
    }

    form {
      width: 100%;
    }
 
    .question-block {
      margin-bottom: 28px;
    }
 
    .question-block label.question-label {
      display: block;
      font-size: 1rem;
      font-weight: 800;
      color: var(--text-primary);
      margin-bottom: 12px;
    }
 
    /* Age input styling */
    .question-block input[type="number"] {
      width: 110px;
      padding: 12px 14px;
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: 8px;
      font-size: 1rem;
      font-family: 'Nunito', sans-serif;
      font-weight: 700;
      color: var(--text-primary);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
 
    .question-block input[type="number"]:focus {
      outline: none;
      background: #ffffff;
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }
 
    /* Radio options wrapper */
    .radio-group {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
 
    .radio-option {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      border: 2.5px solid var(--border);
      border-radius: 8px;
      cursor: pointer;
      background: var(--surface);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
 
    .radio-option:hover { 
      background: var(--accent-blue-light);
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }

    /* Highlight matching current select choice dynamic target */
    .radio-option:has(input[type="radio"]:checked) {
      background: var(--accent-blue);
    }

    .radio-option:has(input[type="radio"]:checked) label {
      color: #ffffff;
    }

    .radio-option:has(input[type="radio"]:checked) input[type="radio"] {
      accent-color: #ffffff;
      outline: 2px solid var(--border);
    }
 
    .radio-option input[type="radio"] {
      accent-color: var(--accent-blue);
      width: 16px;
      height: 16px;
      cursor: pointer;
    }
 
    .radio-option label {
      cursor: pointer;
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-primary);
      flex: 1;
      user-select: none;
    }
 
    /* Error Banner component */
    .error {
      background: var(--error-bg);
      color: var(--border);
      border: 2.5px solid var(--error-border);
      border-radius: 8px;
      padding: 12px 16px;
      font-size: 0.9rem;
      font-weight: 800;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
    }
 
    /* Form action submit */
    button[type="submit"] {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 14px 24px;
      background: var(--accent-green);
      color: #ffffff;
      border: 2.5px solid var(--border);
      border-radius: 9px;
      font-family: 'Nunito', sans-serif;
      font-weight: 800;
      font-size: 1rem;
      cursor: pointer;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
      margin-top: 8px;
    }
 
    button[type="submit"]:hover { 
      background: var(--accent-green-dark);
      transform: translate(-2px, -2px);
      box-shadow: 4px 4px 0px var(--border);
    }

    button[type="submit"]:active {
      transform: translate(0px, 0px);
      box-shadow: 1px 1px 0px var(--border);
    }

    button:disabled { 
      background: #aaa; 
      cursor: not-allowed; 
      box-shadow: none;
      transform: none;
    }
  </style>
</head>
<body>
 
<div class="container">
  <h2>Quick Survey</h2>
  <p class="subtitle">Help us personalise your experience. Just 3 quick questions!</p>
 
  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
 
  <form method="POST" action="survey.php">
 
    <div class="question-block">
      <label class="question-label">1. How old are you?</label>
      <input type="number"
             name="age"
             min="1"
             max="100"
             value="<?= isset($_POST['age']) ? (int)$_POST['age'] : '' ?>"
             placeholder="e.g. 22"
             required />
    </div>
 
    <div class="question-block">
      <label class="question-label">2. Why are you learning a new language?</label>
      <div class="radio-group">
        <?php
          $reasons = ['School', 'Travel', 'Work', 'Personal Interest', 'Other'];
          foreach ($reasons as $r):
            $checked = (isset($_POST['reason_learning']) && $_POST['reason_learning'] === $r) ? 'checked' : '';
        ?>
          <div class="radio-option" onclick="this.querySelector('input').checked=true">
            <input type="radio" name="reason_learning" value="<?= $r ?>" <?= $checked ?> required />
            <label><?= $r ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
 
    <div class="question-block">
      <label class="question-label">3. How did you hear about us?</label>
      <div class="radio-group">
        <?php
          $sources = ['Social Media', 'Friend', 'Google', 'Advertisement', 'Other'];
          foreach ($sources as $s):
            $checked = (isset($_POST['how_user_know']) && $_POST['how_user_know'] === $s) ? 'checked' : '';
        ?>
          <div class="radio-option" onclick="this.querySelector('input').checked=true">
            <input type="radio" name="how_user_know" value="<?= $s ?>" <?= $checked ?> required />
            <label><?= $s ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
 
    <button type="submit" id="submit-btn">Submit Survey 🚀</button>
 
  </form>
</div>
 
</body>
</html>