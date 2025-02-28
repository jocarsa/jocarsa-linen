<?php

	function getUserConfig($user_id, $config_key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT config_value FROM user_configurations WHERE user_id = ? AND config_key = ?");
    $stmt->execute([$user_id, $config_key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['config_value'] : null;
}	
	
?>
