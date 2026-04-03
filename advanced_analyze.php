<?php
$dir = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$functions = [];

foreach ($iterator as $file) {
    if ($file->isDir() || !preg_match('/\.php$/', $file->getFilename())) continue;
    
    $pathname = $file->getPathname();
    if (strpos($pathname, 'fpdf') !== false) continue;
    if (strpos($pathname, 'analyze_functions') !== false) continue;
    if (strpos($pathname, 'advanced_analyze.php') !== false) continue;
    
    $tokens = token_get_all(file_get_contents($pathname));
    $in_function = false;
    $function_name = '';
    $function_structure = '';
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
                        $function_structure = '';
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
            if (is_array($token)) {
                $type = $token[0];
                if ($type !== T_WHITESPACE && $type !== T_COMMENT && $type !== T_DOC_COMMENT) {
                    if ($type === T_VARIABLE) {
                        $function_structure .= '$VAR'; // Normalize variables
                    } else if ($type === T_STRING && $token[1] !== $function_name) {
                        $function_structure .= 'STR'; // Normalize string literals and function calls (basic)
                    } else if ($type === T_CONSTANT_ENCAPSED_STRING) {
                        $function_structure .= '"VAL"'; // Normalize string values
                    } else if ($type === T_DNUMBER || $type === T_LNUMBER) {
                        $function_structure .= 'NUM'; // Normalize numbers
                    } else {
                        $function_structure .= $token[1];
                    }
                }
            } else {
                $function_structure .= $token;
            }
            
            $char = is_array($token) ? $token[1] : $token;
            if ($char === '{' || (is_array($token) && $token[0] === T_CURLY_OPEN)) {
                $brace_count++;
            } elseif ($char === '}') {
                $brace_count--;
                if ($brace_count === 0 && $current_function !== null) {
                    $in_function = false;
                    $current_function['hash'] = md5($function_structure);
                    $current_function['length'] = strlen($function_structure);
                    $functions[] = $current_function;
                    $current_function = null;
                }
            }
        }
    }
}

// Group by hash to find structurally identical functions
$by_hash = [];
foreach ($functions as $f) {
    if ($f['length'] < 50) continue; // ignore very short functions
    // Skip constructors because they are often structurally identical (just setting properties)
    if ($f['name'] === '__construct') continue; 
    $by_hash[$f['hash']][] = $f;
}

$report = "--- STRUCTURALLY DUPLICATE FUNCTIONS (Same Logic, Different Variables/Strings) ---\n";
foreach ($by_hash as $hash => $funcs) {
    if (count($funcs) > 1) {
        $report .= "\nStructural Group (Hash: $hash):\n";
        foreach ($funcs as $f) {
            $report .= "  - {$f['name']} in {$f['file']} on line {$f['line']}\n";
        }
    }
}

file_put_contents('structural_duplicates_report.txt', $report);
echo "Report generated at structural_duplicates_report.txt\n";
