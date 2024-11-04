<?php
function remove_dir($dir) {
    if(rmdir($dir)){

    }
    else
        dispatch_error("Could not remove the micro folder that was created but an error was encountered while trying to move the files there");
    return;
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

function insert_db($table_name, $data) {
    global $wpdb;

    $data_name = $data['name'];
    $data_value = $data['data_value'];
    $value_name = $data['value_name'];
    $already_results = $wpdb->get_results("SELECT * FROM $table_name WHERE $data_name = '$data_value'");
    error_log(print_r($already_results, true));
    if(count($already_results) > 0)
        return $wpdb->update($table_name, array(
            $value_name => $data['value_change']
        ), array(
            'id' => $already_results[0]->id
        ));
    else {
        return $wpdb->insert($table_name, $data['value']);
    }
}

function micro_deploy_search_index_html($folder_path){
    error_log("Searching for index.html in " . $folder_path);
    $files = glob($folder_path . DIRECTORY_SEPARATOR . "*");

    foreach($files as $file){
        error_log("Checking " . basename($file));
        if(is_dir($file)) {
            $checked_file = micro_deploy_search_index_html($file);
            if(basename($checked_file) === 'index.html')
                return $checked_file;
        }
        else
            if(basename($file) === 'index.html') {
                error_log("Found index.html in " . $file);
                return $file;
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