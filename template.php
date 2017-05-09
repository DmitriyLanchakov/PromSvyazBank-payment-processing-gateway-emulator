<?php

function load_template($name, $vars, $layout = null)
{
    if ($layout) {
        ob_start();
    }

    extract($vars);
    include 'templates/'.$name.'.php';

    if ($layout) {
        $content = ob_get_clean();
        include 'templates/'.$layout.'.php';
    }
}
