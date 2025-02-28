<?php
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

    // Obtener temas ordenados por sort_order
    $stmt = $db->prepare("SELECT * FROM topics WHERE project_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$project_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topicsTree = buildTree($topics);

    // Generar <item> recursivamente
    function generateItemsXML($topics) {
        $itemsXML = "";
        foreach ($topics as $topic) {
            $identifier = "ITEM_" . $topic['id'];
            $resourceRef = "RES_" . $topic['id'];
            $itemsXML .= "<item identifier=\"" . $identifier . "\" identifierref=\"" . $resourceRef . "\" isvisible=\"true\">\n";
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
        . 'xmlns:adlseq="http://www.adlnet.org/xsd/adlseq_rootv1p2" '
        . 'xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd '
        . 'http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd '
        . 'http://www.adlnet.org/xsd/adlseq_rootv1p2 adlseq_rootv1p2.xsd">' . "\n"
        . '<organizations default="ORG1">' . "\n"
        . '<organization identifier="ORG1">' . "\n"
        . '<title>' . htmlspecialchars($project['title']) . '</title>' . "\n"
        . $itemsXML
        . '</organization>' . "\n"
        . '</organizations>' . "\n"
        . '<resources>' . "\n"
        . $resources
        . '</resources>' . "\n"
        . '<sequencing>' . "\n"
        . '<adlseq:sequencingRules adlseq:flow="true" />' . "\n"
        . '</sequencing>' . "\n"
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
?>
