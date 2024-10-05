<?php
/*
Plugin Name: Micro Deploy
Description: Deploys micro frontends to Wordpress
Version: 1.0
Author: Ștefan Secrieru
*/

require_once(plugin_dir_path(__FILE__) . 'scripts/admin-dashboard.php');
require_once(plugin_dir_path(__FILE__) . 'scripts/micro.php');

// Hook to add a menu option in the WordPress admin
add_action('admin_menu', 'micro_deploy_generate_admin_dashboard_page');

function micro_deploy_generate_admin_dashboard_page() {
    add_menu_page('Micro Deploy', 'Micro Deploy', 'manage_options', 'micro-deploy', 'micro_deploy_generate_admin_page');
}

add_action('admin_enqueue_scripts', 'micro_deploy_enqueue_admin_styles');

function micro_deploy_enqueue_admin_styles() {
    wp_enqueue_style('micro-deploy-admin-styles', plugins_url('assets/css/admin-style.css', __FILE__));
    wp_enqueue_style('micro-deploy-general-styles', plugins_url('assets/css/general.css', __FILE__));

    wp_enqueue_script('react', plugins_url('micros/test123/build/static/js/main.15dc85d0.js', __FILE__));
}

//add_action('init', function () {
//    add_rewrite_rule(
//        '^landing-page/?$',
//        'index.php?micro=landing-page',
//        'top'
//    );
////    for static files
//    add_rewrite_rule(
//        '^landing-page/(.+)',
//        'index.php?micro=landing-page&static_file=$matches[1]',
//        'top'
//    );
//    flush_rewrite_rules();
//});


add_action('init', function () {
    global $wpdb;
    $micro_table_name = $wpdb->prefix . 'microdeploy_micros';
    $results = $wpdb->get_results("SELECT slug FROM $micro_table_name");

    foreach ($results as $micro_name){
        add_rewrite_rule(
            '^' . $micro_name->slug . '/?$',
            'index.php?micro=landing-page',
            'top'
        );
//    for static files
        add_rewrite_rule(
            '^' . $micro_name->slug . '/(.+)',
            'index.php?micro=' . $micro_name->slug . '&static_file=$matches[1]',
            'top'
        );
    }
    flush_rewrite_rules();
});


//    Register the new query parameter.
add_filter('query_vars', function ($query_vars) {
    $query_vars[] = 'micro';
    $query_vars[] = 'static_file';
    return $query_vars;
});




add_action('template_redirect', function () {
    $micro_target = get_query_var('micro', -1);
    $micro_static_file = get_query_var('static_file', -1);

//    echo $micro_target . "   " . $micro_static_file . "    sad------";

    if(array_key_exists('extension', pathinfo($micro_static_file)))
    $extension = pathinfo($micro_static_file)['extension'] ;
    $static_file_path = plugin_dir_path(__FILE__) . 'micros' . DIRECTORY_SEPARATOR . $micro_target . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . $micro_static_file;

    if(!is_file($static_file_path)) {
        http_response_code(404);
//        exit();
    }

    error_log($static_file_path);

    if($micro_static_file !== -1){
        if($extension === 'css')
            header('Content-Type: text/css');
        elseif ($extension === 'js')
            header('Content-Type: application/javascript');
        elseif ($extension === 'jpg' || $extension === 'jpeg')
            header('Content-Type: image/jpeg');
        elseif ($extension === 'png')
            header('Content-Type: image/png');
        elseif ($extension === 'webp') {
            header('Content-Type: image/webp');
        }
        readfile($static_file_path);
        exit();
    }

    if($micro_target === -1)
        return;
//    echo 'ter   ' . $micro_target . '    dsf';
    $upload_directory_file = plugin_dir_path(__FILE__) . 'micros/' . $micro_target . '/build/index.html';

    include $upload_directory_file;
//    include plugin_dir_path(__FILE__) . 'micros/test123/style.css';

    exit();
});

register_activation_hook(__FILE__, 'micro_deploy_initialize_db');

function micro_deploy_initialize_db() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'microdeploy_micros';
    $charset_collate = $wpdb->get_charset_collate();

    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name)
        return;

    $query = "
      CREATE TABLE " . $table_name . "(
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          slug varchar(255) NOT NULL,
          path varchar(255) NOT NULL,
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (id)
      ); 
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $logs = dbDelta($query);
    error_log('logs ' . print_r($logs, true));
}
micro_deploy_initialize_db();

?>


