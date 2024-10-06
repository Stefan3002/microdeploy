<?php
require_once plugin_dir_path(__FILE__) . 'utils.php';

function add_micro() {

    $micro_name = $_POST['micro-deploy-add-new-micro-name'];
    $micro_slug = $_POST['micro-deploy-add-new-micro-slug'];

// =========================
//  Remove the first slash if existent
    if($micro_slug[0] === '/')
        $micro_slug = trim($micro_slug, "\n/\t ");
// =========================
// =========================
//    Check for WP pages conflicts!
//    I.E: Are there any existing pages with the same slug / name?
    $wp_pages = get_pages();
    foreach ($wp_pages as $wp_page)
        if($wp_page->post_name === $micro_slug) {
            dispatch_error("Existing page or post with the same name!");
            error_log("Existing page or post with the same name!");
            return;
        }
// =========================
    if(!array_key_exists('micro-deploy-add-new-micro-file', $_FILES)){
        dispatch_error('No files uploaded');
        return;
    }
    $upload_directory = ABSPATH . 'wp-content\plugins\microdeploy\micros\\' . $micro_slug;

    $temp_file = $_FILES['micro-deploy-add-new-micro-file']['tmp_name'];
    if(!$temp_file){
        dispatch_error("No files uploaded");
        return;
    }


    if(!is_dir($upload_directory))
        if(mkdir($upload_directory, 0777, true)){

        }
        else{
            dispatch_error("Could not create the micros folder for storing your new micro");
            return;
        }
    $upload_directory_file = $upload_directory . DIRECTORY_SEPARATOR . basename($_FILES['micro-deploy-add-new-micro-file']['name']);
    if(move_uploaded_file($temp_file, $upload_directory_file)) {
        $zip = new ZipArchive;
        if($zip->open($upload_directory_file)){
            $zip->extractTo($upload_directory);
            $zip->close();
            unlink($upload_directory_file);
            dispatch_success('Micro uploaded to server successfully');

//            Add the rewrite rules
            link_micro($upload_directory, $micro_slug, $micro_name);
        }
        else{
            dispatch_error('Could not extract the zip file');
            remove_dir($upload_directory);
            return;
        }
    }
    else{
        dispatch_error("Could not upload the micro files");
        remove_dir($upload_directory);
    }
}

function link_micro($upload_directory_file, $micro_slug, $micro_name) {
    global $wpdb;

    $micro_table_name = $wpdb->prefix . 'microdeploy_micros';

    $data = array(
        'name' => sanitize_text_field($micro_name),
        'slug' => sanitize_text_field($micro_slug),
        'path' => sanitize_text_field($upload_directory_file),
    );

    if(!$wpdb->insert($micro_table_name, $data)){
        error_log("COULD NOT INSERT DATA INTO MICRIOS TABLE");
        dispatch_error('Could not insert data into the table');
        return;
    }
    add_rewrite_rule(
        '^' . $micro_slug . '/?$',
        'index.php?micro=landing-page',
        'top'
    );
//    for static files
    add_rewrite_rule(
        '^' . $micro_slug . '/(.+)',
        'index.php?micro=' . $micro_slug . '&static_file=$matches[1]',
        'top'
    );
    flush_rewrite_rules();
}



