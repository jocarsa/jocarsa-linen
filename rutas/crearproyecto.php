<?php

	$error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = isset($_POST['title']) ? $_POST['title'] : '';
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        if (trim($title) == '') {
            $error = "El título es obligatorio.";
        } else {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO projects (user_id, title, description) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $description]);
            header("Location: ?action=panel");
            exit;
        }
    }
    renderHeader("Crear Proyecto");
    echo "<h2>Crear Nuevo Proyecto</h2>";
    if ($error) {
        echo "<p class='error'>" . $error . "</p>";
    }
    echo "<form method='post' action='?action=create_project'>
          <label>Título:</label>
          <input type='text' name='title' required /><br/>
          <label>Descripción:</label>
          <textarea name='description'></textarea><br/>
          <input type='submit' value='Crear Proyecto' />
          </form>";
    echo "<p><a href='?action=panel'>Volver al Panel</a></p>";
    renderFooter();
    exit;
?>
