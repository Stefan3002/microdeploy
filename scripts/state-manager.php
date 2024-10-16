<?php
function micro_deploy_initialize_state_manager () {
    try {
        if(!extension_loaded('redis'))
            throw new Exception("Redis extension is not loaded");
        $redis = new Redis();
        
    }catch (Exception $e){
        error_log($e);
        dispatch_error("Redis for PHP is not installed. Please contact your hosting provider.");
        return;
    }
}