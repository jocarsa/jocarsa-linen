<?php

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

?>
