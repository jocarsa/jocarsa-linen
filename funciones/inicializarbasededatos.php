<?php

	function initDB() {
    $db = getDB();

    // Table: users
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )");

    // Insert default user if not exist
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['jocarsa']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute(['jocarsa', 'jocarsa']);
    }

    // Table: projects
    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // Table: topics
    $db->exec("CREATE TABLE IF NOT EXISTS topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        parent_id INTEGER DEFAULT 0,
        title TEXT NOT NULL,
        content TEXT,
        type TEXT DEFAULT 'text',
        sort_order INTEGER DEFAULT 0, -- New column for sorting
        FOREIGN KEY(project_id) REFERENCES projects(id)
    )");

    // Table: user_configurations
    $db->exec("CREATE TABLE IF NOT EXISTS user_configurations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        config_key TEXT NOT NULL,
        config_value TEXT NOT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // Insert default configuration for the default user
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_configurations WHERE user_id = ? AND config_key = ?");
    $stmt->execute([1, 'color_corporativo']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO user_configurations (user_id, config_key, config_value) VALUES (?, ?, ?)");
        $stmt->execute([1, 'color_corporativo', '#da291c']);
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM user_configurations WHERE user_id = ? AND config_key = ?");
    $stmt->execute([1, 'familia_de_fuentes']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO user_configurations (user_id, config_key, config_value) VALUES (?, ?, ?)");
        $stmt->execute([1, 'familia_de_fuentes', 'sans-serif']);
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM user_configurations WHERE user_id = ? AND config_key = ?");
    $stmt->execute([1, 'color']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO user_configurations (user_id, config_key, config_value) VALUES (?, ?, ?)");
        $stmt->execute([1, 'color', 'black']);
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM user_configurations WHERE user_id = ? AND config_key = ?");
    $stmt->execute([1, 'tamaño_de_fuente']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO user_configurations (user_id, config_key, config_value) VALUES (?, ?, ?)");
        $stmt->execute([1, 'tamaño_de_fuente', '12px']);
    }
}

?>
