<?php

// Run this via: php artisan tinker < debug_queue.php

$jobs = DB::table('failed_jobs')->orderByDesc('id')->get();
foreach ($jobs as $job) {
    $payload = json_decode($job->payload);
    echo 'ID: ' . $job->id . ' | ' . ($payload->displayName ?? 'unknown') . PHP_EOL;
    echo 'Exception: ' . substr($job->exception ?? '', 0, 500) . PHP_EOL;
    echo '---' . PHP_EOL;
}
