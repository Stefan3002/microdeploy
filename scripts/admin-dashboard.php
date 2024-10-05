<?php

require_once(plugin_dir_path(__FILE__) . 'modals.php');

// Function to display the certificate generation page
function micro_deploy_generate_admin_page() {

    global $wpdb;

    $micro_table_name = $wpdb->prefix . 'microdeploy_micros';
    $results = $wpdb->get_results("SELECT * FROM $micro_table_name");

    $plugin_data = get_plugin_data(__FILE__);
//    echo json_encode($plugin_data);
    ?>
    <div class='micro_deploy_admin-page-wrapper'>
        <div class="micro_deploy_admin-page-header">
            <h2 class="micro_deploy_title">Micro Deploy</h2>
            <p>by Ștefan Secrieru</p>
            <p>Version: <?php _e($plugin_data['Version']); ?></p>
            <p class="micro_deploy_catch">Enable complete micro frontend applications to coexist with WordPress content</p>
        </div>
        <div class="micro-deploy-admin-page-content">
            <div class="micro-deploy-admin-page-new-micro">
                <h2>Add a new micro frontend.</h2>
                <p>Note: Only upload the build folder of your micro frontend.</p>
                <form action="" method="post" enctype="multipart/form-data">
                    <input type="file" accept=".zip" required name="micro-deploy-add-new-micro-file">
                    <input type="text" required placeholder="Name of micro" name="micro-deploy-add-new-micro-name">
                    <button type="submit">Add micro</button>
                </form>

            </div>
        </div>
        <div class="micro-deploy-created-micros">
            <h2>Manage micros</h2>
            <div class="micro-deploy-micros">
                <?php
                foreach ($results as $micro) {
                    ?>
                    <div class="micro-deploy-admin-micro">
                        <p><?php _e($micro->slug) ?></p>
                        <p><?php _e($micro->created_at) ?></p>
                        <p><a href="/<?php _e($micro->slug) ?>">View micro</a></p>
                        <form action="" method="POST">
                            <input type="text" hidden name="delete-micro" value="<?php _e($micro->id) ?>">
                            <input type="text" hidden name="delete-micro-path" value="<?php _e($micro->path) ?>">
                            <button type="submit">DELETE</button>
                        </form>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    $wpdb->query('START TRANSACTION');
    try {
        if (isset($_POST['delete-micro'])) {
            $result = $wpdb->delete($micro_table_name,
                array(
                    'id' => $_POST['delete-micro']
                )
            );
            if (!$result) {
                error_log('Could not delete micro.');
                dispatch_error('Could not delete micro.');
                throw new Exception("Could not delete micro.");
            } else {
//            Delete the OS folder
                if (rmdir($_POST['delete-micro-path']), true)
                    dispatch_success($result . ' Micros have been deleted.');
                else {
//                MUST ROLLBACK!!!!
                    error_log('Could not delete micro folder.');
                    dispatch_error('Could not delete micro folder.');
                    throw new Exception("Could not delete micro.");
                }
            }
        }
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
    }


    if(isset($_FILES['micro-deploy-add-new-micro-file'])) {
        $micro_name = $_POST['micro-deploy-add-new-micro-name'];
        if(!isset($micro_name) || strlen($micro_name) == 0) {
            dispatch_error("Missing name of the micro");
            return;
        }
        if(strlen($micro_name) < 5) {
            dispatch_error("Name of the micro is too short");
            return;
        }
        add_micro();
    }

}

?>