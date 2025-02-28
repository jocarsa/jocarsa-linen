<?php
	$db = getDB();
    $project_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$project_id) {
        header("Location: ?action=panel");
        exit;
    }
    // Verificar propiedad
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        renderHeader("Error");
        echo "<p class='error'>Proyecto no encontrado o no autorizado.</p>";
        renderFooter();
        exit;
    }

    // >>> NEW OR MODIFIED CODE <<<
    // Handling the creation AND editing of topics in one place:
    $error = '';

    // 1) Update existing topic (if "edit_topic" param and form is submitted)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_topic'])) {
        $topic_id   = isset($_POST['topic_id']) ? $_POST['topic_id'] : '';
        $title      = isset($_POST['topic_title'])   ? $_POST['topic_title']   : '';
        $content    = isset($_POST['topic_content']) ? $_POST['topic_content'] : '';
        $type       = isset($_POST['topic_type'])    ? $_POST['topic_type']    : 'text';
        $parent_id  = isset($_POST['parent_id'])    ? $_POST['parent_id']    : 0; // Allow changing parent

        if (trim($title) == '') {
            $error = "El título del tema es obligatorio.";
        } else {
            $stmt = $db->prepare("UPDATE topics
                                  SET title = ?, content = ?, type = ?, parent_id = ?
                                  WHERE id = ? AND project_id = ?");
            $stmt->execute([$title, $content, $type, $parent_id, $topic_id, $project_id]);
            header("Location: ?action=edit_project&id=" . $project_id . "&topic_id=" . $topic_id);
            exit;
        }
    }

    // 2) Create new topic (if "new_topic" form is submitted)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_topic'])) {
        $title     = isset($_POST['topic_title'])   ? $_POST['topic_title']   : '';
        $content   = isset($_POST['topic_content']) ? $_POST['topic_content'] : '';
        $type      = isset($_POST['topic_type'])    ? $_POST['topic_type']    : 'text';
        $parent_id = isset($_POST['parent_id'])     ? $_POST['parent_id']     : 0;
        if (trim($title) == '') {
            $error = "El título del tema es obligatorio.";
        } else {
            $stmt = $db->prepare("INSERT INTO topics (project_id, parent_id, title, content, type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$project_id, $parent_id, $title, $content, $type]);
            // After creation, redirect back
            header("Location: ?action=edit_project&id=" . $project_id);
            exit;
        }
    }
    // >>> END NEW OR MODIFIED CODE <<<

    // Obtener la lista de tópicos
    $stmt = $db->prepare("SELECT * FROM topics WHERE project_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$project_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topicsTree = buildTree($topics);

    // Comprobar si hay un tema seleccionado
    $selected_topic = null;
    if (isset($_GET['topic_id'])) {
        $selected_topic_id = $_GET['topic_id'];
        $stmt = $db->prepare("SELECT * FROM topics WHERE id = ? AND project_id = ?");
        $stmt->execute([$selected_topic_id, $project_id]);
        $selected_topic = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Render
    renderHeader("Editar Proyecto");
    echo "<h2>Editar Proyecto: " . htmlspecialchars($project['title']) . "</h2>";
    echo "<p><a href='?action=panel'>Volver al Panel</a></p>";

    echo "<div class='two-pane'>";

      // ================================
      // Panel izquierdo (el árbol)
      // ================================
      echo "<div class='pane-left'>";
        echo "<h3>Estructura del Proyecto</h3>";
        if (!empty($topicsTree)) {
            renderTopicNav($topicsTree, $project_id);
        } else {
            echo "<p>No hay temas creados.</p>";
        }

        // >>> NEW OR MODIFIED CODE <<<
        // Button to show "Crear Nuevo Tema/Recurso" form in the right pane
        // We'll pass a GET parameter 'show_new_topic=1' to display that form
        echo "<p style='margin-top:20px;'>
                <a class='button'
                   href='?action=edit_project&id=" . $project_id . "&show_new_topic=1'>
                   Añadir Nuevo Tema/Recurso
                </a>
              </p>";
        // >>> END NEW OR MODIFIED CODE <<<
      echo "</div>";

      // =================================
      // Panel derecho (contenido)
      // =================================
      echo "<div class='pane-right'>";

        // Si hay error, lo mostramos
        if ($error) {
            echo "<p class='error'>" . $error . "</p>";
        }

        // >>> NEW OR MODIFIED CODE <<<
        // 1) If user clicked "Añadir Nuevo Tema/Recurso" -> show creation form
        if (isset($_GET['show_new_topic'])) {
            echo "<h3>Añadir Nuevo Tema/Recurso</h3>";
            echo "<form method='post' action='?action=edit_project&id=" . $project_id . "'>";
            echo "<label>Título:</label>";
            echo "<input type='text' name='topic_title' required /><br/>";
            echo "<label>Tipo:</label>";
            echo "<select name='topic_type'>
                    <option value='text'>Texto</option>
                    <option value='task'>Tarea</option>
                    <option value='interactive'>Actividad Interactiva</option>
                  </select><br/>";
            echo "<label>Contenido:</label>";
            echo "<textarea name='topic_content' class='jocarsa-lightslateblue'></textarea><br/>";
            echo "<label>Padre:</label>";
            echo "<select name='parent_id'>
                    <option value='0'>Ninguno</option>";
            if (!empty($topicsTree)) {
                renderParentOptions($topicsTree);
            }
            echo "</select><br/>";
            echo "<input type='submit' name='new_topic' value='Crear Tema' />";
            echo "</form>";

        // 2) If user clicked “Edit” for an existing topic
        } elseif (isset($_GET['edit_topic']) && $selected_topic) {
            echo "<h3>Editar Tema/Recurso</h3>";
            echo "<form method='post' action='?action=edit_project&id=" . $project_id . "&topic_id=" . $selected_topic['id'] . "'>";
            echo "<input type='hidden' name='topic_id' value='" . $selected_topic['id'] . "' />";
            echo "<label>Título:</label>";
            echo "<input type='text' name='topic_title' required value='"
                 . htmlspecialchars($selected_topic['title']) . "'/><br/>";
            echo "<label>Tipo:</label>";
            echo "<select name='topic_type'>";
            $types = ['text'=>'Texto','task'=>'Tarea','interactive'=>'Actividad Interactiva'];
            foreach ($types as $val => $label) {
                $selected = ($selected_topic['type'] == $val) ? "selected" : "";
                echo "<option value='$val' $selected>$label</option>";
            }
            echo "</select><br/>";
            echo "<label>Contenido:</label>";
            echo "<textarea name='topic_content' class='jocarsa-lightslateblue'>"
                 . htmlspecialchars($selected_topic['content']) . "</textarea><br/>";
            echo "<label>Padre:</label>";
            echo "<select name='parent_id'>
                    <option value='0'>Ninguno</option>";
            if (!empty($topicsTree)) {
                renderParentOptions($topicsTree, 0, $selected_topic['id']);
            }
            echo "</select><br/>";
            echo "<input type='submit' name='update_topic' value='Guardar Cambios' />";
            echo "</form>";

        // 3) Otherwise, show the selected topic details if any
        } elseif ($selected_topic) {
            echo "<h3>Contenido del Tema</h3>";
            echo "<p><strong>Título:</strong> " . htmlspecialchars($selected_topic['title']) . "</p>";
            echo "<p><strong>Tipo:</strong> " . htmlspecialchars($selected_topic['type']) . "</p>";
            echo "<p><strong>Contenido:</strong><br/>" . nl2br($selected_topic['content']) . "</p>";
            echo "<p>
                    <a class='button'
                       href='?action=edit_project&id=" . $project_id . "&topic_id=" . $selected_topic['id'] . "&edit_topic=1'>
                       Editar Este Tema
                    </a>
                  </p>";
            echo "<p>
                    <a class='delete-link'
                       href='?action=delete_topic&id=" . $selected_topic['id']
                       . "&project_id=" . $project_id
                       . "' onclick='return confirm(\"¿Está seguro de eliminar este tema?\")'>
                        Eliminar Tema
                    </a>
                  </p>";
        } else {
            // If no topic is selected and no new/edit forms are shown
            echo "<p>Selecciona un tema en el panel de la izquierda para ver o editar su contenido,
                  o haz clic en <strong>Añadir Nuevo Tema/Recurso</strong> para crear uno nuevo.</p>";
        }
        // >>> END NEW OR MODIFIED CODE <<<

      echo "</div>"; // pane-right
    echo "</div>";   // two-pane

    // For your custom CSS/JS
    echo '
    	<link rel="stylesheet" href="https://jocarsa.github.io/jocarsa-lightslateblue/jocarsa%20%7C%20lightslateblue.css">
<script src="https://jocarsa.github.io/jocarsa-lightslateblue/jocarsa%20%7C%20lightslateblue.js"></script>
    ';
    renderFooter();
    exit;
?>
