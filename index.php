<?php
session_start();

/* ======================================================
   1. Conexión a la base de datos y creación de tablas
   ====================================================== */
function getDB() {
    $db = new PDO('sqlite:../databases/linen.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON;');
    return $db;
}

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

    // New Table: topic_order
    $db->exec("CREATE TABLE IF NOT EXISTS topic_order (
        topic_id INTEGER PRIMARY KEY,
        order_value INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY(topic_id) REFERENCES topics(id) ON DELETE CASCADE
    )");
}
initDB();

function getUserConfig($user_id, $config_key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT config_value FROM user_configurations WHERE user_id = ? AND config_key = ?");
    $stmt->execute([$user_id, $config_key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['config_value'] : null;
}

function updateUserConfig($user_id, $config_key, $config_value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO user_configurations (user_id, config_key, config_value) VALUES (?, ?, ?)
                          ON CONFLICT(user_id, config_key) DO UPDATE SET config_value = ?");
    $stmt->execute([$user_id, $config_key, $config_value, $config_value]);
}

/* ======================================================
   Función para eliminar recursivamente un tema
   ====================================================== */
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

/* ======================================================
   Funciones para el árbol de temas
   ====================================================== */
function buildTree(array $elements, $parentId = 0) {
    $branch = [];
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = buildTree($elements, $element['id']);
            $element['children'] = $children;
            $branch[] = $element;
        }
    }
    return $branch;
}

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

// Actualizada para que el <select> de "padre" admita un valor preseleccionado y evite seleccionar el propio tema
function renderParentOptions($tree, $level = 0, $selectedParent = null, $currentTopicId = null) {
    foreach ($tree as $node) {
        // Evitar que un tema se convierta en su propio padre
        if ($currentTopicId !== null && $node['id'] == $currentTopicId) {
            continue;
        }
        $selected = ($selectedParent !== null && $node['id'] == $selectedParent) ? "selected" : "";
        echo "<option value='" . $node['id'] . "' $selected>"
             . str_repeat("--", $level) . " " 
             . htmlspecialchars($node['title'])
             . "</option>";
        if (!empty($node['children'])) {
            renderParentOptions($node['children'], $level + 1, $selectedParent, $currentTopicId);
        }
    }
}

/* ======================================================
   3. Header y Footer comunes
   ====================================================== */
function renderHeader($pageTitle) {
    echo "<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <title>" . htmlspecialchars($pageTitle) . " - jocarsa | linen</title>
  <link rel='stylesheet' href='style.css'>
  <link rel='icon' type='image/svg+xml' href='linen.png' />
</head>
<body>
<header><img src='linen.png'>jocarsa | linen</header>
<div class='container'>
";
}

function renderFooter() {
    echo "</div>
    <script src='https://ghostwhite.jocarsa.com/analytics.js?user=linen.jocarsa.com'></script>
</body>
</html>";
}

/* ======================================================
   4. Enrutamiento
   ====================================================== */
$action = isset($_GET['action']) ? $_GET['action'] : '';
if ($action == '') {
    $action = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ? 'panel' : 'login';
}

/* =============== LOGIN =============== */
if ($action == 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            header("Location: ?action=panel");
            exit;
        } else {
            $error = "Credenciales incorrectas.";
        }
    }
    renderHeader("Login");
    echo "<h2>Login</h2>";
    if (isset($error)) {
        echo "<p class='error'>" . $error . "</p>";
    }
    echo "<form method='post' action='?action=login'>
          <label>Usuario:</label>
          <input type='text' name='username' required /><br/>
          <label>Contraseña:</label>
          <input type='password' name='password' required /><br/>
          <input type='submit' value='Entrar' />
          </form>";
    renderFooter();
    exit;
}

/* =============== LOGOUT =============== */
if ($action == 'logout') {
    session_destroy();
    header("Location: ?action=login");
    exit;
}

/* =============== DELETE PROJECT =============== */
if ($action == 'delete_project') {
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
}

/* =============== DELETE TOPIC =============== */
if ($action == 'delete_topic') {
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
}

