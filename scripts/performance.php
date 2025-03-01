<?php
function add_performance_client_data_to_micros(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_micros';
    $micros = $wpdb->get_results("SELECT * FROM $table_name");

    try {


        foreach ($micros as $micro) {
            $micro_path = $micro->path;
            $index_path = micro_deploy_search_index_html($micro_path);
//        error_log("Micro path: " . $micro_path);
//        error_log("Micro path: " . $index_path);
            if (!$index_path)
                continue;
            $contents = file_get_contents($index_path);
            error_log("Contents: " . $contents);

            $pattern = '/<head\b[^>]*>/';
            $measuring_script = '
            <script>console.log("Hello World3")</script>
        ';
            $target_pattern_start = '<!-- START MICRODEPLOY PERFORMANCE MEASURING -->';
            $target_pattern_end = '<!-- END MICRODEPLOY PERFORMANCE MEASURING -->';

            $pattern_already_exists = '/<!-- START MICRODEPLOY PERFORMANCE MEASURING -->([\s\S]*?)<!-- END MICRODEPLOY PERFORMANCE MEASURING -->/';
//        Skip if already in place!
            if (preg_match($pattern_already_exists, $contents))
                continue;


            $updated_contents = preg_replace_callback($pattern, function ($matches) use ($target_pattern_start, $target_pattern_end, $measuring_script) {
                $match = $matches[0];
                error_log('ALOHAA ' . $match);
                return $match . $target_pattern_start . $measuring_script . $target_pattern_end;
            }, $contents);
            $updated_contents = micro_deploy_handle_regex_errors(preg_last_error(), $updated_contents, $contents);
            file_put_contents($index_path, $updated_contents);
        }
        dispatch_success("Performance measuring scripts have been added to the micros.");
    }
    catch (Exception $e){
        dispatch_error("Could not add performance measuring scripts to the micros." . '/////' . $e->getMessage());
    }
}