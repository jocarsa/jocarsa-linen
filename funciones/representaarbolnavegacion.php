<?php

	// Renderiza el árbol de navegación en "editar proyecto"
function renderTopicNav($tree, $project_id, $level = 0) {
    foreach ($tree as $node) {
        echo "<div style='margin-left:" . ($level * 15) . "px;'>";
        echo "<a href='?action=edit_project&id=" . $project_id . "&topic_id=" . $node['id'] . "'>"
             . htmlspecialchars($node['title']) . "</a>";
        echo " <a class='delete-link' href='?action=delete_topic&id=" . $node['id']
             . "&project_id=" . $project_id
             . "' onclick='return confirm(\"¿Está seguro de eliminar este tema?\")'>[Eliminar]</a>";
        echo "</div>";
        if (!empty($node['children'])) {
            renderTopicNav($node['children'], $project_id, $level + 1);
        }
    }
}

?>
