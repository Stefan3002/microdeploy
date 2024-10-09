<?php

require_once(plugin_dir_path(__FILE__) . 'modals.php');
require_once(plugin_dir_path(__FILE__) . 'utils.php');

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
                    <input type="text" required placeholder="Slug of micro" name="micro-deploy-add-new-micro-slug">
                    <button type="submit">Add micro</button>
                </form>

            </div>
        </div>
        <div class="micro-deploy-created-micros">
            <h2>Manage micros</h2>
            <div class="micro-deploy-manage-options">

            </div>
            <div class="micro-deploy-micros">
                <?php
                foreach ($results as $micro) {
                    ?>
                    <div class="micro-deploy-admin-micro">
                        <p>Name: <span class="micro_deploy_admin_micro_detail"><?php _e($micro->name) ?></span></p>
                        <p>Slug: <span class="micro_deploy_admin_micro_detail">/<?php _e($micro->slug) ?></span></p>
                        <p><?php _e($micro->created_at) ?></p>
                        <p><a href="/<?php _e($micro->slug) ?>">View micro</a></p>
                        <div class="micro-deploy-option-forms">
                            <form class="micro_deploy_admin_form" action="" method="POST">
                                <input type="text" hidden name="delete-micro" value="<?php _e($micro->id) ?>">
                                <input type="text" hidden name="delete-micro-path" value="<?php _e($micro->path) ?>">
                                <button class="micro-deploy-delete-button" type="submit">DELETE</button>
                            </form>
                            <form class="micro_deploy_admin_form" action="" method="POST">
                                <input type="text" hidden name="micro-deploy-fix-links" value="<?php _e($micro->path) ?>">
                                <input type="text" hidden name="micro-deploy-fix-links-slug" value="<?php _e($micro->slug) ?>">
                                <button type="submit">PARSE LINKS</button>
                            </form>
                        </div>
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
                try {
//                    error_log('asdawdwad ' . $_POST['delete-micro-path']);
                    micro_deploy_remove_folder($_POST['delete-micro-path']);
                    rmdir($_POST['delete-micro-path']);
                }
                catch (Exception $e){
                    //                MUST ROLLBACK!!!!
                    error_log('Could not delete micro folder.');
                    dispatch_error('Could not delete micro folder.');
                    throw new Exception("Could not delete micro.");
                }
                    dispatch_success($result . ' Micros have been deleted.');
            }
        }
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
    }

    if(isset($_POST['micro-deploy-fix-links']))
        micro_deploy_adjust_urls_static_serve($_POST['micro-deploy-fix-links'], $_POST['micro-deploy-fix-links-slug']);

    if(isset($_FILES['micro-deploy-add-new-micro-file'])) {
        $micro_name = $_POST['micro-deploy-add-new-micro-name'];
        $micro_slug = $_POST['micro-deploy-add-new-micro-slug'];
        if(!isset($micro_name) || strlen($micro_name) == 0) {
            dispatch_error("Missing name of the micro");
            return;
        }
        if(!isset($micro_slug) || strlen($micro_slug) == 0) {
            dispatch_error("Missing slug of the micro");
            return;
        }
        if(strlen($micro_name) < 3) {
            dispatch_error("Name of the micro is too short");
            return;
        }
        if(strlen($micro_slug) < 3) {
            dispatch_error("Slug of the micro is too short");
            return;
        }
        add_micro();
    }

}
