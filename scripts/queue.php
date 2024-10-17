<?php
function micro_deploy_init_job_queue() {
    while(true){

    }
}

function micro_deploy_enqueue_job($job) {
    global $md_redis;

    $md_redis->lpush('job_queue', $job);
}

function micro_deploy_dequeue_job($job) {
    global $md_redis;

    $md_redis->del('job_queue', $job);
}

function micro_deploy_take_job() {
    global $md_redis;

    $data_json = $md_redis->retrieve('job_queue');
    $data = json_decode($data_json, true);
    $data = micro_deploy_get_data($data['key'], $data['value']);
    $md_redis->lpop('job_queue');

    return $data;
}