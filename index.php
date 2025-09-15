<?php

/**
 * Parses a PlantUML mindmap file into a nested array structure.
 *
 * @param string $filePath The path to the PlantUML file.
 * @return array The parsed mindmap as a nested array.
 */
function parsePlantUMLMindmap($filePath)
{
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $tree = ['children' => []];
    $stack = [&$tree];
    $currentLevel = 0;

    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '@')) {
            continue;
        }

        if (preg_match('/^(\*+)\s(.*)/', $line, $matches)) {
            // FIX: Correctly get the length of the captured group [1] (the asterisks).
            $level = strlen($matches[1]);
            
            // The mindmap text is in captured group [2].
            $text = $matches[2];

            // Navigate the stack to the correct level by moving up or down
            while ($level <= $currentLevel) {
                array_pop($stack);
                $currentLevel--;
            }

            // Create the new node
            $node = ['text' => $text, 'children' => []];

            // Add the new node to the parent's children array
            $parent = &$stack[count($stack) - 1];
            $parent['children'][] = $node;

            // Push a reference to the newly created node onto the stack
            $stack[] = &$parent['children'][count($parent['children']) - 1];
            $currentLevel = $level;
        }
    }

    return $tree['children'];
}

/**
 * Recursively generates HTML <ul>/<li> from a mindmap array.
 *
 * @param array $mindmapData The mindmap data as a nested array.
 * @return string The generated HTML string.
 */
function generateMindmapHtml($mindmapData)
{
    $html = '';
    if (empty($mindmapData)) {
        return '';
    }

    $html .= '<ul class="mindmap-list">' . PHP_EOL;
    foreach ($mindmapData as $node) {
        $hasChildren = !empty($node['children']);
        $html .= '<li>';
        
        if ($hasChildren) {
            $html .= '<span class="caret"></span>';
        }

        $html .= '<span>' . htmlspecialchars($node['text']) . '</span>';
        
        if ($hasChildren) {
            $html .= generateMindmapHtml($node['children']);
        }
        
        $html .= '</li>' . PHP_EOL;
    }
    $html .= '</ul>' . PHP_EOL;

    return $html;
}

// === Security-focused code to handle file path from $_GET ===
$baseDir = __DIR__ . '/mindmaps/';

// Sanitize the user-provided file name to prevent directory traversal.
$fileName = basename($_GET['file_path'] ?? 'mindmap.puml');

// Construct the full, safe file path.
$filePath = $baseDir . $fileName;

// === Check for valid file and file path ===
if (!file_exists($filePath)) {
    die("Error: The file '{$fileName}' was not found.");
}

$realPath = realpath($filePath);
if (strpos($realPath, $baseDir) !== 0) {
    die("Error: Invalid file path.");
}

// Parse the file and generate HTML
$mindmapData = parsePlantUMLMindmap($filePath);
$mindmapHtml = generateMindmapHtml($mindmapData);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collapsible Mindmap</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .controls {
            margin-bottom: 1em;
        }
        .mindmap-list, .mindmap-list ul {
            list-style: none;
            padding-left: 1.5em;
        }
        .mindmap-list ul {
            display: none;
        }
        .mindmap-list .active {
            display: block;
        }
        .mindmap-list li {
            padding: 0.2em 0;
        }
        .caret {
            cursor: pointer;
            user-select: none;
            font-size: 1.2em;
            color: #555;
            display: inline-block;
            margin-right: 0.2em;
        }
        .caret::before {
            content: "►";
        }
        .caret-down::before {
            content: "▼";
        }
        .mindmap-list li > span:not(.caret) {
            cursor: default;
        }
        button {
            padding: 8px 12px;
            margin-right: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Collapsible Mindmap</h1>
    
    <div class="controls">
        <button id="expand-all">Expand All</button>
        <button id="collapse-all">Collapse All</button>
    </div>

    <?php echo $mindmapHtml; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var togglers = document.querySelectorAll(".caret");
            var expandAllBtn = document.getElementById("expand-all");
            var collapseAllBtn = document.getElementById("collapse-all");

            // Function for individual togglers
            togglers.forEach(function(toggler) {
                toggler.addEventListener("click", function() {
                    var nestedList = this.parentElement.querySelector("ul");
                    if (nestedList) {
                        nestedList.classList.toggle("active");
                    }
                    this.classList.toggle("caret-down");
                });
            });

            // Function to expand all
            expandAllBtn.addEventListener("click", function() {
                document.querySelectorAll(".mindmap-list ul").forEach(function(ul) {
                    ul.classList.add("active");
                });
                document.querySelectorAll(".caret").forEach(function(caret) {
                    caret.classList.add("caret-down");
                });
            });

            // Function to collapse all
            collapseAllBtn.addEventListener("click", function() {
                document.querySelectorAll(".mindmap-list ul").forEach(function(ul) {
                    ul.classList.remove("active");
                });
                document.querySelectorAll(".caret").forEach(function(caret) {
                    caret.classList.remove("caret-down");
                });
            });
        });
    </script>
</body>
</html>
