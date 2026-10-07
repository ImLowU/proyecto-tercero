<?php
// Al entrar a la carpeta raíz del proyecto (ej: http://localhost/nextleague/)
// se redirige directo a la página de inicio, que vive dentro de public/.
// Se manda a la carpeta (public/home/) y no a public/home/index.html: el
// servidor entrega igual su index.html y en la barra de direcciones no
// aparece el nombre del archivo.
header("Location: public/home/", true, 302);
exit;
