<?php

function micro_deploy_validate_input($data, $validation_options, $validation_types) {
    error_log("VALIDATING INPUT");
    error_log(print_r($data, true));

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