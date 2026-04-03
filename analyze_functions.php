<?php
$dir = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$functions = [];

foreach ($iterator as $file) {
    if ($file->isDir() || !preg_match('/\.php$/', $file->getFilename())) continue;
    
    $pathname = $file->getPathname();
    if (strpos($pathname, 'fpdf') !== false) continue;
    if (strpos($pathname, 'analyze_functions.php') !== false) continue;
    
    $tokens = token_get_all(file_get_contents($pathname));
    $in_function = false;
    $function_name = '';
    $function_body = '';
    $brace_count = 0;
    $start_line = 0;
    
    $current_function = null;
    
    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        
        if (is_array($token)) {
            if ($token[0] === T_FUNCTION) {
                // Find function name
                $j = $i + 1;
                while ($j < count($tokens)) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $function_name = $tokens[$j][1];
                        $start_line = $tokens[$j][2];
                        $in_function = true;
                        $brace_count = 0;
                        $function_body = '';
                        $current_function = [
                            'name' => $function_name,
                            'file' => str_replace($dir, '', $pathname),
                            'line' => $start_line
                        ];
                        $i = $j;
                        break;
                    }
                    if ($tokens[$j] === '(') { // Anonymous function
                        break;
                    }
                    $j++;
                }
            }
        }
        
        if ($in_function) {
            $char = is_array($token) ? $token[1] : $token;
            // Clean up whitespace for body comparison
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                 $function_body .= ' ';
            } else {
                 $function_body .= $char;
            }
            
            if ($char === '{' || (is_array($token) && $token[0] === T_CURLY_OPEN)) {
                $brace_count++;
            } elseif ($char === '}') {
                $brace_count--;
                if ($brace_count === 0 && $current_function !== null) {
                    $in_function = false;
                    $current_function['hash'] = md5(preg_replace('/\s+/', ' ', $function_body));
                    // naive size
                    $current_function['length'] = strlen($function_body);
                    $functions[] = $current_function;
                    $current_function = null;
                }
            }
        }
    }
}

// Group by hash to find exact duplicates
$by_hash = [];
foreach ($functions as $f) {
    if ($f['length'] < 50) continue; // ignore very short functions
    $by_hash[$f['hash']][] = $f;
}

// Group by name to find same named functions
$by_name = [];
foreach ($functions as $f) {
    $by_name[$f['name']][] = $f;
}

$report = "--- EXACT DUPLICATE FUNCTIONS (Same Body) ---\n";
foreach ($by_hash as $hash => $funcs) {
    if (count($funcs) > 1) {
        $report .= "\nDuplicate Group (Hash: $hash):\n";
        foreach ($funcs as $f) {
            $report .= "  - {$f['name']} in {$f['file']} on line {$f['line']}\n";
        }
    }
}

$report .= "\n\n--- SIMILARLY NAMED FUNCTIONS ---\n";
foreach ($by_name as $name => $funcs) {
    if (count($funcs) > 1) {
        // Only report if they are not in the same file (usually class methods with same name)
        $files = array_column($funcs, 'file');
        if (count(array_unique($files)) > 1) {
            $report .= "\nFunction Name: $name\n";
            foreach ($funcs as $f) {
                $report .= "  - File: {$f['file']} (Line {$f['line']})\n";
            }
        }
    }
}

// Also get a list of all functions and roles (using basic heuristics or just list them)
$report .= "\n\n--- ALL FUNCTIONS LIST ---\n";
usort($functions, function($a, $b) {
    return strcmp($a['file'], $b['file']);
});

$current_file = '';
foreach ($functions as $f) {
    if ($f['file'] !== $current_file) {
        $report .= "\nFile: {$f['file']}\n";
        $current_file = $f['file'];
    }
    $report .= "  - Line {$f['line']}: {$f['name']}\n";
}

file_put_contents('duplicates_report.txt', $report);
echo "Report generated at duplicates_report.txt\n";
