<?php

function micro_deploy_generate_settings_page(){
//    Retrieve the max file upload size from the db
    global $wpdb;

    $table_name = "microdeploy_settings";
    $micro_table_name = $wpdb->prefix . $table_name;

    $results = $wpdb->get_results("SELECT value FROM $micro_table_name WHERE name = 'max_upload'");
    error_log('wwwwwwwww ' . print_r($results, true));
    if(count($results) === 0)
        $max_upload_size = 100000;
    else
        $max_upload_size = $results[0]->value;

    ?>
<div class='micro_deploy_admin-page-wrapper'>
    <div class="micro_deploy_admin-page-header">
        <h2 class="micro_deploy_title">Micro Deploy</h2>
        <p>by Ștefan Secrieru</p>
    </div>
    <div class="micro-deploy-admin-page-content">
        <div class="micro-deploy-admin-page-new-micro">
            <h2>Tweak settings.</h2>
            <p>───── ⋆⋅☆⋅⋆ ─────</p>
            <form action="" method="post">
                <label for="micro-deploy-settings-max-file-upload">Max file upload size (in bytes)</label>
                <input type="text" id="micro-deploy-settings-max-file-upload" required value="<?php _e($max_upload_size) ?> " placeholder="Max file upload" name="micro-deploy-settings-max-file-upload">
                <button type="submit">Save</button>
            </form>
        </div>
    </div>
</div>
<?php
    if(isset($_POST['micro-deploy-settings-max-file-upload'])){
        $size = $_POST['micro-deploy-settings-max-file-upload'];
//TODO: read the value in a global value to have effect
        insert_db($micro_table_name, array(
            'name' => 'max_upload',
            'value' => $size
        ));
    }

}