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
//     header("Location: register.php");
//     exit();
// }

$user_id = $_SESSION['User_ID'];

$stmt = $conn->prepare("SELECT Language 
                        FROM Current_Progress 
                        WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$exist = $stmt->get_result()->fetch_assoc();
$stmt->close();

if($exist){
    header("Location: MainPage.php");
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $language = $_POST['language'];

    $allowed_languages = ['Malay', 'Chinese'];

    if(!in_array($language, $allowed_languages)){
        $error = "Please select a valid language.";
    } else {
        $stmt = $conn->prepare("INSERT INTO Current_Progress (User_ID, Language, Current_Level, LastlogIn)
                                 VALUES (?, ?, 1, NOW())");
        $stmt->bind_param("is", $user_id, $language);
        $stmt->execute();
        $stmt->close();

        $_SESSION['show_reminder'] = true;

        header("Location: MainPage.php");
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
  <title>Choose Language</title>
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
      padding: 20px;
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
      max-width: 420px;
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
      color: var(--accent-blue);
      margin-bottom: 28px;
      line-height: 1.4;
    }

    form {
      width: 100%;
    }
 
    .radio-group {
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-bottom: 28px;
    }
 
    .radio-option {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 20px;
      border: 2.5px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      background: var(--surface);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
 
    .radio-option:hover { 
      background: var(--accent-blue-light); 
      transform: translate(-2px, -2px);
      box-shadow: 4px 4px 0px var(--border);
    }

    /* CSS state highlight when inner radio is selected */
    .radio-option:has(input[type="radio"]:checked) {
      background: var(--accent-blue);
      color: white;
    }
    
    .radio-option:has(input[type="radio"]:checked) label {
      color: white;
    }

    .radio-option:has(input[type="radio"]:checked) input[type="radio"] {
      accent-color: white;
      outline: 2px solid var(--border);
    }
 
    .radio-option input[type="radio"] {
      accent-color: var(--accent-blue);
      width: 18px;
      height: 18px;
      cursor: pointer;
    }
 
    .radio-option label {
      cursor: pointer;
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--text-primary);
      flex: 1;
      user-select: none;
    }
 
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
 
    button[type="submit"] {
      display: inline-flex; 
      align-items: center; 
      justify-content: center;
      gap: 8px;
      width: 100%;
      background: var(--accent-green); 
      color: white;
      border: 2.5px solid var(--border); 
      border-radius: 9px;
      font-family: 'Nunito', sans-serif; 
      font-weight: 800; 
      font-size: 1rem;
      padding: 12px 24px; 
      cursor: pointer; 
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease; 
      text-decoration: none;
    }
 
    button[type="submit"]:not(:disabled):hover { 
      background: var(--accent-green-dark); 
      transform: translate(-2px, -2px); 
      box-shadow: 4px 4px 0px var(--border); 
    }

    button[type="submit"]:not(:disabled):active {
      transform: translate(0px, 0px);
      box-shadow: 1px 1px 0px var(--border);
    }
 
    button:disabled { 
      background: #e2e8f0; 
      color: var(--text-muted);
      border-color: var(--text-muted);
      cursor: not-allowed; 
      box-shadow: none;
      transform: none;
    }
  </style>
</head>
<body>
 
<div class="container">
  <h2>Choose Your Language</h2>
  <p class="subtitle">Pick the language you want to learn. You can only choose one.</p>
 
  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
 
  <form method="POST" action="choose_language.php">
 
    <div class="radio-group">
      <?php
        $languages = ['Malay', 'Chinese'];
        foreach ($languages as $lang):
          $checked = (isset($_POST['language']) && $_POST['language'] === $lang) ? 'checked' : '';
      ?>
        <div class="radio-option" onclick="this.querySelector('input').checked=true; enableSubmit();">
          <input type="radio"
                 name="language"
                 value="<?= $lang ?>"
                 <?= $checked ?>
                 required />
          <label><?= $lang ?></label>
        </div>
      <?php endforeach; ?>
    </div>
 
    <button type="submit" id="submit-btn" disabled>Confirm 🚀</button>
 
  </form>
</div>
 
<script>
  function enableSubmit() {
    document.getElementById('submit-btn').disabled = false;
  }

  // Handle default submission state activation if a post option was natively preserved
  window.addEventListener('DOMContentLoaded', () => {
    if(document.querySelector('input[type="radio"]:checked')) {
      enableSubmit();
    }
  });
</script>
 
</body>
</html>