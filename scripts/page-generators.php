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
        <div class="micro-deploy-admin-page-new-micro micro-deploy-card">
            <h2>Max parser backtrack limit</h2>
            <p>───── ⋆⋅☆⋅⋆ ─────</p>
            <form action="" method="post">
                <label for="micro-deploy-settings-max-backtrack">Number of allowed backtracking operations of the PHP PCRE</label>
                <p>Default: 100.000 operations</p>
                <input type="text" id="micro-deploy-settings-max-backtrack" required value="<?php _e($GLOBALS["micro_deploy_max_backtrack"]) ?> " placeholder="Max file upload" name="micro-deploy-settings-max-backtrack">
                <button type="submit">Save</button>
            </form>
            <form class="micro-deploy-form-marginated-top" action="" method="POST">
                <input hidden name="micro-deploy-settings-reset-max-backtrack">
                <button type="submit" class="micro-deploy-delete-button">Reset</button>
            </form>
        </div>
        <?php
            if($GLOBALS['micro_deploy_enabled_performance'] === true) {
        ?>
        <div class="micro-deploy-admin-page-new-micro micro-deploy-card">
            <h2>Max performance entries</h2>
            <p>───── ⋆⋅☆⋅⋆ ─────</p>
            <form action="" method="post">
                <label for="micro-deploy-settings-max-backtrack">Number of stored records of performance per micro</label>
                <p>Default: 100 entries</p>
                <input type="text" id="micro-deploy-settings-max-backtrack" required value="<?php _e($GLOBALS['micro_deploy_performance_limit']) ?> " placeholder="Max performance records" name="micro-deploy-settings-max-performance-limit">
                <button type="submit">Save</button>
            </form>
            <form class="micro-deploy-form-marginated-top" action="" method="POST">
                <input hidden name="micro-deploy-settings-reset-max-performance-limit">
                <button type="submit" class="micro-deploy-delete-button">Reset</button>
            </form>
        </div>
                <?php
            }
                ?>
    </div>
</div>
<?php
    global $wpdb;
    $table_name = "microdeploy_settings";
    $micro_table_name = $wpdb->prefix . $table_name;
    insert_db_wrapper($table_name, 'max_upload', 10000000, 'micro_deploy_max_upload', 'micro-deploy-settings-reset-max-upload');
    insert_db_wrapper($table_name, 'max_backtrack', 100000, 'micro_deploy_max_backtrack', 'micro-deploy-settings-reset-max-backtrack');
    insert_db_wrapper($table_name, 'performance_limit', 100, 'micro_deploy_performance_limit', 'micro-deploy-settings-reset-max-performance-limit');

    $data_value = null;
    $size = null;
    $global_name = null;

    if(isset($_POST['micro-deploy-settings-max-file-upload'])) {
        $data_value = 'max_upload';
        $size = $_POST['micro-deploy-settings-max-file-upload'];
        $global_name = 'micro_deploy_max_upload';
    }

    if(isset($_POST['micro-deploy-settings-max-backtrack'])) {
        $data_value = 'max_backtrack';
        $size = $_POST['micro-deploy-settings-max-backtrack'];
        $global_name = 'micro_deploy_max_backtrack';
    }
    if(isset($_POST['micro-deploy-settings-max-performance-limit'])) {
        $data_value = 'performance_limit';
        $size = $_POST['micro-deploy-settings-max-performance-limit'];
        $global_name = 'micro_deploy_max_performance_limit';
    }


    if($data_value !== null) {
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

        insert_db_wrapper($table_name, $data_value, $size, $global_name);
    }

}

