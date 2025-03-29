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
    error_log('Checking nonce1: ' . print_r($data, true));
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_nonces';

    if(!key_exists('nonce', $data)) {
        status_header(401);
        return false;
    }
    $nonce = $data['nonce'];
    error_log('Checking nonce2: ' . print_r($nonce, true));
    $nonce = micro_deploy_decrypt_key($nonce);
    error_log('Checking nonce3: ' . print_r($nonce, true));
    if($nonce === false) {
        status_header(401);
        return false;
    }
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
//micro_deploy_generate_keys(null, true);
function micro_deploy_generate_keys($base_path = null, $with_save = true) {
//    TODO: error handling!
    $key_configuration = [
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ];
    $pkey_resource = openssl_pkey_new($key_configuration);

    $private_key_string = '';
    openssl_pkey_export($pkey_resource, $private_key_string);

    $key_details = openssl_pkey_get_details($pkey_resource);
    $public_key_string = $key_details['key'];

    if($with_save){
        if($base_path === null) {
            $segments = explode('scripts', plugin_dir_path(__FILE__));
            $base_path = $segments[0] . 'keys' . DIRECTORY_SEPARATOR;
            if(!is_dir($base_path))
                mkdir($base_path, 0755, true);
        }

        $private_key_path = $base_path . 'private_key.pem';
        $public_key_path = $base_path . 'public_key.pem';

        error_log('Base path: ' . $base_path . '   ' . $private_key_string);
        file_put_contents($private_key_path, $private_key_string);
        file_put_contents($public_key_path, $public_key_string);

        global $wpdb;
        $table_name = $wpdb->prefix . 'microdeploy_keys';
        $data = [
            'new_record_value' => [
                'private_key_path' => $private_key_path,
                'public_key_path' => $public_key_path
            ],
        ];
        insert_db($table_name, $data, false);
    }

    return [
        'private_key' => $private_key_string,
        'public_key' => $public_key_string
    ];
}

function micro_deploy_get_key_local_path($type = 'private') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_keys';
    $results = $wpdb->get_results("SELECT * FROM $table_name");
    if(count($results) === 0)
        return false;

    if($type === 'private')
        return $results[0]->private_key_path;
    else
        return $results[0]->public_key_path;
}
function micro_deploy_read_keys(){

    $private_key_path = micro_deploy_get_key_local_path('private');
    $public_key_path = micro_deploy_get_key_local_path('public');

    $private_key_string = file_get_contents($private_key_path);
    $public_key_string = file_get_contents($public_key_path);

    return [
        'private_key_path' => $private_key_string,
        'public_key_path' => $public_key_string
    ];
}
function micro_deploy_read_key_from_file($key_path){
    if(!file_exists($key_path))
        return false;
    $key_string = file_get_contents($key_path);
    return $key_string;
}
function micro_deploy_encrypt_key($data) {
    $public_key_string = micro_deploy_read_key_from_file(micro_deploy_get_key_local_path('public'));
    $public_key = openssl_pkey_get_public($public_key_string);
    if($public_key === false)
        return false;
    $encrypted = '';
    $result = openssl_public_encrypt($data, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING);
    if($result === false)
        return false;
    return base64_encode($encrypted);
}
error_log('Encrypting: ' . micro_deploy_encrypt_key('test'));
error_log('Decrypting: ' . micro_deploy_decrypt_key(micro_deploy_encrypt_key('test')));
function micro_deploy_decrypt_key($data) {
    $private_key_string = micro_deploy_read_key_from_file(micro_deploy_get_key_local_path('private'));
    error_log('Private key: ' . $private_key_string);
    $private_key = openssl_pkey_get_private($private_key_string);
    if($private_key === false)
        return false;
    $decrypted = '';
    error_log('Data: ' . $data);
    $result = openssl_private_decrypt(base64_decode($data), $decrypted, $private_key,OPENSSL_PKCS1_OAEP_PADDING);
    error_log('Decrypted: ' . $decrypted);
    if($result === false) {
        error_log('Decryption failed: ' . openssl_error_string());

        return false;
    }
    return $decrypted;
}