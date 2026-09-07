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

// if(isset($_SESSION['User_ID'])){
// 	header("Location: MainPage.php");
// 	exit();
// }

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
	$username = $_POST['username'];
	$password = $_POST['password'];
	$email = $_POST['email'];

	if(empty($username) || empty($password) || empty($email)){
		$error = "Please fill in all fields.";
	} 
	else{
		$stmt = $conn->prepare("SELECT User_ID 
								FROM User_Detail
								WHERE Username = ?");
		$stmt->bind_param("s", $username);
		$stmt->execute();
		$existing_username = $stmt->get_result()->fetch_assoc();
		$stmt->close();

    $stmt = $conn->prepare("SELECT User_ID
                            FROM User_Detail
                            WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $existing_email = $stmt->get_result()->fetch_assoc();
    $stmt->close();

		if($existing_username){
			$error = "Username already exists. Please choose another.";
    }elseif($existing_email){
      $error = "Email is already registered";
    }else {
			$hashed_password = password_hash($password, PASSWORD_DEFAULT);

      $stmt = $conn->prepare("INSERT INTO User_Detail(Username, Password, Email)
                              VALUES (?, ?, ?)");
      $stmt->bind_param("sss", $username, $hashed_password, $email);
      $stmt->execute();
      $stmt->close();

			$_SESSION['User_ID'] = $conn->insert_id;
			$_SESSION['username'] = $username;

			header("Location: Survey.php");
			exit();
		}
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
  <title>Register</title>
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
    }

    form {
      width: 100%;
    }
 
    .field {
      margin-bottom: 20px;
      display: flex;
      flex-direction: column;
    }
 
    .field label {
      display: block;
      font-size: 0.85rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-secondary);
      margin-bottom: 8px;
    }
 
    .field input {
      width: 100%;
      padding: 12px 16px;
      background: var(--surface);
      border: 2.5px solid var(--border);
      border-radius: 8px;
      font-size: 0.95rem;
      font-family: 'Nunito', sans-serif;
      font-weight: 600;
      color: var(--text-primary);
      transition: all 0.15s ease;
    }
 
    .field input:focus {
      outline: none;
      background: #ffffff;
      box-shadow: var(--shadow-sm);
      transform: translate(-1px, -1px);
    }

    .field input::placeholder {
      color: var(--text-muted);
      font-weight: 400;
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
 
    .login-link {
      text-align: center;
      margin-top: 24px;
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-secondary);
    }
 
    .login-link a {
      color: var(--accent-blue);
      text-decoration: none;
      font-weight: 800;
      border-bottom: 2px solid var(--accent-blue);
    }

    .login-link a:hover {
      color: var(--text-primary);
      border-bottom-color: var(--text-primary);
    }
  </style>
</head>
<body>
 
<div class="container">
  <h2>Create Account</h2>
  <p class="subtitle">Sign up to start learning!</p>
 
  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
 
  <form method="POST" action="register.php">
 
    <div class="field">
      <label>Username</label>
      <input type="text"
             name="username"
             value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
             placeholder="Enter your username"
             required />
    </div>
 
    <div class="field">
      <label>Email</label>
      <input type="email"
             name="email"
             value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
             placeholder="Enter your email"
             required />
    </div>
 
    <div class="field">
      <label>Password</label>
      <input type="password"
             name="password"
             placeholder="Enter your password"
             required />
    </div>
 
    <button type="submit">Register🚀</button>
 
  </form>
 
  <div class="login-link">
    Already have an account? <a href="login.php">Log in</a>
  </div>
 
</div>
 
</body>
</html>