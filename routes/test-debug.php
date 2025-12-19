<?php
use Illuminate\Support\Facades\Route;

Route::post("/api/test-debug", function() {
    \Log::info("Test debug endpoint hit");
    
    return response()->json([
        "status" => "ok",
        "time" => now(),
        "php_version" => phpversion(),
        "memory" => memory_get_usage()
    ]);
});
