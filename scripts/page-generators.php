<?php

function micro_deploy_generate_settings_page(){
    ?>
<div class='micro_deploy_admin-page-wrapper'>
    <div class="micro_deploy_admin-page-header">
        <h2 class="micro_deploy_title">Micro Deploy</h2>
        <p>by Ștefan Secrieru</p>
    </div>
    <div class="micro-deploy-admin-page-content">
        <div class="micro-deploy-admin-page-new-micro">
            <h2>Max file upload size</h2>
            <p>───── ⋆⋅☆⋅⋆ ─────</p>
            <form action="" method="post">
                <label for="micro-deploy-settings-max-file-upload">Max file upload size (in bytes)</label>
                <p>Default: 10000000 bytes = 10MB</p>
                <input type="text" id="micro-deploy-settings-max-file-upload" required value="<?php _e($GLOBALS["micro_deploy_max_upload"]) ?> " placeholder="Max file upload" name="micro-deploy-settings-max-file-upload">
                <button type="submit">Save</button>
            </form>
            <form class="micro-deploy-form-marginated-top" action="" method="POST">
                <input hidden name="micro-deploy-settings-reset-max-upload">
                <button type="submit" class="micro-deploy-delete-button">Reset</button>
            </form>
        </div>
    </div>
</div>
<?php
    global $wpdb;
    $table_name = "microdeploy_settings";
    $micro_table_name = $wpdb->prefix . $table_name;
//TODO: ADD the settings at INIT TIME IN PLUGIN!!!
    if(isset($_POST['micro-deploy-settings-reset-max-upload'])){
        if(!insert_db($micro_table_name, array(
            'name' => 'max_upload',
            'value' => 10000000
        )))
            dispatch_error("Could not reset the settings.");
        else
            dispatch_success("Settings reset.");
    }


    if(isset($_POST['micro-deploy-settings-max-file-upload'])){
        $size = $_POST['micro-deploy-settings-max-file-upload'];
//        Validate the size!
        $size = trim($size);
        if(!micro_deploy_validate_input(array(
                'size' => $size
        ), [], array(
                'size' => 'string'
        )))
            {
            dispatch_error("Invalid size. Is it empty?");
            return;
        }

        if(!micro_deploy_validate_string_is_numeric($size))
            return false;


        if(!insert_db($micro_table_name, array(
            'name' => 'max_upload',
            'value' => $size
        )))
            dispatch_error("Could not save the settings.");
        else{
            $GLOBALS["micro_deploy_max_upload"] = $size;
            dispatch_success("Settings saved.");
        }
    }

}