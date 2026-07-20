<?php
/**
 * Verifica rutas legacy rotas en modules/ tras la reorganización.
 * Uso: php scripts/verify_module_paths.php
 */
$root = realpath(__DIR__ . '/..');
$patterns = [
    'config relativo 2 niveles' => '/["\']\.{2}\/\.{2}\/config\//',
    'config relativo 3 niveles' => '/["\']\.{2}\/\.{2}\/\.{2}\/config\/(?!bootstrap)/',
    'configPath realpath legacy' => '/\$configPath\s*=\s*realpath/',
    'login.html relativo' => '/\.{2}\/\.{2}(?:\/\.{2})?\/login\.html/',
    'header/footer relativo' => '/include\s+["\']\.{2}\/\.{2}\/(?:header|footer)\.php/',
    'reporte relativo desde módulo' => '/require(?:_once)?\s+["\']\.{2}\/\.{2}\/reporte\//',
    'PROJECT_ROOT sin bootstrap' => null,
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/modules', RecursiveDirectoryIterator::SKIP_DOTS)
);

$issues = [];
$missingBootstrap = [];

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    foreach ($patterns as $label => $regex) {
        if ($regex === null) {
            continue;
        }
        if (preg_match($regex, $content)) {
            $issues[] = [$rel, $label];
        }
    }

    if (strpos($content, 'PROJECT_ROOT') !== false && strpos($content, 'bootstrap.php') === false) {
        $missingBootstrap[] = $rel;
    }
}

if (!$issues && !$missingBootstrap) {
    echo "OK: No se detectaron rutas legacy rotas en modules/.\n";
    exit(0);
}

if ($issues) {
    echo "Problemas detectados:\n";
    foreach ($issues as [$file, $label]) {
        echo "  [$label] $file\n";
    }
}

if ($missingBootstrap) {
    echo "\nArchivos con PROJECT_ROOT pero sin bootstrap.php:\n";
    foreach ($missingBootstrap as $file) {
        echo "  - $file\n";
    }
}

exit(1);