/* =============== DUPLICATE PROJECT =============== */
if ($action == 'duplicate_project') {
    $db = getDB();
    $project_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$project_id) {
        header("Location: ?action=panel");
        exit;
    }
    // Verificar propiedad
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        header("Location: ?action=panel");
        exit;
    }
    // Duplicar el proyecto (agregando " - copia" al título)
    $newTitle = $project['title'] . " - copia";
    $stmt = $db->prepare("INSERT INTO projects (user_id, title, description) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $newTitle, $project['description']]);
    $newProjectId = $db->lastInsertId();
    
    // Duplicar topics de forma que se conserve la estructura (dos pasadas)
    $topicMapping = array(); // mapea el id antiguo => nuevo id
    $originalParents = array(); // almacena el id de padre original por cada nuevo tema
    $stmtTopics = $db->prepare("SELECT * FROM topics WHERE project_id = ? ORDER BY id ASC");
    $stmtTopics->execute([$project_id]);
    $topics = $stmtTopics->fetchAll(PDO::FETCH_ASSOC);
    foreach ($topics as $topic) {
        // Insertar cada tema con project_id del nuevo proyecto y temporalmente parent_id = 0
        $stmtInsert = $db->prepare("INSERT INTO topics (project_id, parent_id, title, content, type) VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->execute([$newProjectId, 0, $topic['title'], $topic['content'], $topic['type']]);
        $newTopicId = $db->lastInsertId();
        $topicMapping[$topic['id']] = $newTopicId;
        $originalParents[$newTopicId] = $topic['parent_id'];
    }
    // Actualizar el parent_id de cada tema duplicado
    foreach ($topicMapping as $oldTopicId => $newTopicId) {
        $oldParentId = $originalParents[$newTopicId];
        if ($oldParentId != 0 && isset($topicMapping[$oldParentId])) {
            $newParentId = $topicMapping[$oldParentId];
            $stmtUpdate = $db->prepare("UPDATE topics SET parent_id = ? WHERE id = ?");
            $stmtUpdate->execute([$newParentId, $newTopicId]);
        }
    }
    
    // Duplicar la información de topic_order
    $stmtOrderSelect = $db->prepare("SELECT order_value FROM topic_order WHERE topic_id = ?");
    $stmtOrderInsert = $db->prepare("INSERT INTO topic_order (topic_id, order_value) VALUES (?, ?)");
    foreach ($topicMapping as $oldTopicId => $newTopicId) {
        $stmtOrderSelect->execute([$oldTopicId]);
        $orderData = $stmtOrderSelect->fetch(PDO::FETCH_ASSOC);
        if ($orderData) {
            $stmtOrderInsert->execute([$newTopicId, $orderData['order_value']]);
        }
    }
    
    // Redirigir al nuevo proyecto duplicado (por ejemplo, al modo de edición)
    header("Location: ?action=edit_project&id=" . $newProjectId);
    exit;
}

/* =============== EDIT PROJECT INFO (Title & Description) =============== */
if ($action == 'edit_project_info') {
    $db = getDB();
    $project_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$project_id) {
        header("Location: ?action=panel");
        exit;
    }
    // Verificar propiedad
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        renderHeader("Error");
        echo "<p class='error'>Proyecto no encontrado o no autorizado.</p>";
        renderFooter();
        exit;
    }
    $error = "";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = isset($_POST['title']) ? $_POST['title'] : '';
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        if (trim($title) == '') {
            $error = "El título es obligatorio.";
        } else {
            $stmt = $db->prepare("UPDATE projects SET title = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $description, $project_id]);
            header("Location: ?action=panel");
            exit;
        }
    }
    renderHeader("Editar Información del Proyecto");
    echo "<h2>Editar Información del Proyecto</h2>";
    if ($error) {
        echo "<p class='error'>" . $error . "</p>";
    }
    echo "<form method='post' action='?action=edit_project_info&id=" . $project_id . "'>";
    echo "<label>Título:</label>";
    echo "<input type='text' name='title' value='" . htmlspecialchars($project['title']) . "' required /><br/>";
    echo "<label>Descripción:</label>";
    echo "<textarea name='description'>" . htmlspecialchars($project['description']) . "</textarea><br/>";
    echo "<input type='submit' value='Guardar Cambios' />";
    echo "</form>";
    echo "<p><a href='?action=panel'>Volver al Panel</a></p>";
    renderFooter();
    exit;
}

