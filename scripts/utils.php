<?php
function remove_dir($dir) {
    try {
        if(!is_dir($dir))
            throw new Exception("$dir is not a directory");

        // Careful with directories that are not empty! It will crash for horizontal split if there are no 3 files required!
        if(count(scandir($dir)) === 2) {
            if (rmdir($dir)) {

            } else
                throw new Exception("Could not remove the micro folder that was created but an error was encountered while trying to move the files there");
        }
        else
            micro_deploy_remove_full_folder($dir);
    }catch (Exception $e){
        error_log($e);
        dispatch_error("Could not remove the micro folder.");
        return;
    }


}

function micro_deploy_remove_folder($target_dir){
    if(!is_dir($target_dir))
        throw new Exception($target_dir . "is not a directory!");

    $files = glob($target_dir . DIRECTORY_SEPARATOR . "*");

    foreach($files as $file)
        if(is_dir($file)) {
            micro_deploy_remove_folder($file);
//            After recursion gets back, you should have an empty folder here!
            rmdir($file);
        }
        else
            unlink($file);
}


function get_build_folder($path) {
    if(is_dir($path . 'build'))
        return $path . 'build';
    elseif (is_dir($path . 'dist'))
        return $path . 'dist';
    elseif (is_dir($path . 'public'))
        return $path . 'public';
    elseif (is_dir($path . '_site'))
        return $path . '_site';
    elseif (is_dir($path . '.next'))
        return $path . '.next';
    elseif (is_dir($path . '.nuxt'))
        return $path . '.nuxt';
    else{
        dispatch_error("Could not determine build path");
        error_log("Could not determine build path");
        throw new Exception("Could not determine build path");
    }
}

function micro_deploy_log_intrusion($message) {
//    Check if the custom log file exists
    if(!is_dir(plugin_dir_path(__FILE__) . 'logs'))
        if(!mkdir(plugin_dir_path(__FILE__) . 'logs')){
            error_log("Could not create logs directory");
            return;
        }
    $error_log_file = plugin_dir_path(__FILE__) . 'logs' . DIRECTORY_SEPARATOR . 'intrusions.log';

    $fd = fopen($error_log_file, "a");
    if(!$fd){
        error_log("Could not open intrusions log file");
        return;
    }

    fwrite($fd, date('Y-m-d H:i:s = '));
    fwrite($fd, $message);
    fwrite($fd, "\n");

    error_log($message);
    fclose($fd);
}

function micro_deploy_sanitize_atomic_data($value){
    $sanitized = $value;
    if(gettype($value) === "string")
        $sanitized = sanitize_text_field($value);

    if($sanitized !== $value)
        error_log("Is this XSS??? " . "   " . $value);
    return $sanitized === $value;
}

