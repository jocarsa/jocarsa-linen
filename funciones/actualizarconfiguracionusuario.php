<?php
function updateUserConfig($user_id, $config_key, $config_value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO user_configurations (user_id, config_key, config_value) VALUES (?, ?, ?)
                          ON CONFLICT(user_id, config_key) DO UPDATE SET config_value = ?");
    $stmt->execute([$user_id, $config_key, $config_value, $config_value]);
}
?>
