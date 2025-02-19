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
    // Eliminar hijos primero
    $stmt = $db->prepare("SELECT id FROM topics WHERE parent_id = ?");
    $stmt->execute([$topic_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($children as $child) {
        deleteTopicRecursive($db, $child['id']);
    }
    // Eliminar el tema actual
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

// Renderiza el menú de navegación en "editar proyecto"
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
  <title>$pageTitle - jocarsa | linen</title>
  <!-- Link to our external CSS -->
  <link rel='stylesheet' href='style.css'>
</head>
<body>
<header>jocarsa | linen</header>
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
$action = $_GET['action'] ?? '';
if ($action == '') {
    $action = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ? 'panel' : 'login';
}

/* =============== LOGIN =============== */
if ($action == 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
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
        echo "<p class='error'>$error</p>";
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
    $project_id = $_GET['id'] ?? '';
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
    $topic_id   = $_GET['id'] ?? '';
    $project_id = $_GET['project_id'] ?? '';
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
    foreach ($projects as $project) {
        echo "<tr>
                <td>" . $project['id'] . "</td>
                <td>" . htmlspecialchars($project['title']) . "</td>
                <td>" . htmlspecialchars($project['description']) . "</td>
                <td>
                  <a href='?action=edit_project&id=" . $project['id'] . "'>Editar</a> | 
                  <a href='?action=export_scorm&id=" . $project['id'] . "'>Exportar SCORM</a> | 
                  <a href='?action=presentation&id=" . $project['id'] . "' target='_blank'>Presentación</a> | 
                  <a class='delete-link' 
                     href='?action=delete_project&id=" . $project['id'] . "' 
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
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
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
        echo "<p class='error'>$error</p>";
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
    $project_id = $_GET['id'] ?? '';
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
    
    // Nuevo tema
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_topic'])) {
        $title     = $_POST['topic_title']   ?? '';
        $content   = $_POST['topic_content'] ?? '';
        $type      = $_POST['topic_type']    ?? 'text';
        $parent_id = $_POST['parent_id']     ?? 0;
        if (trim($title) == '') {
            $error = "El título del tema es obligatorio.";
        } else {
            $stmt = $db->prepare("INSERT INTO topics (project_id, parent_id, title, content, type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$project_id, $parent_id, $title, $content, $type]);
            header("Location: ?action=edit_project&id=" . $project_id);
            exit;
        }
    }
    
    // Obtener tópicos
    $stmt = $db->prepare("SELECT * FROM topics WHERE project_id = ? ORDER BY id ASC");
    $stmt->execute([$project_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topicsTree = buildTree($topics);
    
    // Si hay un tema seleccionado
    $selected_topic = null;
    if (isset($_GET['topic_id'])) {
        $selected_topic_id = $_GET['topic_id'];
        $stmt = $db->prepare("SELECT * FROM topics WHERE id = ? AND project_id = ?");
        $stmt->execute([$selected_topic_id, $project_id]);
        $selected_topic = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    renderHeader("Editar Proyecto");
    echo "<h2>Editar Proyecto: " . htmlspecialchars($project['title']) . "</h2>";
    echo "<p><a href='?action=panel'>Volver al Panel</a></p>";
    
    echo "<div class='two-pane'>";
      // Panel izquierdo (árbol)
      echo "<div class='pane-left'>";
      echo "<h3>Estructura del Proyecto</h3>";
      if (!empty($topicsTree)) {
          renderTopicNav($topicsTree, $project_id);
      } else {
          echo "<p>No hay temas creados.</p>";
      }
      echo "</div>";
      
      // Panel derecho (contenido)
      echo "<div class='pane-right'>";
      if ($selected_topic) {
          echo "<h3>Contenido del Tema</h3>";
          echo "<p><strong>Título:</strong> " . htmlspecialchars($selected_topic['title']) . "</p>";
          echo "<p><strong>Tipo:</strong> " . htmlspecialchars($selected_topic['type']) . "</p>";
          echo "<p><strong>Contenido:</strong><br/>" . nl2br(htmlspecialchars($selected_topic['content'])) . "</p>";
          echo "<p>
                  <a class='delete-link' 
                     href='?action=delete_topic&id=" . $selected_topic['id'] 
                     . "&project_id=" . $project_id 
                     . "' onclick='return confirm(\"¿Está seguro de eliminar este tema?\")'>
                      Eliminar Tema
                  </a>
                </p>";
      } else {
          echo "<p>Selecciona un tema en el panel de la izquierda para ver su contenido.</p>";
      }
      
      // Formulario para añadir un nuevo tema
      echo "<h3>Añadir Nuevo Tema/Recurso</h3>";
      if ($error) {
          echo "<p class='error'>$error</p>";
      }
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
      echo "<textarea name='topic_content'></textarea><br/>";
      echo "<label>Padre:</label>";
      echo "<select name='parent_id'>
              <option value='0'>Ninguno</option>";
      if (!empty($topicsTree)) {
          renderParentOptions($topicsTree);
      }
      echo "</select><br/>";
      echo "<input type='submit' name='new_topic' value='Añadir Tema/Recurso' />";
      echo "</form>";
      
      echo "</div>"; // pane-right
    echo "</div>"; // two-pane
    
    renderFooter();
    exit;
}

/* ===================================================================
   PRESENTATION MODE (two horizontal panes, mark visited, single tab)
   =================================================================== */
if ($action == 'presentation') {
    $db = getDB();
    $project_id = $_GET['id'] ?? '';
    if (!$project_id) {
        echo "Proyecto no especificado.";
        exit;
    }
    // Verificar proyecto
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
    $selected_topic_id = $_GET['topic_id'] ?? null;
    $selected_topic = null;
    if ($selected_topic_id) {
        // Buscar el topic en la lista
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

    // Función para renderizar la navegación marcando si ya fue visto
    function renderPresentationNav($tree, $project_id, $visited, $level=0) {
        foreach ($tree as $node) {
            $margin = 15 * $level;
            $title = htmlspecialchars($node['title']);
            // Si el ID está en visited, lo marcamos
            $visitedMark = in_array($node['id'], $visited) ? " (visto)" : "";
            echo "<div style='margin-left:{$margin}px;'>";
            echo "<a href='?action=presentation&id={$project_id}&topic_id={$node['id']}'>"
                 . $title . "</a> <span class='visited-mark'>{$visitedMark}</span>";
            echo "</div>";
            if (!empty($node['children'])) {
                renderPresentationNav($node['children'], $project_id, $visited, $level+1);
            }
        }
    }

    // Render
    renderHeader("Presentación: " . htmlspecialchars($project['title']));
    echo "<h2>Presentación del Proyecto: " . htmlspecialchars($project['title']) . "</h2>";

    // Layout: two horizontal panes
    echo "<div class='two-pane'>";

    // Left pane: navigation
    echo "<div class='pane-left'>";
    echo "<h3>Navegación</h3>";
    if (!empty($topicsTree)) {
        renderPresentationNav($topicsTree, $project_id, $_SESSION['presentation_visited'][$project_id]);
    } else {
        echo "<p>No hay temas disponibles.</p>";
    }
    echo "</div>";

    // Right pane: content if selected
    echo "<div class='pane-right'>";
    if ($selected_topic) {
        echo "<h3>Tema Seleccionado</h3>";
        echo "<p><strong>" . htmlspecialchars($selected_topic['title']) . "</strong></p>";
        echo "<p>" . nl2br(htmlspecialchars($selected_topic['content'])) . "</p>";
    } else {
        echo "<p>Seleccione un tema en la izquierda.</p>";
    }
    echo "</div>";

    echo "</div>"; // .two-pane

    renderFooter();
    exit;
}

/* ======================================================
   Exportación SCORM
   ====================================================== */
if ($action == 'export_scorm') {
    $db = getDB();
    $project_id = $_GET['id'] ?? '';
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
    
    // Recursivo <item>
    function generateItemsXML($topics) {
        $xml = "";
        foreach ($topics as $topic) {
            $identifier = "ITEM_" . $topic['id'];
            $resourceRef = "RES_" . $topic['id'];
            $xml .= "<item identifier=\"$identifier\" identifierref=\"$resourceRef\">\n";
            $xml .= "<title>" . htmlspecialchars($topic['title']) . "</title>\n";
            if (!empty($topic['children'])) {
                $xml .= generateItemsXML($topic['children']);
            }
            $xml .= "</item>\n";
        }
        return $xml;
    }
    $itemsXML = generateItemsXML($topicsTree);
    
    // imsmanifest.xml
    $manifestXML = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $manifestXML .= '<manifest identifier="MANIFEST_' . $project['id'] . '" version="1.2" ';
    $manifestXML .= 'xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" ';
    $manifestXML .= 'xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2" ';
    $manifestXML .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
    $manifestXML .= 'xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd ';
    $manifestXML .= 'http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd">' . "\n";
    $manifestXML .= "<organizations default=\"ORG1\">\n";
    $manifestXML .= "<organization identifier=\"ORG1\">\n";
    $manifestXML .= "<title>" . htmlspecialchars($project['title']) . "</title>\n";
    $manifestXML .= $itemsXML;
    $manifestXML .= "</organization>\n";
    $manifestXML .= "</organizations>\n";
    $manifestXML .= "<resources>\n";
    foreach ($topics as $topic) {
        $identifier = "RES_" . $topic['id'];
        $href       = "topic_" . $topic['id'] . ".html";
        $manifestXML .= '<resource identifier="' . $identifier 
                     . '" type="webcontent" adlcp:scormType="sco" href="' . $href . '">' . "\n";
        $manifestXML .= '<file href="' . $href . '"/>' . "\n";
        $manifestXML .= "</resource>\n";
    }
    $manifestXML .= "</resources>\n";
    $manifestXML .= "</manifest>";
    
    // SCORM API JS (simplificado)
    $scormApiJS = <<<EOT
// Minimal SCORM 1.2 API Example
var g_api = null;
var g_isInitialized = false;

function findAPI(win) {
  var attempts = 0;
  while ((win.API == null) && (win.parent != null) && (win.parent != win) && (attempts <= 10)) {
    attempts++;
    win = win.parent;
  }
  return win.API;
}

function getAPI() {
  if (g_api == null) {
    g_api = findAPI(window);
  }
  return g_api;
}

function scormInit() {
  var api = getAPI();
  if (api == null) return;
  if (!g_isInitialized) {
    var result = api.LMSInitialize("");
    g_isInitialized = (result.toString() == "true");
  }
}

function scormFinish() {
  var api = getAPI();
  if (api == null) return;
  if (g_isInitialized) {
    api.LMSFinish("");
  }
}

function scormCommit() {
  var api = getAPI();
  if (api == null) return;
  if (g_isInitialized) {
    api.LMSCommit("");
  }
}

function scormSetValue(name, value) {
  var api = getAPI();
  if (api && g_isInitialized) {
    api.LMSSetValue(name, value);
  }
}

function scormGetValue(name) {
  var api = getAPI();
  if (api && g_isInitialized) {
    return api.LMSGetValue(name);
  }
  return "";
}

function markTopicVisited(topicId) {
  var visited = scormGetValue("cmi.suspend_data") || "";
  if (visited.indexOf(","+topicId+",") === -1) {
    visited += "," + topicId + ",";
    scormSetValue("cmi.suspend_data", visited);
    scormCommit();
  }
}
EOT;

    // Style CSS content (with background set to linen)
    $styleCSS = <<<CSS
@import url('https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap');

/* =======================================
   CSS Variables (Color Palette)
   ======================================= */
:root {
    --header-bg: #283593;     /* Deep Indigo */
    --header-text: #ffffff;
    --body-bg: linen;         /* Linen background */
    --container-bg: #ffffff;
    --text-color: #212121;    /* Nearly black */
    --accent-color: #009688;  /* Teal */
    --accent-hover: #00796B;  /* Darker Teal */
    --danger-color: #E74C3C;  /* Red */
    --border-color: #ddd;
    --shadow-color: rgba(0,0,0,0.1);

    --transition-speed: 0.3s;
    --font-family: 'Ubuntu', 'Segoe UI', Tahoma, sans-serif;

    /* Buttons */
    --button-bg: var(--accent-color);
    --button-hover: var(--accent-hover);
    --button-text: #ffffff;
}

/* =======================================
   Global Reset & Basic Elements
   ======================================= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    width: 100%;
    background-color: var(--body-bg);
    color: var(--text-color);
    font-family: var(--font-family);
    font-size: 16px;
    line-height: 1.4;
}

/* Remove default list style from any lists if used */
ul, ol {
    list-style: none;
    padding: 0;
    margin: 0;
}

/* Links */
a {
    color: var(--accent-color);
    text-decoration: none;
    transition: color var(--transition-speed) ease;
}
a:hover {
    color: var(--accent-hover);
    text-decoration: underline;
}

/* =======================================
   Header
   ======================================= */
header {
    background-color: var(--header-bg);
    color: var(--header-text);
    padding: 20px;
    text-align: center;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 1px;
    box-shadow: 0 2px 4px var(--shadow-color);
}

/* =======================================
   Container (central wrapper)
   ======================================= */
.container {
    width: 100%;
    max-width: 1200px;
    margin: 20px auto;
    background-color: var(--container-bg);
    padding: 20px;
    box-shadow: 0 2px 8px var(--shadow-color);
    border-radius: 6px;
}

/* =======================================
   Navigation Bar
   ======================================= */
.navbar {
    margin-bottom: 20px;
}

.navbar a {
    margin-right: 20px;
    font-weight: 600;
}
.navbar a:hover {
    text-decoration: underline;
}

/* =======================================
   Tables
   ======================================= */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0;
    background-color: #fff;
}

/* Table header and cells */
th, td {
    border: 1px solid var(--border-color);
    padding: 12px;
    text-align: left;
    vertical-align: middle;
    transition: background var(--transition-speed) ease;
}

th {
    background-color: #f0f0f0;
    font-weight: 500;
}

/* Table row hover */
tr:hover td {
    background-color: #fafafa;
}

/* =======================================
   Form Elements
   ======================================= */
form input[type='text'],
form input[type='password'],
form select,
form textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-family: var(--font-family);
    font-size: 14px;
}

/* Buttons */
form input[type='submit'],
.button {
    background-color: var(--button-bg);
    border: none;
    color: var(--button-text);
    padding: 12px 25px;
    font-size: 16px;
    font-weight: 500;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color var(--transition-speed) ease;
    text-decoration: none; /* for .button links */
    display: inline-block; /* so .button links size properly */
}

form input[type='submit']:hover,
.button:hover {
    background-color: var(--button-hover);
}

/* =======================================
   Error Messages
   ======================================= */
.error {
    color: var(--danger-color);
    margin-bottom: 15px;
    font-weight: 500;
}

/* =======================================
   Two-Pane Layout
   ======================================= */
.two-pane {
    display: flex;
    gap: 20px;
    min-height: 60vh; /* Example height */
}

.pane-left {
    flex: 1;
    max-width: 300px;
    border-right: 1px solid var(--border-color);
    padding-right: 10px;
    overflow-y: auto;
}

.pane-right {
    flex: 2;
    padding-left: 10px;
    overflow-y: auto;
}

.two-pane h3 {
    margin-bottom: 15px;
    font-size: 18px;
    font-weight: 600;
}

/* Nested items spacing in navigation */
.pane-left div {
    margin-bottom: 8px;
}

/* Mark visited items in presentation mode */
.visited-mark {
    color: green;
    font-size: 0.9em;
    margin-left: 5px;
}

/* =======================================
   Delete Links
   ======================================= */
.delete-link {
    color: var(--danger-color);
    font-size: 0.9em;
    font-weight: 500;
    margin-left: 8px;
    transition: color var(--transition-speed) ease;
}
.delete-link:hover {
    color: #c0392b; /* darker red on hover */
    text-decoration: underline;
}

/* =======================================
   Responsive Adjustments
   ======================================= */
@media (max-width: 768px) {
    header {
        font-size: 20px;
        padding: 15px;
    }
    
    .container {
        margin: 10px auto;
        padding: 15px;
    }
    
    .navbar a {
        margin-right: 10px;
    }
    
    .two-pane {
        flex-direction: column;
        min-height: auto;
        height: auto;
    }
    
    .pane-left, .pane-right {
        max-width: 100%;
        border: none;
        padding: 0;
    }
    
    .pane-left {
        margin-bottom: 20px;
    }
}
CSS;

    // Crear ZIP
    $zip = new ZipArchive();
    $zipFilename = tempnam(sys_get_temp_dir(), 'scorm_') . '.zip';
    if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
        exit("No se pudo crear el archivo ZIP.\n");
    }

    // 1) Agregamos el manifest
    $zip->addFromString("imsmanifest.xml", $manifestXML);

    // 2) Agregamos la API JS
    $zip->addFromString("scorm_api.js", $scormApiJS);

    // 3) Agregamos style.css
    $zip->addFromString("style.css", $styleCSS);

    // 4) Creamos un HTML por cada topic (referenciando style.css y scorm_api.js)
    foreach ($topics as $topic) {
        $titleSafe   = htmlspecialchars($topic['title']);
        $contentSafe = nl2br(htmlspecialchars($topic['content']));
        $topicId     = (int)$topic['id'];
        
        $htmlContent = <<<HTML
<html>
<head>
  <meta charset='UTF-8'>
  <title>{$titleSafe}</title>
  <link rel="stylesheet" href="style.css" />
  <script src="scorm_api.js"></script>
  <script>
    window.onload = function() {
      scormInit();
      markTopicVisited({$topicId});
    };
    window.onunload = function() {
      scormFinish();
    };
    window.onbeforeunload = function() {
      scormFinish();
    };
  </script>
</head>
<body>
<header>{$titleSafe}</header>
<div class="container">
  <h2>{$titleSafe}</h2>
  <div>{$contentSafe}</div>
</div>
</body>
</html>
HTML;
        
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

