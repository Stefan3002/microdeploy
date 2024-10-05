<?php
require_once plugin_dir_path(__FILE__) . 'utils.php';

function add_micro() {

    $micro_name = $_POST['micro-deploy-add-new-micro-name'];

    if(!array_key_exists('micro-deploy-add-new-micro-file', $_FILES)){
        dispatch_error('No files uploaded');
        return;
    }
    $upload_directory = ABSPATH . 'wp-content\plugins\microdeploy\micros\\' . $micro_name;

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
    $upload_directory_file = $upload_directory . '/' . basename($_FILES['micro-deploy-add-new-micro-file']['name']);
    if(move_uploaded_file($temp_file, $upload_directory_file)) {
        $zip = new ZipArchive;
        if($zip->open($upload_directory_file)){
            $zip->extractTo($upload_directory);
            $zip->close();
            unlink($upload_directory_file);
            dispatch_success('Micro uploaded to server successfully');

//            Add the rewrite rules
            link_micro($upload_directory_file);
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


function link_micro($upload_directory_file) {
    echo $upload_directory_file;
}



