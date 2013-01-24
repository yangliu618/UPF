<?php
/**
 * common config
 * 
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2011-10-09 14:09
 */

// app autoload
$config['app_autoload'] = array(
    '^(Lib)$' => UPF_PATH . '/lib/$1.php',
    '^(.+?)Handler$' => APP_PATH . '/$1.php',
);
// app route
$config['app_routes'] = array(
    'indexHandler' => array(
        '^/$',
        '^/index\.(htm|html|php).*',
    ),
);