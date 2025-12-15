<?php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Smart Khwopa Dashboard</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background-color: #f4f4f9; }
        h1 { color: #333; text-align: center; margin-bottom: 30px; }
        
        /* Grid Layout */
        #lights {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .pin-card { 
            background: white;
            border: 1px solid #e0e0e0; 
            padding: 20px; 
            border-radius: 12px; 
            display: flex; 
            flex-direction: column;
            align-items: center; 
            justify-content: space-between; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 220px; /* Fixed height for uniformity */
        }
        
        .pin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .pin-info { 
            text-align: center; 
            margin-bottom: 15px;
            width: 100%;
        }
        .pin-info strong { display: block; font-size: 1.4em; color: #2c3e50; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pin-info small { color: #95a5a6; font-size: 0.9em; }
        
        .controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            width: 100%;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #2196F3; }
        input:focus + .slider { box-shadow: 0 0 1px #2196F3; }
        input:checked + .slider:before { transform: translateX(26px); }

        .btn-delete {
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .btn-delete:hover { opacity: 1; }

        .btn-edit {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .btn-edit:hover { opacity: 1; }
        
        /* Add Card Styles */
        .add-card {
            border: 2px dashed #cbd5e0;
            background-color: #f8fafc;
            justify-content: center;
        }
        .add-card h3 { margin: 0 0 15px 0; color: #4a5568; }
        .add-card input {
            width: 90%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        .btn-add { 
            width: 90%;
            padding: 10px; 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold;
        }
        .btn-add:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <h1>Smart Home Control</h1>
    
    <div id="lights">
        <!-- Static Add Device Card (Won't refresh) -->
        <div class="pin-card add-card" id="addDeviceCard">
            <h3>Add Device</h3>
            <input type="number" id="newPin" placeholder="GPIO Pin (e.g. 2)">
            <input type="text" id="newPinName" placeholder="Name (e.g. Fan)">
            <button class="btn-add" onclick="addPin()">Add</button>
        </div>
    </div>
    
    <script>
    let isEditing = false;

    function refreshPins() {
        if (isEditing) return; // Stop refreshing while editing

        $.get('control.php?action=get_pins', function(data) {
            try {
                // If data is string, try to parse it. If it's already object, use it.
                let pins = typeof data === 'string' ? JSON.parse(data) : data;
                
                if (pins.error) {
                    $('.dynamic-pin').remove();
                    $('#addDeviceCard').before('<p class="dynamic-pin" style="color:red; text-align:center; grid-column: 1/-1;">Server Error: ' + pins.error + '</p>');
                    return;
                }
                
                refreshPins_Render(pins);

            } catch (e) {
                console.error("Parsing error", e);
                // Try to extract JSON from the mess if possible
                let jsonMatch = typeof data === 'string' ? data.match(/\[.*\]|\{.*\}/) : null;
                if (jsonMatch) {
                    try {
                        let pins = JSON.parse(jsonMatch[0]);
                        refreshPins_Render(pins); 
                        return;
                    } catch(e2) {}
                }
                
                $('.dynamic-pin').remove();
                $('#addDeviceCard').before('<div class="dynamic-pin" style="color:red; text-align:center; grid-column: 1/-1;"><strong>Parsing Error:</strong> The server is injecting HTML into the JSON response.</div>');
            }
        });
    }

    function refreshPins_Render(pins) {
        // Remove only the dynamic pin cards, keep the Add Card intact
        $('.dynamic-pin').remove();

        let html = '';
        
        if(Array.isArray(pins)) {
            pins.forEach(pin => {
                let isChecked = pin.status == 1 ? 'checked' : '';
                // Add 'dynamic-pin' class and ID
                html += `<div class="pin-card dynamic-pin" id="card-${pin.pin_number}">
                    <div class="pin-info">
                        <strong>${pin.name}</strong>
                        <small>GPIO Pin: ${pin.pin_number}</small>
                    </div>
                    <div class="controls">
                        <label class="switch">
                            <input type="checkbox" ${isChecked} onchange="togglePin(${pin.pin_number})">
                            <span class="slider"></span>
                        </label>
                        <div style="display:flex; gap:10px;">
                            <button class="btn-edit" onclick="startEdit(${pin.pin_number}, '${pin.name.replace(/'/g, "\\'")}')">Edit</button>
                            <button class="btn-delete" onclick="deletePin(${pin.pin_number})">Delete</button>
                        </div>
                    </div>
                </div>`;
            });
        }

        // Insert new pins BEFORE the Add Card
        $('#addDeviceCard').before(html);
    }

    function startEdit(pin, name) {
        isEditing = true;
        let card = $(`#card-${pin}`);
        let html = `
            <div style="display:flex; flex-direction:column; gap:10px; width:100%; align-items:center;">
                <h3 style="margin:0; color:#2196F3;">Edit Device</h3>
                <input type="number" id="editPin_${pin}" value="${pin}" placeholder="GPIO Pin" style="width:90%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                <input type="text" id="editName_${pin}" value="${name}" placeholder="Name" style="width:90%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                <div style="display:flex; gap:10px; width:90%;">
                    <button class="btn-add" onclick="saveEdit(${pin})" style="flex:1;">Save</button>
                    <button class="btn-delete" onclick="cancelEdit()" style="background:#999; flex:1;">Cancel</button>
                </div>
            </div>
        `;
        card.html(html);
    }

    function cancelEdit() {
        isEditing = false;
        refreshPins();
    }

    function saveEdit(oldPin) {
        let newPin = $(`#editPin_${oldPin}`).val();
        let newName = $(`#editName_${oldPin}`).val();
        
        if(!newPin || !newName) {
            alert("Please enter pin number and name");
            return;
        }

        $.post('control.php', {
            action: 'edit_pin', 
            old_pin: oldPin, 
            new_pin: newPin, 
            name: newName
        }, function(res) {
            if(res.error) {
                alert(res.error);
            } else {
                isEditing = false;
                refreshPins();
            }
        }, 'json');
    }

    function togglePin(pin) {
        $.post('control.php', {action: 'toggle', pin: pin}, function(res) {
            // Don't refresh immediately to avoid switch jumping, let the interval handle it or just assume success
            // refreshPins(); 
        });
    }

    function addPin() {
        let pin = $('#newPin').val();
        let name = $('#newPinName').val();
        if(!pin || !name) {
            alert("Please enter pin number and name");
            return;
        }
        $.post('control.php', {action: 'add_pin', pin: pin, name: name}, function(res) {
            if(res.error) {
                alert(res.error);
            } else {
                $('#newPin').val('');
                $('#newPinName').val('');
                refreshPins();
            }
        }, 'json');
    }

    function deletePin(pin) {
        if(confirm("Are you sure you want to remove this pin configuration?")) {
            $.post('control.php', {action: 'delete_pin', pin: pin}, function(res) {
                refreshPins();
            });
        }
    }

    // Initial load
    refreshPins();
    // Auto refresh every 1 second (Fast updates, low load due to caching)
    setInterval(refreshPins, 1000);
    </script>
</body>
</html>