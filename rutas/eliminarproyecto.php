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
    // Elimina tópicos
    $stmt = $db->prepare("DELETE FROM topics WHERE project_id = ?");
    $stmt->execute([$project_id]);
    // Elimina proyecto
    $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    header("Location: ?action=panel");
    exit;

?>
