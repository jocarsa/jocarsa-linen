<?php
	$db = getDB();
    $project_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$project_id) {
        echo "Proyecto no especificado.";
        exit;
    }
    // Verificar que el proyecto exista para este usuario
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        echo "Proyecto no encontrado o no autorizado.";
        exit;
    }

    // Obtener todos los temas
    $stmt = $db->prepare("SELECT * FROM topics WHERE project_id = ? ORDER BY id ASC");
    $stmt->execute([$project_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topicsTree = buildTree($topics);

    // Preparar array de "vistos" en sesión
    if (!isset($_SESSION['presentation_visited'])) {
        $_SESSION['presentation_visited'] = [];
    }
    if (!isset($_SESSION['presentation_visited'][$project_id])) {
        $_SESSION['presentation_visited'][$project_id] = [];
    }

    // Si el usuario selecciona un topic
    $selected_topic_id = isset($_GET['topic_id']) ? $_GET['topic_id'] : null;
    $selected_topic = null;
    if ($selected_topic_id) {
        foreach ($topics as $t) {
            if ($t['id'] == $selected_topic_id) {
                $selected_topic = $t;
                // Marcarlo como visto
                if (!in_array($t['id'], $_SESSION['presentation_visited'][$project_id])) {
                    $_SESSION['presentation_visited'][$project_id][] = $t['id'];
                }
                break;
            }
        }
    }

    // Renderizado de la navegación
    function renderPresentationNav($tree, $project_id, $visited, $level = 0) {
        foreach ($tree as $node) {
            $margin = 15 * $level;
            $title = htmlspecialchars($node['title']);
            $visitedMark = in_array($node['id'], $visited) ? " (visto)" : "";
            echo "<div style='margin-left:" . $margin . "px;'>";
            echo "<a href='?action=presentation&id=" . $project_id
                 . "&topic_id=" . $node['id'] . "'>"
                 . $title . "</a> <span class='visited-mark'>" . $visitedMark . "</span>";
            echo "</div>";
            if (!empty($node['children'])) {
                renderPresentationNav($node['children'], $project_id, $visited, $level+1);
            }
        }
    }

    // Layout
    renderHeader("Presentación: " . htmlspecialchars($project['title']));
    echo "<h2>Presentación del Proyecto: " . htmlspecialchars($project['title']) . "</h2>";

    echo "<div class='two-pane'>";
      // Left navigation
      echo "<div class='pane-left'>";
      echo "<h3>Navegación</h3>";
      if (!empty($topicsTree)) {
          renderPresentationNav($topicsTree, $project_id, $_SESSION['presentation_visited'][$project_id]);
      } else {
          echo "<p>No hay temas disponibles.</p>";
      }
      echo "</div>";
      // Right content
      echo "<div class='pane-right'>";
      if ($selected_topic) {
          echo "<h3>Tema Seleccionado</h3>";
          echo "<p><strong>" . htmlspecialchars($selected_topic['title']) . "</strong></p>";
          echo "<p>" . nl2br($selected_topic['content']) . "</p>";
      } else {
          echo "<p>Seleccione un tema en la izquierda.</p>";
      }
      echo "</div>";
    echo "</div>";

    renderFooter();
    exit;
?>	
