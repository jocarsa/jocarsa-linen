<?php
	$project_id = isset($_POST['project_id']) ? $_POST['project_id'] : '';
    $order = isset($_POST['order']) ? $_POST['order'] : [];
    if (!$project_id || !isset($_SESSION['user_id']) || empty($order)) {
        header("Location: ?action=panel");
        exit;
    }
    $db = getDB();
    foreach ($order as $index => $topic_id) {
        $stmt = $db->prepare("UPDATE topics SET sort_order = ? WHERE id = ? AND project_id = ?");
        $stmt->execute([$index, $topic_id, $project_id]);
    }
    echo json_encode(['status' => 'success']);
    exit;
?>
