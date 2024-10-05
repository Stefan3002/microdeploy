<?php

require_once(plugin_dir_path(__FILE__) . 'modals.php');

// Function to display the certificate generation page
function micro_deploy_generate_admin_page() {

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
    </div>
    <?php

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