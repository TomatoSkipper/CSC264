<?php
session_start();
$host = "localhost";
$db_name = "language_learning";
$db_username  = "root";
$db_password = "";
 
$conn = mysqli_connect($host, $db_username, $db_password, $db_name);
 
 //Database Not Connnected

 if(!$conn){
	die("Connection failed: ".mysqli_connect_error());
}
 
// if(!isset($_SESSION['User_ID'])) {
// 	header("Location: MainPage.php");
// 	exit();
// }

// if(isset($_SESSION['Admin_ID'])){
// 	header("Location: MainPage.php");
// 	exit();
// }

$error = '';
$banned = false;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
	$role = $_POST['role'];
	$username = $_POST['username'];
	$password = $_POST['password'];

	$allowed_roles = ['Admin', 'Player'];

	if(empty($username) || empty($password)){
		$error = "Please fill in all fields.";
	} elseif(!in_array($role, $allowed_roles)){
		$error = "Please select a valid role.";
	} else {
		if($role === 'Player'){
			$stmt = $conn->prepare("SELECT User_ID, Username, password, Is_Banned
									            FROM User_Detail
									            WHERE Username = ?");
			$stmt->bind_param("s", $username);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();
			$stmt->close();

			if($row && password_verify($password, $row['password'])){

        if($row['Is_Banned'] == 1){
          $banned = true;
        }else{
				  $_SESSION['User_ID'] = $row['User_ID'];
				  $_SESSION['username'] = $row['Username'];

				  $stmt = $conn->prepare("UPDATE Current_Progress
										              SET LastLogIn = NOW()
										              WHERE User_ID = ?");
				  $stmt->bind_param("i", $row['User_ID']);
				  $stmt->execute();
				  $stmt->close();

      
				  header("Location: MainPage.php");
				  exit();
        }
			} else {
				$error = "Invalid Username or Password.";
			}
		}elseif($role === 'Admin'){
			$stmt = $conn->prepare("SELECT Admin_ID, Admin_Username, Admin_Password
									FROM Admin_Details
									WHERE Admin_Username = ?");
			$stmt->bind_param("s", $username);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();
			$stmt->close();

			if($row && $password === $row['Admin_Password']){
				$_SESSION['Admin_ID'] = $row['Admin_ID'];
				$_SESSION['username'] = $row['Admin_Username'];

				$stmt = $conn->prepare("UPDATE Admin_Details
										SET LastLogIn = NOW()
										WHERE Admin_ID = ?");
				$stmt->bind_param("i", $row['Admin_ID']);
				$stmt->execute();
				$stmt->close();
      
				header("Location: Admin_Dashboard.php");
				exit();
			} else {
				$error = "Invalid Username or Password.";
			}
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
  <title>Login</title>
  <style>
    :root {
      --bg: #f5f0e8;
      --surface: #fffdf7;
      --border: #1a1a2e;
      --accent-green: #22c55e;
      --accent-green-dark: #16a34a;
      --accent-blue: #3b5bdb;
      --accent-blue-light: #e8eeff;
      --accent-black: black;
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
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }
	
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }
 
    .container {
      animation: cardIn 0.4s ease both;
      background: var(--surface);
      border-radius: var(--radius);
      padding: 36px 30px;
      max-width: 420px;
      width: 100%;
      border: 2.5px solid var(--border);
      box-shadow: var(--shadow);
      position: relative; 
      overflow: hidden;
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
      margin-bottom: 24px;
    }

    form {
      width: 100%;
    }
 
    /* Role selector design updates */
    .role-group {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
    }
 
    .role-option {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 12px;
      border: 2.5px solid var(--border);
      border-radius: 8px;
      cursor: pointer;
      background: var(--surface);
      box-shadow: var(--shadow-sm);
      transition: all 0.15s ease;
    }
 
    .role-option:hover { 
      background: var(--accent-blue-light); 
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }

    /* Highlight matching active selection choices dynamically */
    .role-option:has(input[type="radio"]:checked) {
      background: var(--accent-blue);
    }

    .role-option:has(input[type="radio"]:checked) label {
      color: #ffffff;
    }

    .role-option:has(input[type="radio"]:checked) input[type="radio"] {
      accent-color: #ffffff;
      outline: 2px solid var(--border);
    }
 
    .role-option input[type="radio"] {
      accent-color: var(--accent-blue);
      width: 16px;
      height: 16px;
      cursor: pointer;
    }
 
    .role-option label {
      cursor: pointer;
      font-size: 0.95rem;
      font-weight: 800;
      color: var(--text-primary);
      user-select: none;
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
 
    .register-link {
      text-align: center;
      margin-top: 24px;
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-secondary);
    }
 
    .register-link a {
      color: var(--accent-blue);
      text-decoration: none;
      font-weight: 800;
      border-bottom: 2px solid var(--accent-blue);
    }

    .register-link a:hover {
      color: var(--text-primary);
      border-bottom-color: var(--text-primary);
    }

    /* Brutalist Ban popup modal */
    .popup-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(26, 26, 46, 0.7);
      backdrop-filter: blur(4px);
      justify-content: center;
      align-items: center;
      z-index: 999;
      padding: 20px;
    }
 
    .popup-overlay.show { 
      display: flex; 
    }
 
    .popup-box {
      background: var(--surface);
      border: 3px solid var(--border);
      border-radius: var(--radius);
      padding: 36px 30px;
      max-width: 380px;
      width: 100%;
      text-align: center;
      box-shadow: 8px 8px 0px var(--border);
      animation: cardIn 0.3s ease-out;
    }
 
    .popup-box h3 {
      font-size: 1.5rem;
      font-weight: 900;
      color: var(--error-border);
      margin-bottom: 12px;
    }
 
    .popup-box p {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-secondary);
      margin-bottom: 24px;
      line-height: 1.5;
    }
 
    .popup-box button {
      width: 100%;
      padding: 12px;
      background: var(--border);
      color: #ffffff;
      border: 2.5px solid var(--border);
      border-radius: 8px;
      font-weight: 800;
      box-shadow: var(--shadow-sm);
    }

    .popup-box button:hover {
      background: #333;
      transform: translate(-1px, -1px);
      box-shadow: 3px 3px 0px var(--border);
    }

    /* Responsive queries updates */
    @media (max-width:680px) {
      .top-cards, .ach-summary { grid-template-columns:1fr; }
      aside { width:60px; }
      .nav-item span:not(.icon){ display:none; }
      .nav-item { justify-content:center; padding:14px; margin-right:0; border-radius:0; }
      .podium { gap:8px; padding:16px 8px 0; }
    }
  </style>
</head>
<body>

<div class="popup-overlay" id="ban-popup">
  <div class="popup-box">
    <h3>🛑 Account Banned</h3>
    <p>We are sorry, your account has been <strong>BANNED</strong> from the platform.</p>
    <button onclick="document.getElementById('ban-popup').classList.remove('show')">Close Window</button>
  </div>
</div>
 
<div class="container">
  <h2>Welcome Back To Language Learning Platform</h2>
  <p class="subtitle">Log in to continue your adventure.</p>
 
  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
 
  <form method="POST" action="login.php">
 
    <div class="role-group">
      <div class="role-option" onclick="this.querySelector('input').checked=true">
        <input type="radio"
               name="role"
               value="Player"
               <?= (!isset($_POST['role']) || $_POST['role'] === 'Player') ? 'checked' : '' ?>
               required />
        <label>Player</label>
      </div>
      <div class="role-option" onclick="this.querySelector('input').checked=true">
        <input type="radio"
               name="role"
               value="Admin"
               <?= (isset($_POST['role']) && $_POST['role'] === 'Admin') ? 'checked' : '' ?> />
        <label>Admin</label>
      </div>
    </div>
 
    <div class="field">
      <label>Username</label>
      <input type="text"
             name="username"
             value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
             placeholder="Enter your username"
             required />
    </div>
 
    <div class="field">
      <label>Password</label>
      <input type="password"
             name="password"
             placeholder="Enter your password"
             required />
    </div>
 
    <button type="submit">Log In 🚀</button>
 
  </form>
 
  <div class="register-link">
    Don't have an account? <a href="Register.php">Register</a>
  </div>
 
</div>

<?php if($banned) : ?>
  <script>
    document.getElementById('ban-popup').classList.add('show');
   </script> 
<?php endif; ?>
</body>
</html>