<?php

function dispatch_error($message) {
    ?>
    <div class='micro-deploy-modal micro-deploy-modal-error'>
        <p><?php _e($message) ?></p>
    </div>
<?php
}

function dispatch_success($message) {
    ?>
    <div class='micro-deploy-modal micro-deploy-modal-success'>
        <p><?php _e($message) ?></p>
    </div>
    <?php
}

?>