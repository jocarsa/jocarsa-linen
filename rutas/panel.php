<?php
	$db = getDB();
    $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    renderHeader("Panel de Administración");
    echo "<div class='navbar'>
            <a href='?action=logout'>Salir</a> |
            <a href='?action=create_project'>Crear Proyecto</a> |
            <a href='?action=configuration'>Configuración</a>
          </div>";
    echo "<h2>Panel de Administración</h2>";
    echo "<h3>Proyectos existentes</h3>";
    echo "<table>
            <tr>
              <th>ID</th>
              <th>Título</th>
              <th>Descripción</th>
              <th>Acciones</th>
            </tr>";
    foreach ($projects as $proj) {
        echo "<tr>
                <td>" . $proj['id'] . "</td>
                <td>" . htmlspecialchars($proj['title']) . "</td>
                <td>" . htmlspecialchars($proj['description']) . "</td>
                <td>
                  <a href='?action=edit_project&id=" . $proj['id'] . "'>Editar</a> |
                  <a href='?action=export_scorm&id=" . $proj['id'] . "'>Exportar SCORM</a> |
                  <a href='?action=presentation&id=" . $proj['id'] . "' target='_blank'>Presentación</a> |
                  <a href='?action=duplicate_project&id=" . $proj['id'] . "'>Duplicar</a> |
                  <a class='delete-link'
                     href='?action=delete_project&id=" . $proj['id'] . "'
                     onclick='return confirm(\"¿Está seguro de eliminar este proyecto?\")'>Eliminar</a>
                </td>
              </tr>";
    }
    echo "</table>";
    renderFooter();
    exit;
?>
