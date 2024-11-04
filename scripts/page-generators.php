<?php

function micro_deploy_generate_settings_page(){
    ?>
<div class='micro_deploy_admin-page-wrapper'>
    <div class="micro_deploy_admin-page-header">
        <h2 class="micro_deploy_title">Micro Deploy</h2>
        <p>by Ștefan Secrieru</p>
    </div>
    <div class="micro-deploy-admin-page-content">
        <div class="micro-deploy-admin-page-new-micro micro-deploy-card">
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
            'name' => 'name',
            'value_name' => 'value',
            'data_value' => 'max_upload',
            'value_change' => 10000000,
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
            'name' => 'name',
            'data_value' => 'max_upload',
            'value_name' => 'value',
            'value_change' => $size,
            'value' => $size
        )))
            dispatch_error("Could not save the settings.");
        else{
            $GLOBALS["micro_deploy_max_upload"] = $size;
            dispatch_success("Settings saved.");
        }
    }

}
function micro_deploy_generate_errors_page() {
    global $wpdb;

    $micro_table_name = $wpdb->prefix . 'microdeploy_errors';
    $results = $wpdb->get_results("SELECT * FROM $micro_table_name WHERE type='static_serve'");

    ?>
    <div class='micro_deploy_admin-page-wrapper'>
        <div class="micro_deploy_admin-page-header">
            <h2 class="micro_deploy_title">Micro Deploy</h2>
            <p>by Ștefan Secrieru</p>
        </div>
        <section class="micro-deploy-settings-page-content">
                <div class="micro-deploy-admin-page-new-micro micro-deploy-card">
                    <h2>Static Serving Faults</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                    <p>Number of static serving faults encountered:</p>
                    <span class="micro-deploy-big-number"><?php _e(count($results)) ?></span>
                </div>
            <?php if(count($results) !== 0){ ?>
                <div class="micro-deploy-admin-page-new-micro micro-deploy-card">
                    <h2>Static Serving Faults URLs</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                    <p>The following static assets requests encountered an error:</p>
                    <ul>
                        <?php
                            foreach($results as $result){
                                ?>
                                <li><?php _e($result->path) ?></li>
                                <?php
                            }
                        }
                        ?>
                    </ul>
                </div>
        </section>
    </div>
    <?php
}

function micro_deploy_generate_about_page() {
    ?>
    <div class='micro_deploy_admin-page-wrapper'>
        <div class="micro_deploy_admin-page-header">
            <h2 class="micro_deploy_title">Micro Deploy</h2>
            <p>by Ștefan Secrieru</p>
        </div>
        <div class="micro-deploy-admin-page-content micro-deploy-settings-page-content">
            <div class="micro-deploy-admin-page-new-micro micro-deploy-card micro-deploy-small-card">
                <div class="centered-title">
                    <h2>About Functionalities</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                </div>
                <p>Micro Deploy is a plugin that enables you to deploy micro frontends on your WordPress website.</p>
            </div>
            <div class="micro-deploy-admin-page-new-micro micro-deploy-card micro-deploy-small-card">
                <div class="centered-title">
                    <h2>About Subpages</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                </div>
                <p>Following are the subpages of the plugin:</p>
                <ul>
                    <li><b>Settings</b> contains various settings of the plugin.</li>
                    <li><b>Errors</b> contains all the errors that the plugin encountered. These include but are not limited to:</li>
                    <ul>
                        <li>Static asset delivery fails</li>
                    </ul>
                </ul>
            </div>
            <div class="micro-deploy-admin-page-new-micro micro-deploy-card micro-deploy-small-card">
                <div class="centered-title">
                    <h2>About Limitations</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                </div>
                <p>Here are the main limitations of the plugin and steps on how to overcome them:</p>
                <ul>
                    <li><b>React Js</b> developers have to manually set the base of their routing system to match the chosen slug in the deployment process of the micro-frontend</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}