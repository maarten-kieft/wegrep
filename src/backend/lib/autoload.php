<?php
declare(strict_types=1);

// Class loader for the Wegrep\ namespace: Wegrep\Http\Router lives in lib/Http/Router.php.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Wegrep\\')) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen('Wegrep\\'))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
