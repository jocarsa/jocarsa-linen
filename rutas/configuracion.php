<?php
	$error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $corporateColor = isset($_POST['corporate_color']) ? $_POST['corporate_color'] : '';
        $fontFamily = isset($_POST['font_family']) ? $_POST['font_family'] : '';
        $textColor = isset($_POST['text_color']) ? $_POST['text_color'] : '';
        $fontSize = isset($_POST['font_size']) ? $_POST['font_size'] : '';

        if (!empty($corporateColor)) {
            updateUserConfig($_SESSION['user_id'], 'color_corporativo', $corporateColor);
        }
        if (!empty($fontFamily)) {
            updateUserConfig($_SESSION['user_id'], 'familia_de_fuentes', $fontFamily);
        }
        if (!empty($textColor)) {
            updateUserConfig($_SESSION['user_id'], 'color', $textColor);
        }
        if (!empty($fontSize)) {
            updateUserConfig($_SESSION['user_id'], 'tamaño_de_fuente', $fontSize);
        }
        $error = "Configuración actualizada.";
    }

    $corporateColor = getUserConfig($_SESSION['user_id'], 'color_corporativo');
    $fontFamily = getUserConfig($_SESSION['user_id'], 'familia_de_fuentes');
    $textColor = getUserConfig($_SESSION['user_id'], 'color');
    $fontSize = getUserConfig($_SESSION['user_id'], 'tamaño_de_fuente');

    renderHeader("Configuración");
    echo "<h2>Configuración</h2>";
    if ($error) {
        echo "<p class='error'>" . $error . "</p>";
    }
    echo "<form method='post' action='?action=configuration'>
          <label>Color Corporativo:</label>
          <input type='color' name='corporate_color' value='" . $corporateColor . "' required /><br/>
          <label>Familia de Fuentes:</label>
          <select name='font_family'>
            <option value='sans-serif' " . ($fontFamily == 'sans-serif' ? 'selected' : '') . ">Sans-serif</option>
            <option value='serif' " . ($fontFamily == 'serif' ? 'selected' : '') . ">Serif</option>
            <option value='monospace' " . ($fontFamily == 'monospace' ? 'selected' : '') . ">Monospace</option>
            <option value='fantasy' " . ($fontFamily == 'fantasy' ? 'selected' : '') . ">Fantasy</option>
            <option value='cursive' " . ($fontFamily == 'cursive' ? 'selected' : '') . ">Cursive</option>
                    <option value='Ubuntu' " . ($fontFamily == 'Ubuntu' ? 'selected' : '') . ">Ubuntu</option>

          </select><br/>
          <label>Color de Texto:</label>
          <input type='color' name='text_color' value='" . $textColor . "' required /><br/>
          <label>Tamaño de Fuente:</label>
          <input type='text' name='font_size' value='" . $fontSize . "' required /><br/>
          <input type='submit' value='Guardar Configuración' />
          </form>";
    echo "<p><a href='?action=panel'>Volver al Panel</a></p>";
    renderFooter();
    exit;
?>
