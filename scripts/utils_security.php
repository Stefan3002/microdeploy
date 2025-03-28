<?php


function micro_deploy_origin_check(){
//    First level of defense, but origin headers can be forged!
    $allowed_origin = [
        get_site_url(),
    ];
    if(empty($_SERVER['HTTP_ORIGIN']) || !in_array($_SERVER['HTTP_ORIGIN'], $allowed_origin)){
        header('HTTP/1.0 403 Forbidden');
        return false;
    }
    return true;
}

function micro_deploy_generate_new_nonce(){
//    Check if insert succeded!
    global $wpdb;
    try {
        $nonce = wp_create_nonce('micro_deploy_nonce' . time());
        $table_name = $wpdb->prefix . 'microdeploy_nonces';
        $data = [
            'value_name' => 'nonce',
            'new_record_value' => [
                'nonce' => $nonce
            ],
//        'value' => [
//            'type' => "static_serve",
//            'path' => $static_file_path_copy
//        ]
        ];
        insert_db($table_name, $data, false);
        return $nonce;
    }catch (Exception $e){
        status_header(500);
        dispatch_error('Could not generate nonce!');
        exit('Could not generate nonce!');
    }
}

function micro_deploy_check_nonce($data){
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_nonces';

    if(!key_exists('nonce', $data)) {
        status_header(401);
        return false;
    }
    $nonce = $data['nonce'];
    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE nonce = '$nonce'");
    if(count($results) === 0) {
        status_header(401);
        return false;
    }

    return true;
}

function get_nonce_rest(){
    $nonce = micro_deploy_generate_new_nonce();
    return new WP_REST_RESPONSE([
        'data' => $nonce
    ], 200);
}

function micro_deploy_consume_nonce($nonce){
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_nonces';
    if(!$wpdb->delete($table_name, ['nonce' => $nonce]))
        return false;
    return true;
}

function micro_deploy_check_hash($received_hash, $data){
    unset($data['hash']);
    $computed_hash = micro_deploy_compute_hash($data);
//    error_log('COMPUTED HASH: ' . $computed_hash);
    if($received_hash !== $computed_hash){
        status_header(401);
        return false;
    }
    return true;
}

function micro_deploy_compute_hash($data){
    $nonce = $data['nonce'];
    $date = $data['date'];
    $secret = $nonce . $date;

    $hash = hash_hmac('sha256', json_encode($data), $secret);
    return $hash;
}