function micro_deploy_traverse_array($array) {
    foreach($array as $array_value) {
        $type = gettype($array_value);
        if ($type === "object") {
            if (!micro_deploy_sanitize_json($array_value))
                return false;
        }
        else
            if($type === "array") {
                if (!micro_deploy_traverse_array($array_value))
                    return false;
            }
            else
                if($type === "boolean" || $type === "integer" || $type === "double" || $type === "string")
                    if(!micro_deploy_sanitize_atomic_data($array_value))
                        return false;
    }
    return true;
}
function micro_deploy_sanitize_json($json){
    foreach ($json as $key => $value){
        $type = gettype($value);
        if($type === "boolean" || $type === "integer" || $type === "double" || $type === "string") {
            if (!micro_deploy_sanitize_atomic_data($value))
                return false;
        }
        else
            if($type === "object") {
                if (!micro_deploy_sanitize_json($value))
                    return false;
            }
            else
                if($type === "array")
                    if(!micro_deploy_traverse_array($value))
                        return false;

    }
    return true;
}
function insert_db_wrapper($table_name, $data_value, $size, $global_name, $form_trigger_name = null, callable $callback = null)
{
    global $wpdb;
    $micro_table_name = $wpdb->prefix . $table_name;

    if ($form_trigger_name == null || isset($_POST[$form_trigger_name])) {

        $already_results = $wpdb->get_results("SELECT * FROM $micro_table_name WHERE name = '$data_value' AND value = '$size'");
        error_log(print_r($already_results, true));
        if(count($already_results) > 0) {
            dispatch_success("Settings already saved.");
            return;
        }
        if (!insert_db($micro_table_name, array(
            'name' => 'name',
            'data_value' => $data_value,
            'value_name' => 'value',
            'value_change' => $size,
            'value' => $size,
            'new_record_value' => array(
                'name' => $data_value,
                'value' => $size
            )
        )))
            dispatch_error("Could not save the settings.");
        else {
            if($size === 'true')
                $size = true;
            elseif($size === 'false')
                $size = false;
            $GLOBALS[$global_name] = $size;
            if($callback !== null)
                $callback();
            dispatch_success("Settings saved.");
        }
    }
}
function insert_db($table_name, $data, $with_update = true) {
    global $wpdb;
    try {

        if ($with_update) {
            $data_name = $data['name'];
            $data_value = $data['data_value'];
            $value_name = $data['value_name'];
            $already_results = $wpdb->get_results("SELECT * FROM $table_name WHERE $data_name = '$data_value'");
//    error_log(print_r($already_results, true));
            if (count($already_results) > 0) {
                if(is_array($value_name)) {
//                    TODO: check if it fails for each update itself
                    foreach ($value_name as $key => $value)
                        $wpdb->update($table_name, array(
                            $key => $value
                        ), array(
                            'id' => $already_results[0]->id
                        ));
                    return true;
                }
                else
                    return $wpdb->update($table_name, array(
                        $value_name => $data['value_change']
                    ), array(
                        'id' => $already_results[0]->id
                    ));
            }
            else
                return $wpdb->insert($table_name, $data['new_record_value']);
        } else
            return $wpdb->insert($table_name, $data['new_record_value']);

    } catch (Exception $e) {
        error_log($e);
        dispatch_error("Could not insert in the DB.");
        return false;
    }
}

function micro_deploy_search_index_html($folder_path, $target_name = 'index.html'){
//    error_log("Searching for index.html in " . $folder_path);
    $files = glob($folder_path . DIRECTORY_SEPARATOR . "*");

    foreach($files as $file){
        error_log("Checking " . basename($file));
        if(is_dir($file)) {
            $checked_file = micro_deploy_search_index_html($file, $target_name);
            if(basename($checked_file) === $target_name)
                return $checked_file;
        }
        else
            if(basename($file) === $target_name) {
                error_log("Found index.html in " . $file);
                return $file;
            }
    }
    return false;
}

function micro_deploy_search_by_extension($folder_path, $extension_target){
//    error_log("Searching for index.html in " . $folder_path);
    $files = glob($folder_path . DIRECTORY_SEPARATOR . "*");

    foreach($files as $file){
//        error_log("Checking " . basename($file));
        if(is_dir($file)) {
            $checked_file = micro_deploy_search_by_extension($file, $extension_target);
            $basename = basename($checked_file);
            $extension = pathinfo($basename, PATHINFO_EXTENSION);
            if($extension === $extension_target)
                return $checked_file;
        }
        else {
            $basename = basename($file);
            $extension = pathinfo($basename, PATHINFO_EXTENSION);
            if ($extension === $extension_target) {
//                error_log("Found index.html in " . $file);
                return $file;
            }
        }
    }
    return false;
}



function micro_deploy_handle_regex_errors($error_code, $updated_contents, $contents){
    if($error_code === 2)
        dispatch_error("Maximum backtrack limit reached when parsing.");
    if($error_code === 3)
        dispatch_error("Maximum recursion limit reached when parsing.");
//                If there were errors, just keep the original content
    if($error_code !== 0)
        return $contents;
    else
        return $updated_contents;
}
function micro_deploy_check_db_table($table_name){
    global $wpdb;
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}

