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


add_action('init', function () {
    add_rewrite_rule(
        '^test/?$',
        'index.php?micro=$matches[1]',
        'top'
    );
//    for static files
    add_rewrite_rule(
        '^test/(.*)',
        'index.php?static_file=$matches[1]',
        'top'
    );
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

    if(array_key_exists('extension', pathinfo($micro_static_file)))
    $extension = pathinfo($micro_static_file)['extension'] ;
    if($micro_static_file !== -1){
        if($extension === 'css')
            header('Content-Type: text/css');
        elseif ($extension === 'js')
            header('Content-Type: application/javascript');
        include plugin_dir_path(__FILE__) . 'micros/test123/build/' . $micro_static_file;
        exit();
    }

    if($micro_target === -1)
        return;

    $upload_directory_file = plugin_dir_path(__FILE__) . 'micros/test123/build/index.html';

    include $upload_directory_file;
//    include plugin_dir_path(__FILE__) . 'micros/test123/style.css';

    exit();
});


?>


