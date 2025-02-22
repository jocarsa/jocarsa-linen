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
}
initDB();

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

// Opciones para el <select> de "padre"
function renderParentOptions($tree, $level = 0) {
    foreach ($tree as $node) {
        echo "<option value='" . $node['id'] . "'>"
             . str_repeat("--", $level) . " "
             . htmlspecialchars($node['title'])
             . "</option>";
        if (!empty($node['children'])) {
            renderParentOptions($node['children'], $level + 1);
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

/* =============== PANEL =============== */
if ($action == 'panel') {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    renderHeader("Panel de Administración");
    echo "<div class='navbar'>
            <a href='?action=logout'>Salir</a> |
            <a href='?action=create_project'>Crear Proyecto</a>
          </div>";
    echo "<h2>Panel de Administración</h2>";
    echo "<h3>Proyectos existentes</h3>";
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
                  <a href='?action=edit_project&id=" . $proj['id'] . "'>Editar</a> |
                  <a href='?action=export_scorm&id=" . $proj['id'] . "'>Exportar SCORM</a> |
                  <a href='?action=presentation&id=" . $proj['id'] . "' target='_blank'>Presentación</a> |
                  <a class='delete-link'
                     href='?action=delete_project&id=" . $proj['id'] . "'
                     onclick='return confirm(\"¿Está seguro de eliminar este proyecto?\")'>Eliminar</a>
                </td>
              </tr>";
    }
    echo "</table>";
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

/* =============== EDIT PROJECT (two-panel layout) =============== */
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
        $topic_id   = isset($_POST['topic_id']) ? $_POST['topic_id'] : '';
        $title      = isset($_POST['topic_title'])   ? $_POST['topic_title']   : '';
        $content    = isset($_POST['topic_content']) ? $_POST['topic_content'] : '';
        $type       = isset($_POST['topic_type'])    ? $_POST['topic_type']    : 'text';
        $parent_id  = isset($_POST['parent_id'])    ? $_POST['parent_id']    : 0; // Allow changing parent

        if (trim($title) == '') {
            $error = "El título del tema es obligatorio.";
        } else {
            $stmt = $db->prepare("UPDATE topics
                                  SET title = ?, content = ?, type = ?, parent_id = ?
                                  WHERE id = ? AND project_id = ?");
            $stmt->execute([$title, $content, $type, $parent_id, $topic_id, $project_id]);
            header("Location: ?action=edit_project&id=" . $project_id . "&topic_id=" . $topic_id);
            exit;
        }
    }

    // 2) Create new topic (if "new_topic" form is submitted)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_topic'])) {
        $title     = isset($_POST['topic_title'])   ? $_POST['topic_title']   : '';
        $content   = isset($_POST['topic_content']) ? $_POST['topic_content'] : '';
        $type      = isset($_POST['topic_type'])    ? $_POST['topic_type']    : 'text';
        $parent_id = isset($_POST['parent_id'])     ? $_POST['parent_id']     : 0;
        if (trim($title) == '') {
            $error = "El título del tema es obligatorio.";
        } else {
            $stmt = $db->prepare("INSERT INTO topics (project_id, parent_id, title, content, type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$project_id, $parent_id, $title, $content, $type]);
            // After creation, redirect back
            header("Location: ?action=edit_project&id=" . $project_id);
            exit;
        }
    }
    // >>> END NEW OR MODIFIED CODE <<<

    // Obtener la lista de tópicos
    $stmt = $db->prepare("SELECT * FROM topics WHERE project_id = ? ORDER BY id ASC");
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

        // >>> NEW OR MODIFIED CODE <<<
        // Button to show "Crear Nuevo Tema/Recurso" form in the right pane
        // We'll pass a GET parameter 'show_new_topic=1' to display that form
        echo "<p style='margin-top:20px;'>
                <a class='button'
                   href='?action=edit_project&id=" . $project_id . "&show_new_topic=1'>
                   Añadir Nuevo Tema/Recurso
                </a>
              </p>";
        // >>> END NEW OR MODIFIED CODE <<<
      echo "</div>";

      // =================================
      // Panel derecho (contenido)
      // =================================
      echo "<div class='pane-right'>";

        // Si hay error, lo mostramos
        if ($error) {
            echo "<p class='error'>" . $error . "</p>";
        }

        // >>> NEW OR MODIFIED CODE <<<
        // 1) If user clicked "Añadir Nuevo Tema/Recurso" -> show creation form
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
            echo "<input type='submit' name='new_topic' value='Crear Tema' />";
            echo "</form>";

        // 2) If user clicked “Edit” for an existing topic
        } elseif (isset($_GET['edit_topic']) && $selected_topic) {
            echo "<h3>Editar Tema/Recurso</h3>";
            echo "<form method='post' action='?action=edit_project&id=" . $project_id . "&topic_id=" . $selected_topic['id'] . "'>";
            echo "<input type='hidden' name='topic_id' value='" . $selected_topic['id'] . "' />";
            echo "<label>Título:</label>";
            echo "<input type='text' name='topic_title' required value='"
                 . htmlspecialchars($selected_topic['title']) . "'/><br/>";
            echo "<label>Tipo:</label>";
            echo "<select name='topic_type'>";
            $types = ['text'=>'Texto','task'=>'Tarea','interactive'=>'Actividad Interactiva'];
            foreach ($types as $val => $label) {
                $selected = ($selected_topic['type'] == $val) ? "selected" : "";
                echo "<option value='$val' $selected>$label</option>";
            }
            echo "</select><br/>";
            echo "<label>Contenido:</label>";
            echo "<textarea name='topic_content' class='jocarsa-lightslateblue'>"
                 . htmlspecialchars($selected_topic['content']) . "</textarea><br/>";
            echo "<label>Padre:</label>";
            echo "<select name='parent_id'>
                    <option value='0'>Ninguno</option>";
            if (!empty($topicsTree)) {
                renderParentOptions($topicsTree, 0, $selected_topic['id']);
            }
            echo "</select><br/>";
            echo "<input type='submit' name='update_topic' value='Guardar Cambios' />";
            echo "</form>";

        // 3) Otherwise, show the selected topic details if any
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
                       href='?action=delete_topic&id=" . $selected_topic['id']
                       . "&project_id=" . $project_id
                       . "' onclick='return confirm(\"¿Está seguro de eliminar este tema?\")'>
                        Eliminar Tema
                    </a>
                  </p>";
        } else {
            // If no topic is selected and no new/edit forms are shown
            echo "<p>Selecciona un tema en el panel de la izquierda para ver o editar su contenido,
                  o haz clic en <strong>Añadir Nuevo Tema/Recurso</strong> para crear uno nuevo.</p>";
        }
        // >>> END NEW OR MODIFIED CODE <<<

      echo "</div>"; // pane-right
    echo "</div>";   // two-pane

    // For your custom CSS/JS
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

    // Obtener todos los temas
    $stmt = $db->prepare("SELECT * FROM topics WHERE project_id = ? ORDER BY id ASC");
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

    // Obtener temas
    $stmt = $db->prepare("SELECT * FROM topics WHERE project_id = ?");
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
    $styleCSS =
"@import url('https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap');\n"
. ":root {\n"
. "  --header-bg: #283593;\n"
. "  --header-text: #ffffff;\n"
. "  --text-color: #212121;\n"
. "}\n"
. "body {\n"
. "  font-family: 'Ubuntu', sans-serif;\n"
. "  margin: 0; padding: 20px;\n"
. "  border-top: 15px solid #782f40;\n"
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
            . "    <h2>" . $titleSafe . "</h2>\n"
            . "    <div>" . $contentSafe . "</div>\n"
            . "  </div>\n"
            . "</body>\n"
            . "</html>";

        $filename = "topic_" . $topicId . ".html";
        $zip->addFromString($filename, $htmlContent);
    }

    $zip->close();

    // Forzar descarga
    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=project_' . $project['id'] . '_scorm.zip');
    header('Content-Length: ' . filesize($zipFilename));
    readfile($zipFilename);
    unlink($zipFilename);
    exit;
}
?>

