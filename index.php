<?php
session_start();

/* ======================================================
   1. Conexión a la base de datos y creación de tablas
   ====================================================== */
include "funciones/abrirbasededatos.php";

include "funciones/inicializarbasededatos.php";
initDB();
include "funciones/obtenerconfiguracionusuario.php";
include "funciones/actualizarconfiguracionusuario.php";


/* ======================================================
   Función para eliminar recursivamente un tema
   ====================================================== */
include "funciones/eliminartemarecursivo.php";

/* ======================================================
   Funciones para el árbol de temas
   ====================================================== */
include "funciones/creararboldetemas.php";
include "funciones/representaarbolnavegacion.php";
include "funciones/opcionesselectpadre.php";


/* ======================================================
   3. Header y Footer comunes
   ====================================================== */
include "funciones/representarcabecera.php";
include "funciones/representarpiedepagina.php";


/* ======================================================
   4. Enrutamiento
   ====================================================== */
$action = isset($_GET['action']) ? $_GET['action'] : '';
if ($action == '') {
    include "rutas/pordefecto.php";
}

if ($action == 'login') {
    include "rutas/login.php";
}

if ($action == 'logout') {
    include "rutas/logout.php";
}

if ($action == 'delete_project') {
    include "rutas/eliminarproyecto.php";
}

if ($action == 'delete_topic') {
    include "rutas/eliminartopic.php";
}

if ($action == 'panel') {
    include "rutas/panel.php";
}

if ($action == 'create_project') {
    include "rutas/crearproyecto.php";
}

if ($action == 'edit_project') {
    include "rutas/editarproyecto.php";
}

if ($action == 'configuration') {
    include "rutas/configuracion.php";
}

if ($action == 'presentation') {
    include "rutas/presentacion.php";
}

if ($action == 'export_scorm') {
    include "rutas/exportarscorm.php";
}

if ($action == 'duplicate_project') {
    include "rutas/duplicarproyecto.php";
}

if ($action == 'reorder_topics') {
    include "rutas/reordenar.php";
}

if ($action == 'edit_project_info') {
    include "rutas/editarinformacionproyecto.php";
}
?>