function micro_deploy_generate_performance_page() {
    global $wpdb;

//    $micro_table_name = $wpdb->prefix . 'microdeploy_errors';
//    $results = $wpdb->get_results("SELECT * FROM $micro_table_name WHERE type='static_serve'");
    $table_name = "microdeploy_settings";
    $micro_table_name = $wpdb->prefix . $table_name;

    $data_value = 'enabled_performance';
    $size = 'true';
    $global_name = 'micro_deploy_enabled_performance';

    insert_db_wrapper($table_name, $data_value, $size, $global_name, 'micro_deploy_enable_performance', 'add_performance_client_data_to_micros');
    insert_db_wrapper($table_name, $data_value, 'false', $global_name, 'micro_deploy_reset_performance');

    if(isset($_POST['micro_deploy_reparse_performance'])){
        add_performance_client_data_to_micros();
    }

    ?>
    <div class='micro_deploy_admin-page-wrapper'>
        <div class="micro_deploy_admin-page-header">
            <h2 class="micro_deploy_title">Micro Deploy</h2>
            <p>by Ștefan Secrieru</p>
        </div>
        <section class="micro-deploy-admin-page-content marginated-bottom">
            <div class="micro-deploy-admin-page-new-micro micro-deploy-card">
                <h2>Deployment Performance</h2>
                <p>───── ⋆⋅☆⋅⋆ ─────</p>
                <form action="" method="post">
                    <input type="text" hidden name="micro_deploy_enable_performance">
                    <button>Enable</button>
                </form>
                <form class="micro-deploy-form-marginated-top" action="" method="POST">
                    <input hidden name="micro_deploy_reset_performance">
                    <button type="submit" class="micro-deploy-delete-button">Disable</button>
                </form>
                <?php
                if($GLOBALS['micro_deploy_enabled_performance'] == true){
                ?>
                <form class="micro-deploy-form-marginated-top" action="" method="POST">
                    <input hidden name="micro_deploy_reparse_performance">
                    <button type="submit">Re-parse files</button>
                </form>
                    <?php
                        }
                    ?>
            </div>
        </section>
        <section class="micro-deploy-admin-page-content marginated-bottom">
            <?php
            ?>
            <?php if($GLOBALS['micro_deploy_enabled_performance'] == true){ ?>
                <section class="micro-deploy-admin-page-new-micro micro-deploy-card">
                    <h2>DCL</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                    <p>123</p>
                </section>
                <section class="micro-deploy-admin-page-new-micro micro-deploy-card">
                    <h2>FCP</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                    <p>123</p>
                </section>
                <section class="micro-deploy-admin-page-new-micro micro-deploy-card">
                    <h2>LCP</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                    <p>123</p>
                </section>
                <?php
            }
            ?>

        </section>
    </div>
<?php
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
                <div class="micro-deploy-admin-page-new-micro micro-deploy-card micro-deploy-max-height-card">
                    <h2>Static Serving Faults URLs</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                    <form action="" method="POST">
                        <input type="text" hidden name="micro_deploy_delete_all_errors">
                        <button type="submit">Delete all records</button>
                    </form>
                    <p>The following static assets requests encountered an error:</p>
                    <ul>
                        <?php
                            foreach($results as $result){
                                ?>
                                <li><?php _e($result->path) ?>
                                    <form action="" method="POST">
                                        <input type="text" hidden name="micro_deploy_delete_error" value="<?php _e($result->id) ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </li>
                                <?php
                            }
                        }
                        ?>
                    </ul>
                </div>
        </section>
    </div>
    <?php
    if(isset($_POST['micro_deploy_delete_error']))
        if($wpdb->delete($micro_table_name, array(
            'id' => $_POST['micro_deploy_delete_error']
        ))) {
            dispatch_success("Error deleted.");
        } else {
            dispatch_error("Could not delete error.");
        }
    if(isset($_POST['micro_deploy_delete_all_errors']))
        foreach($results as $result)
            if($wpdb->delete($micro_table_name, array(
                'id' => $result->id
            ))) {
                dispatch_success("Error deleted.");
            } else {
                dispatch_error("Could not delete error.");
            }
}

function micro_deploy_generate_about_page() {
    ?>
    <div class='micro_deploy_admin-page-wrapper'>
        <div class="micro_deploy_admin-page-header">
            <h2 class="micro_deploy_title">Micro Deploy</h2>
            <p>by Ștefan Secrieru</p>
        </div>
        <div class="centered-title">
            <div class="work-card micro-deploy-admin-page-new-micro micro-deploy-card micro-deploy-small-card">
                <div class="centered-title">
                    <h2>How Does it Work?</h2>
                    <p>───── ⋆⋅☆⋅⋆ ─────</p>
                </div>
                <ul>
                    <li><strong>For Vertical Splits:</strong> Upload an archive that has a build/dist folder inside.</li>
                    <li><strong>For Horizontal Splits:</strong> Upload an archive that has a build/dist folder inside and inside that folder there are 3 files: a .css, .jd, index.html file. Inside the index.html file, there must be at least one section delimited by the following comments:

                        <p>START MICRODEPLOY HORIZONTAL SPLIT</p>
                        <p>END MICRODEPLOY HORIZONTAL SPLIT</p>
                        All HTML code between these two comments will be considered a micro-frontend. If there are multiple such sections, they will all be aggregated into one.
                    </li>
                </ul>
            </div>
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