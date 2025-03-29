<?php
require_once plugin_dir_path(__FILE__) . 'utils.php';
function add_micro($type='vertical') {

    $micro_name = $_POST['micro-deploy-add-new-micro-name'];
    $micro_slug = $_POST['micro-deploy-add-new-micro-slug'];
    $micro_tech = $_POST['micro-deploy-add-new-micro-tech'];
    $micro_build = $_POST['micro-deploy-add-new-micro-build'];

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
//    Also check own database to see if there are other micro-frontends with the same slug already uploaded
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_micros';
    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE slug = '$micro_slug'");
    $existing_path = null;
    if(count($results) > 0) {
//        Allow for update in this case!
        $existing_path = $results[0]->path;
        micro_deploy_remove_full_folder($existing_path);
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

    if(!is_dir($upload_directory))
        if(mkdir($upload_directory, 0755, true)){

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
//            Check for the requested 3 files!
            if($type === 'horizontal')
                if(!check_horizontal_files($upload_directory)){
                    dispatch_error('The horizontal micro does not contain the necessary files!');
                    remove_dir($upload_directory);
                    return;
                }
//            Add the rewrite rules and DB entries
            link_micro($upload_directory, $micro_slug, $micro_name, $micro_tech, $micro_build, $type, $existing_path);
            if($type === 'vertical') {
//            Change the URLS for static serving!
                micro_deploy_adjust_urls_static_serve($upload_directory, $micro_slug, $micro_tech, $micro_build);
//            Check if the performance monitoring is on!
                if ($GLOBALS['micro_deploy_enabled_performance'] === true)
                    add_performance_client_data_to_micros();
            }
            else{
//                micro_deploy_adjust_urls_static_serve($upload_directory, $micro_slug, $micro_tech, $micro_build);
                micro_deploy_minify_horizontal_split($micro_slug, $upload_directory);
                link_micro_shortcode($micro_slug, $upload_directory);
            }

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

function link_micro($upload_directory_file, $micro_slug, $micro_name, $micro_tech, $micro_build, $type='vertical', $existing_path = null) {
    global $wpdb;

    $micro_table_name = $wpdb->prefix . 'microdeploy_micros';

    $data = array(
        'name' => sanitize_text_field($micro_name),
        'slug' => sanitize_text_field($micro_slug),
        'tech' => sanitize_text_field($micro_tech),
        'build' => sanitize_text_field($micro_build),
        'path' => sanitize_text_field($upload_directory_file),
        'type' => sanitize_text_field($type)
    );
    $db_data = [
        'name' => 'slug',
        'data_value' => $micro_slug,
        'new_record_value' => $data,
        'value_name' => $data
    ];
    insert_db($micro_table_name, $db_data, true);
//    if(!$wpdb->insert($micro_table_name, $data)){
//        error_log("COULD NOT INSERT DATA INTO MICRIOS TABLE");
//        dispatch_error('Could not insert data into the table');
//        return;
//    }
    if($type === 'horizontal')
        return;
//    TODO: Does this make sense anymore?
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

function micro_deploy_adjust_urls_static_serve($micro_upload_directory, $micro_slug, $micro_tech = 'vanilla', $micro_build = 'cra') {
    $files = glob($micro_upload_directory . DIRECTORY_SEPARATOR . '*');
    foreach($files as $file) {
        if(is_dir($file))
            micro_deploy_adjust_urls_static_serve($file, $micro_slug, $micro_tech, $micro_build);
        elseif (is_file($file)){

            if(!array_key_exists('extension', pathinfo($file)))
                continue;
            $extension = pathinfo($file)['extension'];

            $extra_slug = '';
//            If the Micro is Angular, also add /browser/
            if($micro_tech === 'angular')
                $extra_slug = '/browser';

            // Verify if there are urls in CSS file
            if($extension === 'css'){
                $contents = file_get_contents($file);
                $pattern = '/url\(\s*[\'"]?(?:[.]{0,2}\/?)([^\/\'"\s]+)\/?([^\'"\s)]*)[\'"]?\s*\)/';
                $updated_contents = preg_replace_callback($pattern, function($matches) use ($micro_slug, $extra_slug) {
//                    Return the same link if it has already been parsed an modified accordingly.
                    if($matches[1] === $micro_slug)
                        return $matches[0];

                    $match = trim( $matches[1] . '/' . $matches[2], "./");
                    $match = '/' . $match;
                    $new_url = micro_deploy_get_slug_url($match, $micro_slug . $extra_slug);
                    return 'url(\'' . $new_url . '\')';

                }, $contents);
                $updated_contents = micro_deploy_handle_regex_errors(preg_last_error(), $updated_contents, $contents);
                file_put_contents($file, $updated_contents);
            }
            elseif($extension === 'html' || $extension === 'htm'){

                $contents = file_get_contents($file);
                $pattern = '/src=\s*[\'"]?(?:[.]{0,2}(?!http)\/?)([^\/\'"\s]+)\/?([^\'"\s]*)[\'"]?\s*/';
                $pattern2 = '/href=\s*[\'"]?(?:[.]{0,2}(?!http)\/?)([^\/\'"\s]+)\/?([^\'"\s]*)[\'"]?\s*/';
                error_log('HTML ' . $micro_tech);
//                This is only needed for angular build where href="/" will not be picked up by the other patterns in Regex
                if($micro_tech === 'angular' && (basename($file) === 'index.html' || basename($file) === 'index.htm')) {
                    $pattern3 = '/<base href=[\"\']\/[\"\']>/';
                    if(preg_match($pattern3, $contents)) {

                        $updated_contents = preg_replace_callback($pattern3, function ($matches) use ($micro_slug) {

                            return '<base href="/' . $micro_slug . '/">';
                        }, $contents);

                        $updated_contents = micro_deploy_handle_regex_errors(preg_last_error(), $updated_contents, $contents);
                    }
                    else{
                        $updated_contents = $contents . ' <base href="/' . $micro_slug . '/">';
                    }
                    file_put_contents($file, $updated_contents);
                }
                $contents = file_get_contents($file);
                $updated_contents = preg_replace_callback($pattern, function($matches) use ($micro_upload_directory, $extra_slug, $micro_slug) {
                    error_log('FILE ' . $matches[0]);
                    // Return the same link if it has already been parsed and modified accordingly.
//                    matches[1] is a capturing group that contains the first part of the URL (between the first two slashes /)
                    if($matches[1] === $micro_slug)
                        return $matches[0];

                    $match = trim($matches[1] . '/' . $matches[2], "./");
                    $match = '/' . $match;
                    $new_url = micro_deploy_get_slug_url($match, $micro_slug . $extra_slug);

//                    Only if media files like images!

                    $name_segments = explode('.', $matches[2]);
                    $media_extension = null;
                    error_log('MEDIA FILE ' . print_r($name_segments, true));
                    if(count($name_segments) > 1)
                        $media_extension = $name_segments[1];
                    $supported_media_types_optimization = ['svg', 'png', 'jpg', 'jpeg', 'webp'];

                    if(in_array($media_extension, $supported_media_types_optimization)) {
//                    matches[2] contains the name of the media file from the src tag
//                    Search for it in the WordPress native media folder
                        $wp_media_path = micro_deploy_search_media_in_wp($matches[2]);
                        $micro_media_file_path = $micro_upload_directory . DIRECTORY_SEPARATOR . $match;

                        error_log('MEDIA PATH ' . $wp_media_path);
                        if ($wp_media_path) {
                            $wp_media_url = micro_deploy_local_path_to_url($wp_media_path);
//                        Remove the file that was already found
                            if (file_exists($micro_media_file_path))
                                unlink($micro_media_file_path);
//                        TODO extend for all types of files, not just html and src
                            return 'src=\'' . $wp_media_url . '\'';
                        } else {
//                        Image was not found already, let's move it from here to there!
//                            First verify that the file actually exists!
                            if (file_exists($micro_media_file_path)) {
                                $uploads_path = ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads';
                                $final_path = micro_deploy_move_file_local($micro_media_file_path, $uploads_path);
//                                Also add to DB!
                                $wp_media_url = micro_deploy_local_path_to_url($final_path);
                                micro_deploy_add_media_to_db($wp_media_url, $matches[2], $media_extension);

                                return 'src=\'' . $wp_media_url . '\'';
                            }
                        }
                    }
                    return 'src=\'' . $new_url . '\'';

                }, $contents);

                $updated_contents = micro_deploy_handle_regex_errors(preg_last_error(), $updated_contents, $contents);
                file_put_contents($file, $updated_contents);

                $contents = file_get_contents($file);
                $updated_contents = preg_replace_callback($pattern2, function($matches) use ($extra_slug, $micro_slug) {
                    //                    Return the same link if it has already been parsed an modified accordingly.
                    if($matches[1] === $micro_slug)
                        return $matches[0];

                    $match = trim('/' . $matches[1] . '/' . $matches[2], "./");
                    $match = '/' . $match;

                    $new_url = micro_deploy_get_slug_url($match, $micro_slug . $extra_slug);
//                    error_log('ALOHAA HTML ' . $matches[1]);
                    return 'href=\'' . $new_url . '\'';

                }, $contents);
                $updated_contents = micro_deploy_handle_regex_errors(preg_last_error(), $updated_contents, $contents);
                file_put_contents($file, $updated_contents);

            }
            elseif($extension === 'js'){
                $contents = file_get_contents($file);
                ini_set('pcre.backtrack_limit', $GLOBALS['micro_deploy_max_backtrack']);
                $pattern = '/[\'"](?:[.]{0,2}\/?)([^\/\'"\s]+)\/?([^\'"\s]*\.(svg|png|jpg|jpeg|webp))[\'"]\s*/';
                $updated_contents = preg_replace_callback($pattern, function($matches) use ($micro_tech, $micro_build, $extra_slug, $micro_slug) {
//                    Return the same link if it has already been parsed an modified accordingly.
//                    error_log("ALOHA! " . $matches[0] . ' ' . $matches[1] . ' ' . $matches[2]);
                    if($matches[1] === $micro_slug)
                        return $matches[0];

                    $match = trim('/' . $matches[1] . '/' . $matches[2], "./");
                    $match = '/' . $match;


                    $leading_slash = false;
//                    If VITE or Angular!
                    if((($micro_tech === 'react' || $micro_tech === 'vue') && $micro_build === 'vite') || $micro_tech === 'angular')
                        $leading_slash = true;

                    $new_url = "\"" . micro_deploy_get_slug_url($match, $micro_slug . $extra_slug, $leading_slash) . "\"";

                    return $new_url;
                }, $contents);
                $updated_contents = micro_deploy_handle_regex_errors(preg_last_error(), $updated_contents, $contents);
                file_put_contents($file, $updated_contents);
            }
        }
    }
    dispatch_success("Successfully modified the links.");
}

function link_micro_shortcodes() {
//    Take the shortcodes from the DB
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_micros';
    $micros = $wpdb->get_results("SELECT * FROM $table_name WHERE type='horizontal'");
    if(count($micros) === 0)
        return;
    foreach($micros as $micro){
        link_micro_shortcode($micro->slug, $micro->path);
    }
}
function link_micro_shortcode($micro_shortcode, $micro_path){
    add_shortcode($micro_shortcode, function () use ($micro_shortcode, $micro_path) {return inject_micro_shortcode($micro_shortcode, $micro_path);});
}

function inject_micro_shortcode($micro_slug, $micro_path){
    $index_path = micro_deploy_search_index_html($micro_path);
    $css_path = micro_deploy_search_by_extension($micro_path, 'css');
    $js_path = micro_deploy_search_by_extension($micro_path, 'js');

    if (!$css_path || !$js_path || !$index_path) {
        dispatch_error("The micro does not contain the necessary files!");
        return;
    }

//    error_log('INJECTING MICRO ' . $index_path);

    $contents = file_get_contents($index_path);
//    error_log('INJECTING MICRO ' . $contents);

    $target_pattern_start = '<!-- START MICRODEPLOY HORIZONTAL SPLIT -->';
    $target_pattern_end = '<!-- END MICRODEPLOY HORIZONTAL SPLIT -->';

    $pattern_already_exists = '/<!-- START MICRODEPLOY HORIZONTAL SPLIT -->([\s\S]*?)<!-- END MICRODEPLOY HORIZONTAL SPLIT -->/';

    if (!preg_match_all($pattern_already_exists, $contents, $matches)) {
//        dispatch_error("The micro did not contain the delimiting markers!");
        return 'No markers found';
    }

    $output = '';
    foreach($matches[0] as $match)
        $output .= $match;

    $plugin_name = 'microdeploy';
    $segments = explode($plugin_name, plugin_dir_url(__FILE__));
//    $local_segments = explode($micro_slug, $css_path);
//    $local_segments_js = explode($micro_slug, $js_path);
    $css_path_url = $segments[0] . $plugin_name . DIRECTORY_SEPARATOR . 'micros' . DIRECTORY_SEPARATOR . $micro_slug . DIRECTORY_SEPARATOR . 'prod.min.css';
    $js_path_url = $segments[0] . $plugin_name . DIRECTORY_SEPARATOR . 'micros' . DIRECTORY_SEPARATOR . $micro_slug . DIRECTORY_SEPARATOR . 'prod.min.js';
//    Add CSS and JS files
//    error_log('CSS PATH ' . $p);
//    error_log('CSS PATH ' . $css_path);
    wp_enqueue_style('micro-deploy-shortcode-style-' . $micro_slug, $css_path_url);
    wp_enqueue_script('my-custom-script-' . $micro_slug, $js_path_url, array('jquery'), '1.0.0', true);

    return $output;
}