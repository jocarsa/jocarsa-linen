<?php
$topic_id   = isset($_GET['id']) ? $_GET['id'] : '';
    $project_id = isset($_GET['project_id']) ? $_GET['project_id'] : '';
    if (!$topic_id || !$project_id) {
        header("Location: ?action=edit_project&id=" . $project_id);
        exit;
    }
    $db = getDB();
    deleteTopicRecursive($db, $topic_id);
    header("Location: ?action=edit_project&id=" . $project_id);
    exit;
?>
