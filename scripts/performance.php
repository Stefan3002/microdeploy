<?php
function add_performance_client_data_to_micros(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_micros';
    $micros = $wpdb->get_results("SELECT * FROM $table_name");

    try {


        foreach ($micros as $micro) {
            $micro_path = $micro->path;
            $micro_slug = $micro->slug;
            $index_path = micro_deploy_search_index_html($micro_path);
//        error_log("Micro path: " . $micro_path);
//        error_log("Micro path: " . $index_path);
            if (!$index_path)
                continue;
            $contents = file_get_contents($index_path);
            error_log("Contents: " . $contents);

            $pattern = '/<head\b[^>]*>/';



            $measuring_script = '
            <script src="https://unpkg.com/web-vitals@3/dist/web-vitals.iife.js"></script>
            <script>
            (() => {
	const sendData = () => {
    	// Only send the data when both FCP and LCP have been captured
    	if (fcp !== -1 && lcp !== -1 && dcl !== -1) {
        	const data = {
                slug: "' . $micro_slug . '",
            	dcl,
            	fcp,
            	lcp
        	};
    	fetch("' . site_url() . '/wp-json/performance-metrics/v1/set-data", {
            	method: "POST",
            	headers: {
                	"Content-Type": "application/json"
            	},
            	body: JSON.stringify(data)
        	});
    	}
	}
	let fcp = -1;
	let lcp = -1;
	let dcl = -1;
    
	// Wait for load event
	window.addEventListener("load", () => {
    	dcl = performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart;
    	sendData()
    	}
	);
    
	// Capture FCP and LCP with web-vitals
	webVitals.onFCP((value) => {
    	fcp = value.value; // Update FCP value
    	sendData()
	});

	webVitals.onLCP((value) => {
    	lcp = value.value; // Update LCP value
    	sendData()
	});
})();



</script>
        ';
            $target_pattern_start = '<!-- START MICRODEPLOY PERFORMANCE MEASURING -->';
            $target_pattern_end = '<!-- END MICRODEPLOY PERFORMANCE MEASURING -->';

            $pattern_already_exists = '/<!-- START MICRODEPLOY PERFORMANCE MEASURING -->([\s\S]*?)<!-- END MICRODEPLOY PERFORMANCE MEASURING -->/';
//        Skip if already in place!
            if (preg_match($pattern_already_exists, $contents))
                continue;


            $updated_contents = preg_replace_callback($pattern, function ($matches) use ($target_pattern_start, $target_pattern_end, $measuring_script) {
                $match = $matches[0];
//                error_log('ALOHAA ' . $match);
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

function set_data_rest($request)
{
//    TODO: SANITIZE THE DATA!!!
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_performance';
    $data = $request->get_json_params();
    error_log(print_r($data, true));
    $wpdb->insert($table_name, $data);
    return new WP_REST_Response('ok', 200);
}