<?php
// Prevent any HTML injection from hosting provider
if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'ESP32') !== false) {
    // It's the ESP32, just output JSON
} else {
    // It's a browser, we might need to clean the output buffer
}

// Turn off all error reporting and buffering
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted HTML injected by the host
ob_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

// --- CACHE SYSTEM TO FIX MAX_CONNECTIONS ERROR ---
$cacheFile = 'pins_cache.json';
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// If it's a read request and we have a fresh cache (less than 1 hour old)
// Serve the file directly and DO NOT connect to the database.
// Since we delete the cache on every update, we can keep this valid for a long time.
if ($action == 'get_pins' && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
    ob_clean(); // Clear any hosting garbage
    readfile($cacheFile);
    exit; // Stop script here, no DB connection made!
}
// ------------------------------------------------

$servername = "193.203.185.164";
$username = "u290660616_pustak";
$password = "Pustak@237";
$dbname = "u290660616_pustak";

// Disable default error reporting for mysqli
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    ob_clean(); // Clear any previous output
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// ... existing code ...

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

$response = [];

if ($action == 'get_pins') {
    $result = $conn->query("SELECT * FROM pins ORDER BY pin_number ASC");
    $pins = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['pin_number'] = (int)$row['pin_number'];
            $row['status'] = (int)$row['status'];
            $pins[] = $row;
        }
    }
    $response = $pins;
    // Save result to cache file for next requests
    file_put_contents($cacheFile, json_encode($response));
} 
elseif ($action == 'toggle') {
    $pin = intval($_POST['pin']);
    $conn->query("UPDATE pins SET status = IF(status=1, 0, 1) WHERE pin_number=$pin");
    $response = ["success" => true];
    @unlink($cacheFile); // Delete cache so next read gets fresh data
} 
elseif ($action == 'add_pin') {
    $pin = intval($_POST['pin']);
    $name = $conn->real_escape_string($_POST['name']);
    
    $check = $conn->query("SELECT * FROM pins WHERE pin_number=$pin");
    if ($check && $check->num_rows > 0) {
        $response = ["error" => "Pin already configured"];
    } else {
        $sql = "INSERT INTO pins (pin_number, name, status) VALUES ($pin, '$name', 0)";
        if ($conn->query($sql) === TRUE) {
            $response = ["success" => true];
            @unlink($cacheFile); // Delete cache
        } else {
            $response = ["error" => $conn->error];
        }
    }
} 
elseif ($action == 'edit_pin') {
    $old_pin = intval($_POST['old_pin']);
    $new_pin = intval($_POST['new_pin']);
    $name = $conn->real_escape_string($_POST['name']);

    // Check if new pin exists (and it's not the same as old pin)
    if ($old_pin != $new_pin) {
        $check = $conn->query("SELECT * FROM pins WHERE pin_number=$new_pin");
        if ($check && $check->num_rows > 0) {
            $response = ["error" => "New pin number already in use"];
        } else {
             $sql = "UPDATE pins SET pin_number=$new_pin, name='$name' WHERE pin_number=$old_pin";
             if ($conn->query($sql) === TRUE) {
                $response = ["success" => true];
                @unlink($cacheFile);
            } else {
                $response = ["error" => $conn->error];
            }
        }
    } else {
        // Just updating name
        $sql = "UPDATE pins SET name='$name' WHERE pin_number=$old_pin";
        if ($conn->query($sql) === TRUE) {
            $response = ["success" => true];
            @unlink($cacheFile);
        } else {
            $response = ["error" => $conn->error];
        }
    }
}
elseif ($action == 'delete_pin') {
    $pin = intval($_POST['pin']);
    $conn->query("DELETE FROM pins WHERE pin_number=$pin");
    $response = ["success" => true];
    @unlink($cacheFile); // Delete cache
} 
else {
    $response = ["message" => "Smart Home API Ready"];
}

$conn->close();

// CLEAN THE BUFFER
// This removes the "Quick Nav" HTML injected by the hosting provider
ob_clean(); 

echo json_encode($response);
exit;
?>