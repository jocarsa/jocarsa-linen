<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            header("Location: ?action=panel");
            exit;
        } else {
            $error = "Credenciales incorrectas.";
        }
    }
    renderHeader("Login");
    echo "<h2>Login</h2>";
    if (isset($error)) {
        echo "<p class='error'>" . $error . "</p>";
    }
    echo "<form method='post' action='?action=login'>
          <label>Usuario:</label>
          <input type='text' name='username' required /><br/>
          <label>Contraseña:</label>
          <input type='password' name='password' required /><br/>
          <input type='submit' value='Entrar' />
          </form>";
    renderFooter();
    exit;
?>
