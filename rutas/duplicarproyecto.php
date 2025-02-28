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

    // Duplicate project
    $stmt = $db->prepare("INSERT INTO projects (user_id, title, description) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $project['title'] . " (Copia)", $project['description']]);
    $new_project_id = $db->lastInsertId();

    // Initialize the topic mapping
    $topicMapping = [];

    // Recursive function to duplicate topics
    function duplicateTopics($db, $project_id, $new_project_id, &$topicMapping, $parent_id = 0) {
        $stmt = $db->prepare("SELECT * FROM topics WHERE project_id = ? AND parent_id = ?");
        $stmt->execute([$project_id, $parent_id]);
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($topics as $topic) {
            $stmt = $db->prepare("INSERT INTO topics (project_id, parent_id, title, content, type, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$new_project_id, $parent_id, $topic['title'], $topic['content'], $topic['type'], $topic['sort_order']]);
            $new_topic_id = $db->lastInsertId();
            // Map the old topic ID to the new topic ID
            $topicMapping[$topic['id']] = $new_topic_id;
            // Recursively duplicate child topics
            duplicateTopics($db, $project_id, $new_project_id, $topicMapping, $topic['id']);
        }
    }

    // Duplicate topics recursively
    duplicateTopics($db, $project_id, $new_project_id, $topicMapping);

    // Update the parent_id of the duplicated topics
    foreach ($topicMapping as $old_topic_id => $new_topic_id) {
        $stmt = $db->prepare("UPDATE topics SET parent_id = ? WHERE id = ?");
        $stmt->execute([$topicMapping[$old_topic_id], $new_topic_id]);
    }

    header("Location: ?action=panel");
    exit;
?>
