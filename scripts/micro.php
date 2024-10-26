<?php
require_once plugin_dir_path(__FILE__) . 'utils.php';

function add_micro() {

    $micro_name = $_POST['micro-deploy-add-new-micro-name'];
    $micro_slug = $_POST['micro-deploy-add-new-micro-slug'];

//    SANITIZE the input file!
    $file_validation = micro_deploy_sanitize_build_file($_FILES['micro-deploy-add-new-micro-file']);
    if(!$file_validation['success']){
        dispatch_error($file_validation['message']);
        error_log($file_validation['message']);
        return;
    }
    error_log('FILE! ' . print_r($_FILES['micro-deploy-add-new-micro-file'], true));
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
    $upload_directory = ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'microdeploy' . DIRECTORY_SEPARATOR . 'micros' . DIRECTORY_SEPARATOR . $micro_slug;

    $temp_file = $_FILES['micro-deploy-add-new-micro-file']['tmp_name'];
    if(!$temp_file){
        dispatch_error("No files uploaded");
        return;
    }

//CHANGE THE TRIPLE SEVEN!
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
//            Add the rewrite rules
            link_micro($upload_directory, $micro_slug, $micro_name);
//            Change the URLS for static serving!
            micro_deploy_adjust_urls_static_serve($upload_directory, $micro_slug);
            dispatch_success('Micro uploaded to server successfully');
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

function micro_deploy_get_slug_url($relative_path, $micro_slug, $root = true){
    if($root)
        return '/' . $micro_slug . $relative_path;
    else
        return $micro_slug . $relative_path;
}

function micro_deploy_adjust_urls_static_serve($micro_upload_directory, $micro_slug) {

    $files = glob($micro_upload_directory . DIRECTORY_SEPARATOR . '*');
    foreach($files as $file) {
        if(is_dir($file))
            micro_deploy_adjust_urls_static_serve($file, $micro_slug);
        elseif (is_file($file)){

            if(!array_key_exists('extension', pathinfo($file)))
                continue;
            $extension = pathinfo($file)['extension'];
            //                Verify if there are urls in CSS file
            if($extension === 'css'){
                $contents = file_get_contents($file);
                $pattern = '/url\(\s*[\'"]?(?:[.]{0,2}\/?)([^\/\'"\s]+)\/?([^\'"\s)]*)[\'"]?\s*\)/';
                $updated_contents = preg_replace_callback($pattern, function($matches) use ($micro_slug) {
//                    Return the same link if it has already been parsed an modified accordingly.
                    if($matches[1] === $micro_slug)
                        return $matches[0];

                    $match = trim('/' . $matches[1] . '/' . $matches[2], ".");
                    $new_url = micro_deploy_get_slug_url($match, $micro_slug);
                    error_log('CSSS ' . 'url(\'' . $new_url . '\')' . '  == ' . $matches[1] . "  ---  " . $matches[2]);

                    return 'url(\'' . $new_url . '\')';

                }, $contents);
                file_put_contents($file, $updated_contents);
            }
            elseif($extension === 'html' || $extension === 'htm'){
                $contents = file_get_contents($file);
                $pattern = '/src=\s*[\'"]?(?:[.]{0,2}(?!http)\/?)([^\/\'"\s]+)\/?([^\'"\s]*)[\'"]?\s*/';
                $pattern2 = '/href=\s*[\'"]?(?:[.]{0,2}(?!http)\/?)([^\/\'"\s]+)\/?([^\'"\s]*)[\'"]?\s*/';

                $updated_contents = preg_replace_callback($pattern, function($matches) use ($micro_slug) {
                    error_log('FILE ' . $matches[0]);
                    // Return the same link if it has already been parsed an modified accordingly.
//                    matches[1] is a capturing group that contains the first part of the URL (between the first two slashes /)
                    if($matches[1] === $micro_slug)
                        return $matches[0];

                    $match = trim('/' . $matches[1] . '/' . $matches[2], ".");
                    $new_url = micro_deploy_get_slug_url($match, $micro_slug);
                    error_log('HTML ' . 'src=\'' . $new_url . '\'');
                    return 'src=\'' . $new_url . '\'';

                }, $contents);


                file_put_contents($file, $updated_contents);
                $contents = file_get_contents($file);
                $updated_contents = preg_replace_callback($pattern2, function($matches) use ($micro_slug) {
                    //                    Return the same link if it has already been parsed an modified accordingly.
                    if($matches[1] === $micro_slug)
                        return $matches[0];

                    $match = trim('/' . $matches[1] . '/' . $matches[2], ".");
                    $new_url = micro_deploy_get_slug_url($match, $micro_slug);
                    return 'href=\'' . $new_url . '\'';

                }, $contents);
                file_put_contents($file, $updated_contents);

            }
            elseif($extension === 'js'){
                $contents = file_get_contents($file);
                $pattern2 = '/[\'"](?:[.]{0,2}\/?)([^\/\'"\s]+)\/?([^\'"\s]*\.png)[\'"]\s*/';
                $pattern = '/[\'"](?:[.]{0,2}\/?)([^\/\'"\s]+)\/?([^\'"\s]*\.(svg|png|jpg|jpeg))[\'"]\s*/';
                error_log('JS file ' . $file);
                $updated_contents = preg_replace_callback($pattern, function($matches) use ($micro_slug) {
                    error_log('JS match ' . print_r($matches, true));
//                    Return the same link if it has already been parsed an modified accordingly.
                    if($matches[1] === $micro_slug)
                        return $matches[0];

                    $match = trim('/' . $matches[1] . '/' . $matches[2], ".");
                    $new_url = "\"" . micro_deploy_get_slug_url($match, $micro_slug, false) . "\"";
//                    return $new_url;
                    return $new_url;
                }, $contents);
                file_put_contents($file, $updated_contents);
            }
        }
    }
    dispatch_success("Successfully modified the links.");
}
