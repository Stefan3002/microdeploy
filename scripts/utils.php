<?php
function remove_dir($dir) {
    if(rmdir($dir)){

    }
    else
        dispatch_error("Could not remove the micro folder that was created but an error was encountered while trying to move the files there");
    return;
}

?>