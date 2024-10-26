<?php

function micro_deploy_validate_input($data, $validation_options, $validation_types) {

//    Check for content keys
    foreach($validation_options as $option => $value)
        if($value === true)
        if(key_exists($option, $data) === false)
            return false;

    foreach($validation_types as $option => $type) {
        if (gettype($data[$option]) !== $type)
            return false;
        if($type === 'string'){
            if($data[$option] === null)
                return false;
            if($data[$option] === "")
                return false;
            if(strlen(trim($data[$option])) === 0)
                return false;
        }
    }
    return true;
}


function micro_deploy_sanitize_build_file($file_data) {
    $allowed_file_types = ['application/zip'];

    $file_type = $file_data['type'];

    if(!in_array($file_type, $allowed_file_types))
        return [
            "success" => false,
            "message" => "Invalid file type!"
        ];

//    Check the MIME type
//    $file_info = finfo.open($file_data['tmp_name']);
//    $mime_type = finfo.file($file_info);
//
//    error_log("MIME TYPE: " . $mime_type);

//    Check the size
    $file_size = $file_data['size'];
    error_log('FILE SIZE: ' . $file_size . ' ' . $GLOBALS['micro_deploy_max_upload']);
    if($file_size > (int) $GLOBALS['micro_deploy_max_upload'])
        return [
            "success" => false,
            "message" => "File size is too large!"
        ];

    return [
        "success" => true
    ];
}


function micro_deploy_validate_string_is_numeric($string) {
    $string = trim($string);

    for($i = 0; $i < strlen($string); $i++)
        if(!is_numeric($string[$i])) {
            dispatch_error("Invalid size. It contains other characters than numbers.");
            return false;
        }
    return true;
}