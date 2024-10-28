<?php

require_once(plugin_dir_path(__FILE__) . 'modals.php');
require_once(plugin_dir_path(__FILE__) . 'utils.php');

require_once(plugin_dir_path(__FILE__) . 'state-manager.php');

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
                <p>───── ⋆⋅☆⋅⋆ ─────</p>
                <p>Note: Only upload the build folder of your micro frontend.</p>
                <form action="" method="post" enctype="multipart/form-data">
                    <input type="file" accept="application/zip" required name="micro-deploy-add-new-micro-file">
                    <input type="text" required placeholder="Name of micro" name="micro-deploy-add-new-micro-name">
                    <input type="text" required placeholder="Slug of micro" name="micro-deploy-add-new-micro-slug">
                    <label for="">Technology of micro frontend</label>
                    <select type="text" required placeholder="Technology" name="micro-deploy-add-new-micro-tech">
                        <option value="react">React</option>
                        <option value="angular">Angular</option>
                        <option value="vanilla">Vanilla</option>
                    </select>
                    <label for="">Build tool of micro frontend</label>
                    <select type="text" required placeholder="Build tool" name="micro-deploy-add-new-micro-build">
                        <option value="cra">CRA</option>
                        <option value="vite">Vite</option>
                    </select>
                    <button type="submit">Add micro</button>
                </form>
            </div>
        </div>
        <div class="micro-deploy-state">
            <h2>State Manager Service</h2>
            <p>───── ⋆⋅☆⋅⋆ ─────</p>
            <div class="micro-deploy-state-options">
                <form action="" method="post">
                    <input type="text" hidden name="initialize_state">
                    <button type="submit">Initialize state manager</button>
                </form>
                <form action="" method="post">
                    <input type="text" hidden name="remove_state">
                    <button type="submit">Remove state manager</button>
                </form>
            </div>
        </div>
        <div class="micro-deploy-created-micros">
            <h2>Manage micros</h2>
            <p>───── ⋆⋅☆⋅⋆ ─────</p>
            <div class="micro-deploy-manage-options">

            </div>
            <div class="micro-deploy-micros">
                <?php
                if(count($results) === 0){
                    ?>
                    <p>No micros yet.</p>
                    <?php
                }
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
                                <input type="text" hidden name="micro-deploy-fix-links-tech" value="<?php _e($micro->tech) ?>">
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

    if(isset($_POST['initialize_state']))
        micro_deploy_initialize_state_manager();

    if(isset($_POST['remove_state']))
        micro_deploy_remove_state_manager();

    if(isset($_POST['micro-deploy-fix-links']))
        micro_deploy_adjust_urls_static_serve($_POST['micro-deploy-fix-links'], $_POST['micro-deploy-fix-links-slug'], $_POST['micro-deploy-fix-links-tech'], $_POST['micro-deploy-fix-links-build']);

    if(isset($_FILES['micro-deploy-add-new-micro-file'])) {
        $micro_name = $_POST['micro-deploy-add-new-micro-name'];
        $micro_slug = $_POST['micro-deploy-add-new-micro-slug'];
        $micro_tech = $_POST['micro-deploy-add-new-micro-tech'];
        $micro_build = $_POST['micro-deploy-add-new-micro-build'];

        if(!isset($micro_tech)){
            dispatch_error("Missing technology of the micro");
            return;
        }
        if(!isset($micro_build)){
            dispatch_error("Missing build tool of the micro");
            return;
        }
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
