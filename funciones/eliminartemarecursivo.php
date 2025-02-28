<?php

	function deleteTopicRecursive($db, $topic_id) {
    // Primero eliminamos hijos (recursivo)
    $stmt = $db->prepare("SELECT id FROM topics WHERE parent_id = ?");
    $stmt->execute([$topic_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($children as $child) {
        deleteTopicRecursive($db, $child['id']);
    }
    // Luego eliminamos el propio tema
    $stmt = $db->prepare("DELETE FROM topics WHERE id = ?");
    $stmt->execute([$topic_id]);
}

?>
