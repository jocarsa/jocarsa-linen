<?php
	$project_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$project_id || !isset($_SESSION['user_id'])) {
        header("Location: ?action=panel");
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        header("Location: ?action=panel");
        exit;
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = isset($_POST['title']) ? $_POST['title'] : '';
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        if (trim($title) == '') {
            $error = "El título es obligatorio.";
        } else {
            $stmt = $db->prepare("UPDATE projects SET title = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $description, $project_id]);
            header("Location: ?action=panel");
            exit;
        }
    }

    renderHeader("Editar Información del Proyecto");
    echo "<h2>Editar Información del Proyecto</h2>";
    if ($error) {
        echo "<p class='error'>" . $error . "</p>";
    }
    echo "<form method='post' action='?action=edit_project_info&id=" . $project_id . "'>";
    echo "<label>Título:</label>";
    echo "<input type='text' name='title' value='" . htmlspecialchars($project['title']) . "' required /><br/>";
    echo "<label>Descripción:</label>";
    echo "<textarea name='description'>" . htmlspecialchars($project['description']) . "</textarea><br/>";
    echo "<input type='submit' value='Guardar Cambios' />";
    echo "</form>";
    echo "<p><a href='?action=panel'>Volver al Panel</a></p>";
    renderFooter();
    exit;
?>
