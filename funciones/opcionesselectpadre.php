<?php

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

?>
