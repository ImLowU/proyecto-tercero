<?php
// =====================================================================
// CARGA AUTOMÁTICA DE CLASES
// Cuando el código usa una clase (ej: "Torneo"), PHP llama a esta función
// para que cargue su archivo. Cada clase vive en un archivo con su mismo
// nombre dentro de una de estas carpetas, así no hacen falta los
// require_once de la versión anterior en cada archivo.
// =====================================================================

spl_autoload_register(function (string $clase): void {
    foreach (['core', 'modelos', 'modulos', 'servicios', 'controladores'] as $carpeta) {
        $archivo = __DIR__ . "/$carpeta/$clase.php";
        if (is_file($archivo)) {
            require $archivo;
            return;
        }
    }
});
