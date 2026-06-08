<?php
$dir = new RecursiveDirectoryIterator(__DIR__);
$iter = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($iter, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach ($files as $file) {
    $path = $file[0];
    if (basename($path) === 'fixer.php') continue;
    $content = file_get_contents($path);
    if (strpos($content, '\->fetchAssoc(\\)') !== false || strpos($content, '\->numRows(\\)') !== false) {
        $lines = explode("\n", $content);
        $last_query_var = '$result'; // default fallback
        $changed = false;
        for ($i = 0; $i < count($lines); $i++) {
            
            // track assignment of mysqli_query
            if (preg_match('/(\$[a-zA-Z0-9_]+)\s*=\s*mysqli_query\(/', $lines[$i], $m)) {
                $last_query_var = $m[1];
            }
            
            // if ($result && \->numRows(\) > 0)
            if (preg_match('/if\s*\(\s*(\$[a-zA-Z0-9_]+)\s*&&\s*\\\\->numRows\(\\\\\)/', $lines[$i], $m)) {
                $last_query_var = $m[1];
            }
            
            // if ($_sb_res && $_sb_row = \->fetchAssoc(\))
            if (preg_match('/if\s*\(\s*(\$[a-zA-Z0-9_]+)\s*&&\s*\$[a-zA-Z0-9_]+\s*=\s*\\\\->fetchAssoc\(\\\\\)/', $lines[$i], $m)) {
                $last_query_var = $m[1];
            }

            if (strpos($lines[$i], '\->fetchAssoc(\\)') !== false) {
                $lines[$i] = str_replace('\->fetchAssoc(\\)', 'mysqli_fetch_assoc(' . $last_query_var . ')', $lines[$i]);
                $changed = true;
            }
            if (strpos($lines[$i], '\->numRows(\\)') !== false) {
                $lines[$i] = str_replace('\->numRows(\\)', 'mysqli_num_rows(' . $last_query_var . ')', $lines[$i]);
                $changed = true;
            }
        }
        if ($changed) {
            file_put_contents($path, implode("\n", $lines));
            echo "Fixed $path\n";
        }
    }
}
echo "Done.\n";
?>