/* =============== PANEL (ADMIN PANEL estilo WordPress) =============== */
if ($action == 'panel') {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    renderHeader("Panel de Administración");
    // Two-pane layout: Left navigation and Right main pane.
    echo "<div class='two-pane'>";
      // Left Navigation
      echo "<div class='pane-left'>";
        echo "<nav>";
          echo "<ul>";
            echo "<li><a href='?action=logout'>Salir</a></li>";
            echo "<li><a href='?action=create_project'>Crear Proyecto</a></li>";
            echo "<li><a href='?action=configuration'>Configuración</a></li>";
          echo "</ul>";
        echo "</nav>";
      echo "</div>"; // pane-left

      // Right Main Pane: Table of projects with actions.
      echo "<div class='pane-right'>";
        echo "<h2>Panel de Administración</h2>";
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
                      <a href='?action=edit_project&id=" . $proj['id'] . "'>Contenido</a>
                      <a href='?action=edit_project_info&id=" . $proj['id'] . "'>Info</a>
                      <a href='?action=duplicate_project&id=" . $proj['id'] . "'>Duplicar</a>
                      <a href='?action=export_scorm&id=" . $proj['id'] . "'>SCORM</a>
                      <a href='?action=presentation&id=" . $proj['id'] . "' target='_blank'>Presentación</a>
                      <a class='delete-link' href='?action=delete_project&id=" . $proj['id'] . "' onclick='return confirm(\"¿Está seguro de eliminar este proyecto?\")'>Eliminar</a>
                    </td>
                  </tr>";
        }
        echo "</table>";
      echo "</div>"; // pane-right
    echo "</div>"; // two-pane
    renderFooter();
    exit;
}

/* =============== CREATE PROJECT =============== */
if ($action == 'create_project') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = isset($_POST['title']) ? $_POST['title'] : '';
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        if (trim($title) == '') {
            $error = "El título es obligatorio.";
        } else {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO projects (user_id, title, description) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $description]);
            header("Location: ?action=panel");
            exit;
        }
    }
    renderHeader("Crear Proyecto");
    echo "<h2>Crear Nuevo Proyecto</h2>";
    if ($error) {
        echo "<p class='error'>" . $error . "</p>";
    }
    echo "<form method='post' action='?action=create_project'>
          <label>Título:</label>
          <input type='text' name='title' required /><br/>
          <label>Descripción:</label>
          <textarea name='description'></textarea><br/>
          <input type='submit' value='Crear Proyecto' />
          </form>";
    echo "<p><a href='?action=panel'>Volver al Panel</a></p>";
    renderFooter();
    exit;
}