function check_horizontal_files($folder_path) {
    error_log(count(scandir($folder_path)));
    if(count(scandir($folder_path)) < 5) {
        dispatch_error("The micro folder does not contain the required number of files.");
        return false;
    }

    $html_found = False;
    $css_found = False;
    $js_found = False;
    $files = glob($folder_path . DIRECTORY_SEPARATOR . "*");
    foreach ($files as $file) {
        $file_name = basename($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if($extension === 'html')
            $html_found = True;
        elseif($extension === 'css')
            $css_found = True;
        elseif($extension === 'js')
            $js_found = True;
    }
    return $html_found && $css_found && $js_found;
}

function micro_deploy_minify_horizontal_split($micro_slug, $upload_directory) {
    try {
//    $html_file = micro_deploy_search_by_extension($upload_directory, 'html');
        $css_file = micro_deploy_search_by_extension($upload_directory, 'css');
        $js_file = micro_deploy_search_by_extension($upload_directory, 'js');
        error_log($css_file);
//    Get the minifier
        $css_minifier = new MatthiasMullie\Minify\CSS($css_file);
        $js_minifier = new MatthiasMullie\Minify\JS($js_file);

        $css_segments = explode($micro_slug, $css_file);

        $css_minified_path = $css_segments[0] . $micro_slug . DIRECTORY_SEPARATOR . 'prod.min.css';
        $js_minified_path = $css_segments[0] . $micro_slug . DIRECTORY_SEPARATOR . 'prod.min.js';

        $css_minifier->minify($css_minified_path);
        $js_minifier->minify($js_minified_path);
    }
    catch (Exception $e){
        error_log($e);
        dispatch_error("Could not minify the files.");
    }

}
function micro_deploy_remove_full_folder($dir){
    try {
        $files = glob($dir . DIRECTORY_SEPARATOR . "*");

        foreach($files as $file)
            if(is_dir($file))
                micro_deploy_remove_full_folder($file);
            elseif (is_file($file))
                unlink($file);
        rmdir($dir);
    }catch (Exception $e){
        error_log($e);
        dispatch_error("Could not remove the full micro folder.");
    }
}

function micro_deploy_search_media_in_wp($target){
    $search_path = ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads';
    error_log("Searching for media in WP" . $search_path);
    $found_file = micro_deploy_search_index_html($search_path, $target);
    if(micro_deploy_search_index_html($search_path, $target))
        return $found_file;
    else
        return false;
}

function micro_deploy_local_path_to_url($local_path, $marker='wp-content') {
    $segments = explode($marker, $local_path);
    $url = get_site_url() . DIRECTORY_SEPARATOR . $marker . $segments[1];
    return $url;
}

function micro_deploy_move_file_local($micro_media_file_path, $uploads_path) {
    $segments = explode(DIRECTORY_SEPARATOR, $micro_media_file_path);
    $file_name = $segments[count($segments) - 1];
    $destination_path = $uploads_path . DIRECTORY_SEPARATOR . $file_name;
    if(rename($micro_media_file_path, $destination_path))
        return $destination_path;
    else
        return false;
}

function micro_deploy_add_media_to_db($final_path, $media_name, $media_extension) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';
    try {
        $wpdb->query('START TRANSACTION');
        $result = $wpdb->insert($table_name, array(
            'post_author' => 1,
            'post_date' => date('Y-m-d H:i:s'),
            'post_date_gmt' => date('Y-m-d H:i:s'),
            'post_title' => $media_name,
            'post_status' => 'inherit',
            'post_name' => $media_name,
            'post_modified' => date('Y-m-d H:i:s'),
            'post_modified_gmt' => date('Y-m-d H:i:s'),
            'post_type' => 'attachment',
            'post_mime_type' => 'image/' . $media_extension,
            'guid' => $final_path,
        ));

        if(!$result)
            throw new Exception("Could not add media to the DB.");

        $table_name2 = $wpdb->prefix . 'postmeta';
        $segments = explode('uploads' . DIRECTORY_SEPARATOR, $final_path);

        $result = $wpdb->insert($table_name2, array(
            'post_id' => $wpdb->insert_id,
            'meta_key' => '_wp_attached_file',
            'meta_value' => $segments[1],
        ));

        if(!$result)
            throw new Exception("Could not add media to the DB.");

        $wpdb->query('COMMIT');
    }catch (Exception $e){
        $wpdb->query('ROLLBACK');
        error_log($e);
        dispatch_error("Could not add media to the DB.");
    }

}