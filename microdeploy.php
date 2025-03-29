<?php
/*
Plugin Name: Micro Deploy
Description: Deploys micro frontends to Wordpress
Version: 1.0
Author: Ștefan Secrieru
*/
//TODO: implement key signing or remove it from the paper!
require_once(plugin_dir_path(__FILE__) . 'scripts/admin-dashboard.php');
require_once(plugin_dir_path(__FILE__) . 'scripts/micro.php');
require_once(plugin_dir_path(__FILE__) . 'scripts/state-manager.php');
require_once(plugin_dir_path(__FILE__) . 'scripts/page-generators.php');
require_once(plugin_dir_path(__FILE__) . 'scripts/performance.php');
require_once(plugin_dir_path(__FILE__) . 'scripts/utils_security.php');
require_once plugin_dir_path(__FILE__) . 'assets/libraries/matthiasmullie-minifier/autoload.php';


// Hook to add a menu option in the WordPress admin
add_action('admin_menu', function () {
//    add_submenu_page(
//        'my-plugin-main',           // Parent slug (matches the main menu slug)
//        'Settings',                 // Page title
//        'Settings',                 // Submenu title
//        'manage_options',           // Capability
//        'my-plugin-settings',       // Menu slug for the subpage
//        'my_plugin_settings_page'   // Callback function to display content
//    );
    add_menu_page('Micro Deploy', 'Micro Deploy', 'manage_options', 'micro-deploy', 'micro_deploy_generate_admin_page');
    add_submenu_page('micro-deploy', 'Deployment Settings', 'Settings', 'manage_options', 'settings', 'micro_deploy_generate_settings_page');
    add_submenu_page('micro-deploy', 'Deployment Performance', 'Performance', 'manage_options', 'performance', 'micro_deploy_generate_performance_page');
    add_submenu_page('micro-deploy', 'Deployment Errors', 'Errors', 'manage_options', 'errors', 'micro_deploy_generate_errors_page');
    add_submenu_page('micro-deploy', 'About Microdeploy', 'About', 'manage_options', 'about', 'micro_deploy_generate_about_page');
});



add_action('admin_enqueue_scripts', 'micro_deploy_enqueue_admin_styles');

function micro_deploy_enqueue_admin_styles() {
    wp_enqueue_style('micro-deploy-admin-styles', plugins_url('assets/css/admin-style.css', __FILE__));
    wp_enqueue_style('micro-deploy-general-styles', plugins_url('assets/css/general.css', __FILE__));

//    wp_enqueue_script('react', plugins_url('micros/test123/build/static/js/main.15dc85d0.js', __FILE__));
}