/* =============== EDIT PROJECT (two-panel layout for topics) =============== */
if ($action == 'edit_project') {
    $db = getDB();
    $project_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$project_id) {
        header("Location: ?action=panel");
        exit;
    }
    // Verificar propiedad
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        renderHeader("Error");
        echo "<p class='error'>Proyecto no encontrado o no autorizado.</p>";
        renderFooter();
        exit;
    }

    // >>> NEW OR MODIFIED CODE <<<
    // Handling the creation AND editing of topics in one place:
    $error = '';

    // 1) Update existing topic (if "edit_topic" param and form is submitted)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_topic'])) {
        $topic_id  = isset($_POST['topic_id']) ? $_POST['topic_id'] : '';
        $title     = isset($_POST['topic_title']) ? $_POST['topic_title'] : '';
        $content   = isset($_POST['topic_content']) ? $_POST['topic_content'] : '';
        $type      = isset($_POST['topic_type']) ? $_POST['topic_type'] : 'text';
        $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : 0;
        $order     = isset($_POST['order']) ? (int)$_POST['order'] : 0;

        if (trim($title) == '') {
            $error = "El título del tema es obligatorio.";
        } else {
            $stmt = $db->prepare("UPDATE topics
                                  SET title = ?, content = ?, type = ?, parent_id = ?
                                  WHERE id = ? AND project_id = ?");
            $stmt->execute([$title, $content, $type, $parent_id, $topic_id, $project_id]);
            // Update order value in topic_order table
            $stmtOrder = $db->prepare("INSERT INTO topic_order (topic_id, order_value) VALUES (?, ?)
                                       ON CONFLICT(topic_id) DO UPDATE SET order_value = ?");
            $stmtOrder->execute([$topic_id, $order, $order]);
            header("Location: ?action=edit_project&id=" . $project_id . "&topic_id=" . $topic_id);
            exit;
        }
    }

    // 2) Create new topic (if "new_topic" form is submitted)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_topic'])) {
        $title     = isset($_POST['topic_title']) ? $_POST['topic_title'] : '';
        $content   = isset($_POST['topic_content']) ? $_POST['topic_content'] : '';
        $type      = isset($_POST['topic_type']) ? $_POST['topic_type'] : 'text';
        $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : 0;
        $order     = isset($_POST['order']) ? (int)$_POST['order'] : 0;
        if (trim($title) == '') {
            $error = "El título del tema es obligatorio.";
        } else {
            $stmt = $db->prepare("INSERT INTO topics (project_id, parent_id, title, content, type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$project_id, $parent_id, $title, $content, $type]);
            $topic_id = $db->lastInsertId();
            // Save the order value
            $stmtOrder = $db->prepare("INSERT INTO topic_order (topic_id, order_value) VALUES (?, ?)");
            $stmtOrder->execute([$topic_id, $order]);
            header("Location: ?action=edit_project&id=" . $project_id);
            exit;
        }
    }
    // >>> END NEW OR MODIFIED CODE <<<

    // Obtener la lista de tópicos, ordered by order_value then id
    $stmt = $db->prepare("SELECT topics.*, IFNULL(topic_order.order_value, 0) as order_value
                          FROM topics
                          LEFT JOIN topic_order ON topics.id = topic_order.topic_id
                          WHERE project_id = ?
                          ORDER BY order_value ASC, topics.id ASC");
    $stmt->execute([$project_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topicsTree = buildTree($topics);

    // Comprobar si hay un tema seleccionado
    $selected_topic = null;
    if (isset($_GET['topic_id'])) {
        $selected_topic_id = $_GET['topic_id'];
        $stmt = $db->prepare("SELECT * FROM topics WHERE id = ? AND project_id = ?");
        $stmt->execute([$selected_topic_id, $project_id]);
        $selected_topic = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Render
    renderHeader("Editar Proyecto");
    echo "<h2>Editar Proyecto: " . htmlspecialchars($project['title']) . "</h2>";
    echo "<p><a href='?action=panel'>Volver al Panel</a></p>";

    echo "<div class='two-pane'>";

      // ================================
      // Panel izquierdo (el árbol)
      // ================================
      echo "<div class='pane-left'>";
        echo "<h3>Estructura del Proyecto</h3>";
        if (!empty($topicsTree)) {
            renderTopicNav($topicsTree, $project_id);
        } else {
            echo "<p>No hay temas creados.</p>";
        }
        // Botón para mostrar el formulario de "Crear Nuevo Tema/Recurso"
        echo "<p style='margin-top:20px;'>
                <a class='button'
                   href='?action=edit_project&id=" . $project_id . "&show_new_topic=1'>
                   Añadir Nuevo Tema/Recurso
                </a>
              </p>";
      echo "</div>";

      // =================================
      // Panel derecho (contenido)
      // =================================
      echo "<div class='pane-right'>";
        if ($error) {
            echo "<p class='error'>" . $error . "</p>";
        }
        // 1) Si se muestra el formulario para crear un nuevo tema
        if (isset($_GET['show_new_topic'])) {
            echo "<h3>Añadir Nuevo Tema/Recurso</h3>";
            echo "<form method='post' action='?action=edit_project&id=" . $project_id . "'>";
            echo "<label>Título:</label>";
            echo "<input type='text' name='topic_title' required /><br/>";
            echo "<label>Tipo:</label>";
            echo "<select name='topic_type'>
                    <option value='text'>Texto</option>
                    <option value='task'>Tarea</option>
                    <option value='interactive'>Actividad Interactiva</option>
                  </select><br/>";
            echo "<label>Contenido:</label>";
            echo "<textarea name='topic_content' class='jocarsa-lightslateblue'></textarea><br/>";
            echo "<label>Padre:</label>";
            echo "<select name='parent_id'>
                    <option value='0'>Ninguno</option>";
            if (!empty($topicsTree)) {
                renderParentOptions($topicsTree);
            }
            echo "</select><br/>";
            echo "<label>Orden:</label>";
            echo "<input type='number' name='order' value='0' /><br/>";
            echo "<input type='submit' name='new_topic' value='Crear Tema' />";
            echo "</form>";
        // 2) Si se edita un tema existente
        } elseif (isset($_GET['edit_topic']) && $selected_topic) {
            // Obtener el valor actual del orden
            $stmtOrder = $db->prepare("SELECT order_value FROM topic_order WHERE topic_id = ?");
            $stmtOrder->execute([$selected_topic['id']]);
            $orderData = $stmtOrder->fetch(PDO::FETCH_ASSOC);
            $orderValue = $orderData ? $orderData['order_value'] : 0;
            echo "<h3>Editar Tema/Recurso</h3>";
            echo "<form method='post' action='?action=edit_project&id=" . $project_id . "&topic_id=" . $selected_topic['id'] . "'>";
            echo "<input type='hidden' name='topic_id' value='" . $selected_topic['id'] . "' />";
            echo "<label>Título:</label>";
            echo "<input type='text' name='topic_title' required value='" . htmlspecialchars($selected_topic['title']) . "'/><br/>";
            echo "<label>Tipo:</label>";
            echo "<select name='topic_type'>";
            $types = ['text'=>'Texto','task'=>'Tarea','interactive'=>'Actividad Interactiva'];
            foreach ($types as $val => $label) {
                $sel = ($selected_topic['type'] == $val) ? "selected" : "";
                echo "<option value='$val' $sel>$label</option>";
            }
            echo "</select><br/>";
            echo "<label>Contenido:</label>";
            echo "<textarea name='topic_content' class='jocarsa-lightslateblue'>" . htmlspecialchars($selected_topic['content']) . "</textarea><br/>";
            echo "<label>Padre:</label>";
            echo "<select name='parent_id'>
                    <option value='0'>Ninguno</option>";
            if (!empty($topicsTree)) {
                renderParentOptions($topicsTree, 0, $selected_topic['parent_id'], $selected_topic['id']);
            }
            echo "</select><br/>";
            echo "<label>Orden:</label>";
            echo "<input type='number' name='order' value='" . $orderValue . "' /><br/>";
            echo "<input type='submit' name='update_topic' value='Guardar Cambios' />";
            echo "</form>";
        // 3) Si se ha seleccionado un tema para ver su contenido
        } elseif ($selected_topic) {
            echo "<h3>Contenido del Tema</h3>";
            echo "<p><strong>Título:</strong> " . htmlspecialchars($selected_topic['title']) . "</p>";
            echo "<p><strong>Tipo:</strong> " . htmlspecialchars($selected_topic['type']) . "</p>";
            echo "<p><strong>Contenido:</strong><br/>" . nl2br($selected_topic['content']) . "</p>";
            echo "<p>
                    <a class='button'
                       href='?action=edit_project&id=" . $project_id . "&topic_id=" . $selected_topic['id'] . "&edit_topic=1'>
                       Editar Este Tema
                    </a>
                  </p>";
            echo "<p>
                    <a class='delete-link'
                       href='?action=delete_topic&id=" . $selected_topic['id'] . "&project_id=" . $project_id . "'
                       onclick='return confirm(\"¿Está seguro de eliminar este tema?\")'>
                        Eliminar Tema
                    </a>
                  </p>";
        } else {
            echo "<p>Selecciona un tema en el panel de la izquierda para ver o editar su contenido,
                  o haz clic en <strong>Añadir Nuevo Tema/Recurso</strong> para crear uno nuevo.</p>";
        }
      echo "</div>"; // pane-right
    echo "</div>";   // two-pane

    echo '
    	<link rel="stylesheet" href="https://jocarsa.github.io/jocarsa-lightslateblue/jocarsa%20%7C%20lightslateblue.css">
    <script src="https://jocarsa.github.io/jocarsa-lightslateblue/jocarsa%20%7C%20lightslateblue.js"></script>
    ';
    renderFooter();
    exit;
}

/* ===================================================================
   PRESENTATION MODE (two horizontal panes, mark visited)
   =================================================================== */
if ($action == 'presentation') {
    $db = getDB();
    $project_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$project_id) {
        echo "Proyecto no especificado.";
        exit;
    }
    // Verificar que el proyecto exista para este usuario
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        echo "Proyecto no encontrado o no autorizado.";
        exit;
    }

    // Obtener todos los temas, ordered by order_value then id
    $stmt = $db->prepare("SELECT topics.*, IFNULL(topic_order.order_value, 0) as order_value
                          FROM topics
                          LEFT JOIN topic_order ON topics.id = topic_order.topic_id
                          WHERE project_id = ?
                          ORDER BY order_value ASC, topics.id ASC");
    $stmt->execute([$project_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topicsTree = buildTree($topics);

    // Preparar array de "vistos" en sesión
    if (!isset($_SESSION['presentation_visited'])) {
        $_SESSION['presentation_visited'] = [];
    }
    if (!isset($_SESSION['presentation_visited'][$project_id])) {
        $_SESSION['presentation_visited'][$project_id] = [];
    }

    // Si el usuario selecciona un topic
    $selected_topic_id = isset($_GET['topic_id']) ? $_GET['topic_id'] : null;
    $selected_topic = null;
    if ($selected_topic_id) {
        foreach ($topics as $t) {
            if ($t['id'] == $selected_topic_id) {
                $selected_topic = $t;
                // Marcarlo como visto
                if (!in_array($t['id'], $_SESSION['presentation_visited'][$project_id])) {
                    $_SESSION['presentation_visited'][$project_id][] = $t['id'];
                }
                break;
            }
        }
    }

    // Renderizado de la navegación
    function renderPresentationNav($tree, $project_id, $visited, $level = 0) {
        foreach ($tree as $node) {
            $margin = 15 * $level;
            $title = htmlspecialchars($node['title']);
            $visitedMark = in_array($node['id'], $visited) ? " (visto)" : "";
            echo "<div style='margin-left:" . $margin . "px;'>";
            echo "<a href='?action=presentation&id=" . $project_id
                 . "&topic_id=" . $node['id'] . "'>"
                 . $title . "</a> <span class='visited-mark'>" . $visitedMark . "</span>";
            echo "</div>";
            if (!empty($node['children'])) {
                renderPresentationNav($node['children'], $project_id, $visited, $level+1);
            }
        }
    }

    // Layout
    renderHeader("Presentación: " . htmlspecialchars($project['title']));
    echo "<h2>Presentación del Proyecto: " . htmlspecialchars($project['title']) . "</h2>";

    echo "<div class='two-pane'>";
      // Left navigation
      echo "<div class='pane-left'>";
      echo "<h3>Navegación</h3>";
      if (!empty($topicsTree)) {
          renderPresentationNav($topicsTree, $project_id, $_SESSION['presentation_visited'][$project_id]);
      } else {
          echo "<p>No hay temas disponibles.</p>";
      }
      echo "</div>";
      // Right content
      echo "<div class='pane-right'>";
      if ($selected_topic) {
          echo "<h3>Tema Seleccionado</h3>";
          echo "<p><strong>" . htmlspecialchars($selected_topic['title']) . "</strong></p>";
          echo "<p>" . nl2br($selected_topic['content']) . "</p>";
      } else {
          echo "<p>Seleccione un tema en la izquierda.</p>";
      }
      echo "</div>";
    echo "</div>";

    renderFooter();
    exit;
}

/* ======================================================
   Exportación SCORM
   ====================================================== */
if ($action == 'export_scorm') {
    $db = getDB();
    $project_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$project_id) {
        echo "ID de proyecto no especificado.";
        exit;
    }
    // Verificar propiedad
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        echo "Proyecto no encontrado o no tiene permiso para exportar.";
        exit;
    }

    // Obtener temas, ordered by order_value then id
    $stmt = $db->prepare("SELECT topics.*, IFNULL(topic_order.order_value, 0) as order_value
                          FROM topics
                          LEFT JOIN topic_order ON topics.id = topic_order.topic_id
                          WHERE project_id = ?
                          ORDER BY order_value ASC, topics.id ASC");
    $stmt->execute([$project_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topicsTree = buildTree($topics);

    // Generar <item> recursivamente
    function generateItemsXML($topics) {
        $itemsXML = "";
        foreach ($topics as $topic) {
            $identifier = "ITEM_" . $topic['id'];
            $resourceRef = "RES_" . $topic['id'];
            $itemsXML .= "<item identifier=\"" . $identifier . "\" identifierref=\"" . $resourceRef . "\">\n";
            $itemsXML .= "<title>" . htmlspecialchars($topic['title']) . "</title>\n";
            if (!empty($topic['children'])) {
                $itemsXML .= generateItemsXML($topic['children']);
            }
            $itemsXML .= "</item>\n";
        }
        return $itemsXML;
    }
    $itemsXML = generateItemsXML($topicsTree);

    // Generar <resource> para cada topic
    $resources = "";
    foreach ($topics as $topic) {
        $identifier = "RES_" . $topic['id'];
        $href       = "topic_" . $topic['id'] . ".html";
        $resources .= '<resource identifier="' . $identifier
                   . '" type="webcontent" adlcp:scormType="sco" href="' . $href . '">' . "\n";
        $resources .= '<file href="' . $href . '"/>' . "\n";
        $resources .= "</resource>\n";
    }

    // imsmanifest.xml
    $manifestXML = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<manifest identifier="MANIFEST_' . $project['id'] . '" version="1.2" '
        . 'xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" '
        . 'xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
        . 'xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd '
        . 'http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd">' . "\n"
        . '<organizations default="ORG1">' . "\n"
        . '<organization identifier="ORG1">' . "\n"
        . '<title>' . htmlspecialchars($project['title']) . '</title>' . "\n"
        . $itemsXML
        . '</organization>' . "\n"
        . '</organizations>' . "\n"
        . '<resources>' . "\n"
        . $resources
        . '</resources>' . "\n"
        . '</manifest>';

    // SCORM API JS (minimal)
    $scormApiJS =
"// Minimal SCORM 1.2 API Example\n"
. "var g_api = null;\n"
. "var g_isInitialized = false;\n\n"
. "function findAPI(win) {\n"
. "  var attempts = 0;\n"
. "  while ((win.API == null) && (win.parent != null) && (win.parent != win) && (attempts <= 10)) {\n"
. "    attempts++;\n"
. "    win = win.parent;\n"
. "  }\n"
. "  return win.API;\n"
. "}\n\n"
. "function getAPI() {\n"
. "  if (g_api == null) {\n"
. "    g_api = findAPI(window);\n"
. "  }\n"
. "  return g_api;\n"
. "}\n\n"
. "function scormInit() {\n"
. "  var api = getAPI();\n"
. "  if (api == null) return;\n"
. "  if (!g_isInitialized) {\n"
. "    var result = api.LMSInitialize(\"\");\n"
. "    g_isInitialized = (result.toString() == \"true\");\n"
. "  }\n"
. "}\n\n"
. "function scormFinish() {\n"
. "  var api = getAPI();\n"
. "  if (api == null) return;\n"
. "  if (g_isInitialized) {\n"
. "    api.LMSFinish(\"\");\n"
. "  }\n"
. "}\n\n"
. "function scormCommit() {\n"
. "  var api = getAPI();\n"
. "  if (api == null) return;\n"
. "  if (g_isInitialized) {\n"
. "    api.LMSCommit(\"\");\n"
. "  }\n"
. "}\n\n"
. "function scormSetValue(name, value) {\n"
. "  var api = getAPI();\n"
. "  if (api && g_isInitialized) {\n"
. "    api.LMSSetValue(name, value);\n"
. "  }\n"
. "}\n\n"
. "function scormGetValue(name) {\n"
. "  var api = getAPI();\n"
. "  if (api && g_isInitialized) {\n"
. "    return api.LMSGetValue(name);\n"
. "  }\n"
. "  return \"\";\n"
. "}\n\n"
. "function markTopicVisited(topicId) {\n"
. "  var visited = scormGetValue(\"cmi.suspend_data\") || \"\";\n"
. "  if (visited.indexOf(\",\" + topicId + \",\") === -1) {\n"
. "    visited += \",\" + topicId + \",\";\n"
. "    scormSetValue(\"cmi.suspend_data\", visited);\n"
. "    scormCommit();\n"
. "  }\n"
. "  // Set completion status and score\n"
. "  scormSetValue(\"cmi.core.lesson_status\", \"completed\");\n"
. "  scormSetValue(\"cmi.core.score.raw\", \"10\");\n"
. "  scormSetValue(\"cmi.core.score.min\", \"0\");\n"
. "  scormSetValue(\"cmi.core.score.max\", \"10\");\n"
. "  scormCommit();\n"
. "}\n";

    // Basic style
    $corporateColor = getUserConfig($_SESSION['user_id'], 'color_corporativo');
    $fontFamily = getUserConfig($_SESSION['user_id'], 'familia_de_fuentes');
    $textColor = getUserConfig($_SESSION['user_id'], 'color');
    $fontSize = getUserConfig($_SESSION['user_id'], 'tamaño_de_fuente');

    $styleCSS =
"@import url('https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap');\n"
. ":root {\n"
. "  --header-bg: " . $corporateColor . ";\n"
. "  --header-text: #ffffff;\n"
. "  --text-color: " . $textColor . ";\n"
. "  --font-family: " . $fontFamily . ";\n"
. "  --font-size: " . $fontSize . ";\n"
. "}\n"
. "body {\n"
. "  font-family: var(--font-family);\n"
. "  font-size: var(--font-size);\n"
. "  margin: 0; padding: 20px;\n"
. "  border-top: 15px solid var(--header-bg);\n"
. "}\n"
. "header {\n"
. "  background: var(--header-bg);\n"
. "  color: var(--header-text);\n"
. "  padding: 15px;\n"
. "}\n";

    // Crear ZIP
    $zip = new ZipArchive();
    $zipFilename = tempnam(sys_get_temp_dir(), 'scorm_') . '.zip';
    if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
        exit("No se pudo crear el archivo ZIP.\n");
    }

    // 1) imsmanifest.xml
    $zip->addFromString("imsmanifest.xml", $manifestXML);

    // 2) SCORM API JS
    $zip->addFromString("scorm_api.js", $scormApiJS);

    // 3) style.css
    $zip->addFromString("style.css", $styleCSS);

    // 4) topic_X.html for each topic
    foreach ($topics as $topic) {
        $titleSafe   = htmlspecialchars($topic['title']);
        $contentSafe = nl2br($topic['content']);
        $topicId     = (int)$topic['id'];

        $htmlContent = ""
            . "<html>\n"
            . "<head>\n"
            . "  <meta charset='UTF-8'>\n"
            . "  <title>" . $titleSafe . "</title>\n"
            . "  <link rel='stylesheet' href='style.css' />\n"
            . "  <script src='scorm_api.js'></script>\n"
            . "  <script>\n"
            . "    window.onload = function() {\n"
            . "      scormInit();\n"
            . "      markTopicVisited(" . $topicId . ");\n"
            . "    };\n"
            . "    window.onunload = function() {\n"
            . "      scormFinish();\n"
            . "    };\n"
            . "    window.onbeforeunload = function() {\n"
            . "      scormFinish();\n"
            . "    };\n"
            . "  </script>\n"
            . "</head>\n"
            . "<body>\n"
            . "  <header>" . $titleSafe . "</header>\n"
            . "  <div class='container'>\n"
            . "    <div>" . $contentSafe . "</div>\n"
            . "  </div>\n"
            
            . "</body>\n"
            . "</html>";

        $filename = "topic_" . $topicId . ".html";
        $zip->addFromString($filename, $htmlContent);
    }

    $zip->close();

    // Forzar descarga con nombre de archivo actualizado
    $timestamp = date("Y-m-d-H-i-s");
    $projectName = str_replace(' ', '_', $project['title']);
    $downloadFilename = $projectName . '_' . $timestamp . '.zip';

    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=' . $downloadFilename);
    header('Content-Length: ' . filesize($zipFilename));
    readfile($zipFilename);
    unlink($zipFilename);
    exit;
}

?>

