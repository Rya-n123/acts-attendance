<?php
// scanner/index.php
require_once '../config/session.php';
requireScanner(); // Ensure user is logged in
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner - Acts Attendance</title>
    <!-- CSS is now fully loaded from style.css -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="display: block; height: auto;">

    <div class="scanner-header">
        <div>
            <h3>Acts Scanner</h3>
            <p style="margin: 3px 0 0 0; font-size: 12px; opacity: 0.8;">Logged in as: <b><?php echo htmlspecialchars($_SESSION['username']); ?></b></p>
        </div>
        <a href="../logout.php" style="color: var(--acts-white); font-size: 13px; font-weight: bold; text-decoration: none; padding: 5px 10px; border: 1px solid rgba(255,255,255,0.5); border-radius: 5px;">Logout</a>
    </div>

    <div class="scanner-container">
        
        <!-- LIVE CLOCK PARA SA SCANNER -->
        <div style="text-align: center; background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; border-top: 4px solid var(--acts-green);">
            <p style="margin: 0; font-size: 12px; color: #888; font-weight: bold;">CURRENT TIME</p>
            <h1 id="scanner-clock" style="margin: 5px 0 0 0; color: var(--acts-green); font-size: 34px; letter-spacing: 1px;">--:--:-- --</h1>
        </div>

        <!-- Step 1: Action Selection -->
        <p style="text-align: center; font-size: 14px; font-weight: bold; color: var(--acts-green); margin-bottom: 8px;">Step 1: Select Action</p>
        <div class="action-toggle">
            <button id="btn-time-in" class="btn-toggle active" onclick="setAction('time_in')">🕒 TIME IN</button>
            <button id="btn-time-out" class="btn-toggle" onclick="setAction('time_out')">🚪 TIME OUT</button>
        </div>

        <!-- Step 2: Camera Scanner -->
        <p style="text-align: center; font-size: 14px; font-weight: bold; color: var(--acts-green); margin-bottom: 8px;">Step 2: Scan ID Barcode/QR</p>
        <div id="reader"></div>

        <!-- Notification Box -->
        <div id="scan-result"></div>

        <!-- Fallback: Manual Search -->
        <div class="manual-search-box">
            <h4 style="margin-top: 0; color: var(--acts-green); font-size: 16px;">Manual Entry</h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">If the ID cannot be scanned, search the student here.</p>
            <input type="text" id="manual-input" placeholder="e.g. SHS10769 or Juan Dela Cruz" style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 12px; box-sizing: border-box; font-size: 15px;">
            
            <!-- Dito lalabas ang mga instant tap buttons kapag may multiple results -->
            <div id="search-results-list" style="display: none; margin-bottom: 12px; display: flex; flex-direction: column; gap: 8px;"></div>
            
            <!-- <button class="btn-search" onclick="manualSearch()" style="width: 100%; padding: 14px; background-color: var(--acts-green); color: var(--acts-white); border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer;">Search</button> -->
        </div>
    </div>

    <script>
        // Default action
        let currentAction = 'time_in';

        // Function to handle toggle buttons
        function setAction(action) {
            currentAction = action;
            
            if(action === 'time_in') {
                document.getElementById('btn-time-in').classList.add('active');
                document.getElementById('btn-time-out').classList.remove('active');
            } else {
                document.getElementById('btn-time-out').classList.add('active');
                document.getElementById('btn-time-in').classList.remove('active');
            }
        }

        let html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);

        function onScanSuccess(decodedText, decodedResult) {
            safePauseScanner();
            processAttendance(decodedText);
        }

        function onScanFailure(error) {
            // Normal background scanning errors
        }

        html5QrcodeScanner.render(onScanSuccess, onScanFailure);

        // ==========================================
        // DEFENSIVE PROGRAMMING: SAFE SCANNER CONTROLS
        // ==========================================
        function safePauseScanner() {
            try { html5QrcodeScanner.pause(); } catch(err) { /* Ignore kung naka-pause na */ }
        }

        function safeResumeScanner() {
            try { html5QrcodeScanner.resume(); } catch(err) { /* Ignore kung tumatakbo na */ }
        }

        function manualSearch() {
            let inputValue = document.getElementById('manual-input').value;
            if(inputValue.trim() === '') {
                alert("Please enter a value in the search box.");
                return;
            }
            processAttendance(inputValue);
        }

        function processAttendance(studentData, step = 'check') {
            if (!studentData || studentData.trim() === '') return;

            let resultBox = document.getElementById('scan-result');
            let searchResultsList = document.getElementById('search-results-list');
            
            searchResultsList.innerHTML = '';
            
            if (step === 'check') {
                searchResultsList.style.display = 'none';
                resultBox.style.display = 'block';
                resultBox.style.backgroundColor = '#e2e3e5';
                resultBox.style.color = '#383d41';
                resultBox.innerHTML = "Searching: " + studentData + "...";
            }

            fetch('process_scan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'student_data=' + encodeURIComponent(studentData) + '&scan_type=' + encodeURIComponent(currentAction) + '&step=' + step
            })
            .then(response => response.json())
            .then(data => {
                resultBox.style.display = 'none';

                if (data.status === 'multiple') {
                    searchResultsList.style.display = 'flex';
                    
                    data.students.forEach(s => {
                        let btn = document.createElement('button');
                        btn.style.cssText = 'text-align: left; background: #f8f9fa; border: 2px solid var(--acts-green); padding: 10px 15px; border-radius: 8px; cursor: pointer; transition: 0.2s;';
                        btn.innerHTML = `<b style="color: var(--acts-green); font-size: 15px;">${s.name}</b><br><span style="font-size: 12px; color: #666;">${s.tag}</span>`;
                        
                        btn.onclick = function() {
                            searchResultsList.style.display = 'none';
                            processAttendance(s.student_number, 'check');
                        };
                        
                        btn.onmouseover = function() { this.style.backgroundColor = '#e9ecef'; };
                        btn.onmouseout = function() { this.style.backgroundColor = '#f8f9fa'; };
                        
                        searchResultsList.appendChild(btn);
                    });
                    
                    safePauseScanner(); // Safe Pause
                }
                else if (data.status === 'confirm') {
                    let actionText = (currentAction === 'time_in') ? 'TIME IN' : 'TIME OUT';
                    
                    Swal.fire({
                        title: 'Confirm ' + actionText,
                        html: `Are you sure you want to record attendance for:<br><br><b style="font-size: 22px; color: var(--acts-green);">${data.name}</b><br>(${data.section})?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#033500',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, Record it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            processAttendance(data.student_number, 'record');
                        } else {
                            safeResumeScanner(); // Safe Resume
                        }
                    });
                } 
                else if (data.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    document.getElementById('manual-input').value = '';
                    setTimeout(() => { safeResumeScanner(); }, 2000); // Safe Resume
                } 
                else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#033500'
                    });
                    setTimeout(() => { safeResumeScanner(); }, 2500); // Safe Resume
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Server error. Please try again.', 'error');
                setTimeout(() => { safeResumeScanner(); }, 3000); // Safe Resume
            });
        }

        function updateScannerClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            });
            document.getElementById('scanner-clock').innerText = timeString;
        }
        
        setInterval(updateScannerClock, 1000);
        updateScannerClock();

        let debounceTimer;
        const searchInput = document.getElementById('manual-input');

        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            const searchResultsList = document.getElementById('search-results-list');

            clearTimeout(debounceTimer);

            if (query.length < 3) {
                searchResultsList.style.display = 'none';
                searchResultsList.innerHTML = '';
                safeResumeScanner(); // Safe Resume
                return;
            }

            debounceTimer = setTimeout(() => {
                processAttendance(query, 'live_search');
            }, 300); 
        });
    </script>
</body>
</html>