add_action('init', function () {
    global $wpdb;
    $micro_table_name = $wpdb->prefix . 'microdeploy_micros';
    $results = $wpdb->get_results("SELECT slug, type FROM $micro_table_name");


    error_log(print_r($results, true));
    foreach ($results as $micro_name){
//        Skip if horizontal!
        if($micro_name->type === 'horizontal')
            continue;
        add_rewrite_rule(
            '^' . $micro_name->slug . '/?$',
            'index.php?micro=' . $micro_name->slug,
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


$micro_deploy_allowed_files = ['css', 'js', 'png', 'jpg', 'jpeg', 'html', 'htm', 'svg', 'json', 'ico', 'webp'];


add_action('template_redirect', function () {
    global $micro_deploy_allowed_files;
    global $wpdb;
    $micro_table_name = $wpdb->prefix . 'microdeploy_errors';
    $micro_target = get_query_var('micro', -1);
    $micro_static_file = get_query_var('static_file', -1);


//    echo $micro_target . "   " . $micro_static_file . "    sad------";

// =========================
//    Verify the integrity of the custom query parameters
    if($micro_target === -1)
        return;
// =========================
    if($micro_static_file !== -1) {
        if (array_key_exists('extension', pathinfo($micro_static_file)))
            $extension = pathinfo($micro_static_file)['extension'];
        else
            return;
//        ONLY ALLOW CERTAIN TYPES OF FILES
        if(!in_array($extension, $micro_deploy_allowed_files)){
            micro_deploy_log_intrusion("Forbidden file type attempt to access: " . $extension);
            header("HTTP/1.1 403 Forbidden");
            header("Content-Type: text/plain");
            exit("Forbidden: You do not have permission to access this resource type.");
        }

        $base_allowed_path = realpath(get_build_folder(plugin_dir_path(__FILE__) . 'micros' . DIRECTORY_SEPARATOR . $micro_target . DIRECTORY_SEPARATOR));
        $static_file_path = $base_allowed_path . DIRECTORY_SEPARATOR . $micro_static_file;
        $static_file_path_copy = $static_file_path;
        //MITIGATE DIRECTORY ATTACKS
//        Never return anything that is not in the micro folder already
        $static_file_path = realpath($static_file_path);
//        realpath returns false if the file does not exist!!!!
        error_log('STATIC FILE PATH: ' . $static_file_path);
        if(!$static_file_path){
//          Remember the error in the DB
            error_log('STATIC FILE PATH: ' . $static_file_path . 'll');
            $data = [
                'name' => 'path',
                'data_value' => $static_file_path_copy,
                'value_name' => 'path',
                'value_change' => $static_file_path_copy,
                'new_record_value' => [
                    'type' => "static_serve",
                    'path' => $static_file_path_copy
                    ]
            ];
            insert_db($micro_table_name, $data);
            header("HTTP/1.1 404 Not Found");
            header("Content-Type: text/plain");
            exit("File not found.");
        }
//        Check again to see if the file is in the micro folder
        if(strpos($static_file_path, $base_allowed_path) !== 0){
            micro_deploy_log_intrusion("Forbidden file attempt to access: " . $static_file_path . "\n");
            header("HTTP/1.1 403 Forbidden");
            header("Content-Type: text/plain");
            exit("Forbidden: You do not have permission to access this resource.");
        }

//        If served successfully, remove the error from the DB
        $results = $wpdb->get_results("SELECT * FROM $micro_table_name WHERE path = '$static_file_path_copy'");
        if(count($results) > 0)
            $wpdb->delete($micro_table_name, array(
                'id' => $results[0]->id
            ));


        if ($extension === 'css')
            header('Content-Type: text/css');
        elseif ($extension === 'js')
            header('Content-Type: application/javascript');
        elseif ($extension === 'jpg' || $extension === 'jpeg') {
            header('Content-Type: image/jpeg');
            header('Content-Length: ' . filesize($static_file_path));
        }
        elseif ($extension === 'png') {
            header('Content-Type: image/png');
            header('Content-Length: ' . filesize($static_file_path));
        }
        elseif ($extension === 'webp') {
            header('Content-Type: image/webp');
            header('Content-Length: ' . filesize($static_file_path));
        }
        elseif ($extension === 'json') {
            header('Content-Type: application/json');
            header('Content-Length: ' . filesize($static_file_path));
        }
        elseif ($extension === 'ico') {
            header('Content-Type: image/vnd.microsoft.icon');
            header('Content-Length: ' . filesize($static_file_path));
        }
        elseif ($extension === 'svg') {
            header('Content-Type: image/svg+xml');
            header('Content-Length: ' . filesize($static_file_path));
        }
        elseif ($extension === 'glb') {
            header('Content-Type: model/gltf-binary');
            header('Content-Length: ' . filesize($static_file_path));
        }
        readfile($static_file_path);
        exit();
    }

//Actually search for the index.html!!
//    $upload_directory_file = get_build_folder(plugin_dir_path(__FILE__) . 'micros' . DIRECTORY_SEPARATOR . $micro_target . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
    $upload_directory_file = micro_deploy_search_index_html(get_build_folder(plugin_dir_path(__FILE__) . 'micros' . DIRECTORY_SEPARATOR . $micro_target . DIRECTORY_SEPARATOR));
    include $upload_directory_file;

    exit();
});
add_action('init', 'micro_deploy_load_settings');
register_activation_hook(__FILE__, 'micro_deploy_initialize_db');


function micro_deploy_initialize_db() {
    micro_deploy_load_settings();
    global $wpdb;

    $table_name = $wpdb->prefix . 'microdeploy_micros';
    $charset_collate = $wpdb->get_charset_collate();

    if(!micro_deploy_check_db_table($table_name)) {

        $query = "
      CREATE TABLE " . $table_name . "(
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          name varchar(255) NOT NULL,
          slug varchar(255) NOT NULL,
          tech varchar(255) NOT NULL,
          build varchar(255) NOT NULL,
          path varchar(255) NOT NULL,
          type varchar(50) NOT NULL DEFAULT 'vertical',
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (id)
      ); 
    ";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $logs = dbDelta($query);
        error_log('logs ' . print_r($logs, true));
    }
//    Errors table
    $table_name = $wpdb->prefix . 'microdeploy_errors';
    $charset_collate = $wpdb->get_charset_collate();

    if(!($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name)) {

        $query = "
      CREATE TABLE " . $table_name . "(
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          type varchar(255) NOT NULL,
          path varchar(255) NOT NULL,
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (id)
      ); 
    ";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $logs = dbDelta($query);
        error_log('logs ' . print_r($logs, true));
    }



//    Settings table

    $table_name = $wpdb->prefix . 'microdeploy_settings';
    $charset_collate = $wpdb->get_charset_collate();

    if(!($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name)) {
        $query = "
      CREATE TABLE " . $table_name . "(
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          name varchar(255) NOT NULL,
          value varchar(255) NOT NULL,
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (id)
      ); 
    ";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $logs = dbDelta($query);
        error_log('logs ' . print_r($logs, true));
    }

//    Performance table
    if($GLOBALS['micro_deploy_enabled_performance'] === true) {
        $table_name = $wpdb->prefix . 'microdeploy_performance';
//        $charset_collate = $wpdb->get_charset_collate();

        if (!($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name)) {
            $query = "
      CREATE TABLE " . $table_name . "(
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          slug varchar(255) NOT NULL,
          dcl int(10) NOT NULL,
          fcp int(10) NOT NULL,
          lcp int(10) NOT NULL,
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (id)
      ); 
    ";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $logs = dbDelta($query);
            error_log('logs ' . print_r($logs, true));
        }
    }

//    Security table!
    if($GLOBALS['micro_deploy_enabled_performance'] === true) {
        $table_name = $wpdb->prefix . 'microdeploy_nonces';
//        $charset_collate = $wpdb->get_charset_collate();

        if (!($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name)) {
            $query = "
      CREATE TABLE " . $table_name . "(
          nonce varchar(255) NOT NULL,
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (nonce)
      ); 
    ";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $logs = dbDelta($query);
            error_log('logs ' . print_r($logs, true));
        }
    }


    //    Security table!
    if($GLOBALS['micro_deploy_enabled_performance'] === true) {
        $table_name = $wpdb->prefix . 'microdeploy_keys';
//        $charset_collate = $wpdb->get_charset_collate();

        if (!($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name)) {
            $query = "
      CREATE TABLE " . $table_name . "(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
          private_key_path varchar(255) NOT NULL,
          public_key_path varchar(255) NOT NULL,
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (id)
      ); 
    ";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $logs = dbDelta($query);
            error_log('logs ' . print_r($logs, true));
        }
    }
}
//micro_deploy_initialize_db();

add_action('init', 'link_micro_shortcodes');

add_action('init', function () {
    add_action('rest_api_init', function () {
        register_rest_route('performance-metrics/v1', "/set-data", array(
            'methods' => "POST",
            'callback' => 'set_data_rest'
        ));
    });
    add_action('rest_api_init', function () {
        register_rest_route('security/v1', "/get-nonce", array(
            'methods' => "GET",
            'callback' => 'get_nonce_rest'
        ));
    });
});
function micro_deploy_register_rest(){
    add_action('rest_api_init', function () {
        register_rest_route('micro-deploy/v1', "/set-data", array(
            'methods' => "POST",
            'callback' => 'micro_deploy_set_data_rest'
        ));
    });

    add_action('rest_api_init', function () {
        register_rest_route('micro-deploy/v1', "/get-data", array(
            'methods' => "POST",
            'callback' => 'micro_deploy_get_data_rest'
        ));
    });

}
function micro_deploy_load_settings(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_settings';
    $results = $wpdb->get_results("SELECT * FROM $table_name");

    $max_upload_found = false;
    $max_backtrack_found = false;
    $enabled_performance_found = false;
    $performance_limit_found = false;
    if(count($results) === 0)
        return;

    foreach($results as $result) {
        if ($result->name === 'state_manager_initialized' && $result->value === 'true') {
            micro_deploy_register_rest();
            micro_deploy_initialize_state_manager(true);
        }
        if($result->name === 'max_upload') {
            $max_upload_found = true;
            $GLOBALS['micro_deploy_max_upload'] = $result->value;
        }
        if($result->name === 'performance_limit') {
            $performance_limit_found = true;
            $GLOBALS['micro_deploy_performance_limit'] = $result->value;
        }
        if($result->name === 'max_backtrack') {
            $max_backtrack_found = true;
            $GLOBALS['micro_deploy_max_backtrack'] = $result->value;
        }
        if($result->name === 'enabled_performance') {
            $enabled_performance_found = true;
            if($result->value === 'true')
                $GLOBALS['micro_deploy_enabled_performance'] = true;
            else
                $GLOBALS['micro_deploy_enabled_performance'] = false;
        }
    }
    if(!$max_upload_found)
        $GLOBALS['micro_deploy_max_upload'] = 10000000;
    if(!$max_backtrack_found)
        $GLOBALS['micro_deploy_max_backtrack'] = 100000;
    if(!$performance_limit_found)
        $GLOBALS['micro_deploy_performance_limit'] = 100;
    if(!$enabled_performance_found)
        $GLOBALS['micro_deploy_enabled_performance'] = false;
}

