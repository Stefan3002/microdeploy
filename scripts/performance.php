<?php
function add_performance_client_data_to_micros(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'microdeploy_micros';
    $micros = $wpdb->get_results("SELECT * FROM $table_name");

    try {


        foreach ($micros as $micro) {
//            If Horizontal, skip, you can't measure performance here, I think!
            if($micro->type === 'horizontal')
                continue;
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
                const createHmac = async (message, secret) => {
    const encoder = new TextEncoder()

    const key = await crypto.subtle.importKey(
        "raw",
        encoder.encode(secret),
        {
            name: "HMAC",
            hash: {
                name: "SHA-256"
            }
        },
        false,
        ["sign"]
    )

    const signature = await crypto.subtle.sign(
        "HMAC",
        key,
        encoder.encode(JSON.stringify(message))
    )
    let aux = ""
    const intBytes = new Uint8Array(signature)
    intBytes.forEach((byte) => {
        byte = byte.toString(16).padStart(2, "0")
        aux += byte
    })
    return aux
}

async function importPublicKey(pemKey) {
    const binaryDer = Uint8Array.from(atob(pemKey), c => c.charCodeAt(0));

    return await window.crypto.subtle.importKey(
        "spki", // SubjectPublicKeyInfo format
        binaryDer,
        { name: "RSA-OAEP", hash: "SHA-256" },
        true,  // Can be exported
        ["encrypt"] // Only encryption allowed
    );
}

async function encryptMessage(publicKey, message) {
    const encodedMessage = new TextEncoder().encode(message);
    const encrypted = await window.crypto.subtle.encrypt(
        { name: "RSA-OAEP" },
        publicKey,
        encodedMessage
    );

    return btoa(String.fromCharCode(...new Uint8Array(encrypted)));
}
                
                
	const sendData = async () => {
    	// Only send the data when both FCP and LCP have been captured
    	if (fcp !== -1 && lcp !== -1 && dcl !== -1) {
            
            const dataNonce = await fetch("' . site_url() . '/wp-json/security/v1/get-nonce", {
            	method: "GET",
        	});
        	const nonce = (await dataNonce.json()).data;
            const date = Date.now()
            const secret = nonce + date
            
//            Encrypt the nonce!!!
            const key = await(importPublicKey("MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsBXAYZpm02DKBGabl+d8q1jPs9ZOAQsvikDNCtpXgAm4qEbuWjf+TJnzptuZfKpaN6+ObkNaNtgKsSEMtDOaI0lq+SK+GhvTVetiZwicA1TnKmn0kH+OP9YIwgKpWlQfBG+ieUh8q/UJnK+TK4UCWuh/oJznyN/k6SrVmjTNkSVaGvtBp3oIhvTrhiioLK2jJlZiN8FgggMi3X1AHOmVzT9/bX2SbOBJYZJlYhkTz9nJ7tm4g8GNAs4WFHy1y0jn9r0te7VqroWui1Ui4Zz3x9rxWeCX9iRa1Kp6cn5AoJoqfUCDrGFkEmd25EOabTRh9mlOJKnkk0PV0REAapUySQIDAQAB"))
            const nonce_encrypted = await encryptMessage(key, nonce)
            
            
        	let data = {
                nonce_encrypted,
                slug: "' . $micro_slug . '",
            	dcl,
            	fcp,
            	lcp,
            	date
        	};
            const hash = await createHmac(data, secret)
            data = {
                nonce,
                slug: "' . $micro_slug . '",
            	dcl,
            	fcp,
            	lcp,
            	date,
            	hash
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
    global $wpdb;
    try {
        $wpdb->query("START TRANSACTION");

        if(!micro_deploy_origin_check())
            throw new Exception("Not the same origin!");


        $table_name = $wpdb->prefix . 'microdeploy_performance';
        $data = $request->get_json_params();

        if(!micro_deploy_check_nonce($data))
            throw new Exception("Invalid nonce!");

        if (!micro_deploy_validate_input($data, [
            "slug" => true,
            "dcl" => true,
            "fcp" => true,
            "lcp" => true
        ], [
            "slug" => "string",
            "dcl" => "integer",
            "fcp" => "integer",
            "lcp" => "integer"
        ]))
            throw new Exception("Invalid data!");

        if(!micro_deploy_check_hash($data['hash'], $data))
            throw new Exception("Invalid hash!");

        $db_data = [
            'slug' => $data['slug'],
            'dcl' => $data['dcl'],
            'fcp' => $data['fcp'],
            'lcp' => $data['lcp']
        ];
        $slug = $data['slug'];
        $performance_limit = $GLOBALS['micro_deploy_performance_limit'];
//    Only keep the latest values!
        if(!$wpdb->insert($table_name, $db_data))
            throw new Exception("Could not save performance data.");

        $deletion_result = $wpdb->query("
    DELETE FROM $table_name 
    WHERE id NOT IN (
        SELECT id FROM (
            SELECT id FROM $table_name WHERE slug='$slug' ORDER BY created_at DESC LIMIT $performance_limit
        ) AS t
    ) AND slug='$slug'
");
        if($deletion_result === false)
            throw new Exception("Could not save performance data.");

        if(!micro_deploy_consume_nonce($data['nonce']))
            throw new Exception("Could not save performance data.");

        $wpdb->query("COMMIT");
        return new WP_REST_Response('ok', 200);

    }catch (Exception $e){
        $wpdb->query("ROLLBACK");
        dispatch_error("Could not save performance data.");
    }
}