<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Light Pollution Monitoring System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        
        .level-low { color: #4CAF50; }
        .level-moderate { color: #FF9800; }
        .level-high { color: #F44336; }
        
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            height: 400px;
        }
        
        .data-table {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .level-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
        }
        
        .badge-low { background-color: #4CAF50; }
        .badge-moderate { background-color: #FF9800; }
        .badge-high { background-color: #F44336; }
        
        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 20px;
            transition: background 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: #5a6fd8;
        }
        
        .auto-refresh {
            display: inline-block;
            margin-left: 10px;
            color: white;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-online { background-color: #4CAF50; }
        .status-offline { background-color: #F44336; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Light Pollution Monitoring System</h1>
            <p>Real-time environmental monitoring with ESP32 & BH1750</p>
        </div>
        
        <button class="refresh-btn" onclick="refreshData()">Refresh Data</button>
        <span class="auto-refresh">
            <span class="status-indicator" id="statusIndicator"></span>
            <span id="statusText">Checking...</span>
            <span id="lastUpdate"></span>
        </span>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value level-low" id="currentLux">--</div>
                <div class="stat-label">Current Lux</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="currentLevel">--</div>
                <div class="stat-label">Pollution Level</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="totalReadings">--</div>
                <div class="stat-label">Total Readings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="avgLux">--</div>
                <div class="stat-label">Average Lux</div>
            </div>
        </div>
        
        <div class="chart-container">
            <h2>Light Trend (20 Readings)</h2>
            <canvas id="luxChart"></canvas>
        </div>
        
        <div class="data-table">
            <h2>Recent Sensor Data</h2>
            <table id="dataTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Lux Value</th>
                        <th>Pollution Level</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                    <!-- Data will be populated here -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let chart;
        
        function initChart() {
            const ctx = document.getElementById('luxChart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Light Intensity (Lux)',
                        data: [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Lux'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Time'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        }
        
        async function fetchData() {
            try {
                const response = await fetch('api/get_data.php');
                const data = await response.json();
                
                // Check if we have actual sensor data (not just empty or old data)
                if (data.status === 'success' && data.current_lux > 0 && data.recent_data.length > 0) {
                    // Check if data is recent (within last 5 minutes)
                    const lastReading = new Date(data.recent_data[0].created_at);
                    const now = new Date();
                    const timeDiff = (now - lastReading) / 1000 / 60; // minutes
                    
                    if (timeDiff <= 5) {
                        // Fresh data - system is online
                        updateDashboard(data);
                        updateStatus(true);
                    } else {
                        // Stale data - system is offline
                        updateStatus(false);
                        showOfflineMessage();
                    }
                } else {
                    // No valid data - system is offline
                    updateStatus(false);
                    showOfflineMessage();
                }
            } catch (error) {
                console.error('Error fetching data:', error);
                updateStatus(false);
                showOfflineMessage();
            }
        }
        
        function updateDashboard(data) {
            // Update stats
            document.getElementById('currentLux').textContent = data.current_lux.toFixed(1);
            document.getElementById('currentLevel').textContent = data.current_level;
            document.getElementById('totalReadings').textContent = data.total_readings;
            document.getElementById('avgLux').textContent = data.average_lux.toFixed(1);
            
            // Update level colors
            const levelElement = document.getElementById('currentLevel');
            levelElement.className = 'stat-value';
            if (data.current_level === 'Low') {
                levelElement.classList.add('level-low');
            } else if (data.current_level === 'Moderate') {
                levelElement.classList.add('level-moderate');
            } else {
                levelElement.classList.add('level-high');
            }
            
            // Update chart
            updateChart(data.chart_data);
            
            // Update table
            updateTable(data.recent_data);
            
            // Update last update time
            document.getElementById('lastUpdate').textContent = 'Last updated: ' + new Date().toLocaleTimeString();
        }
        
        function updateChart(chartData) {
            const labels = chartData.map(item => new Date(item.created_at).toLocaleTimeString());
            const values = chartData.map(item => item.lux);
            
            chart.data.labels = labels;
            chart.data.datasets[0].data = values;
            chart.update();
        }
        
        function updateTable(tableData) {
            const tbody = document.getElementById('dataTableBody');
            tbody.innerHTML = '';
            
            tableData.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.id}</td>
                    <td>${row.lux.toFixed(1)}</td>
                    <td><span class="level-badge badge-${row.level.toLowerCase()}">${row.level}</span></td>
                    <td>${new Date(row.created_at).toLocaleString()}</td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        function updateStatus(isOnline) {
            const indicator = document.getElementById('statusIndicator');
            const text = document.getElementById('statusText');
            
            if (isOnline) {
                indicator.className = 'status-indicator status-online';
                text.textContent = 'System Online';
            } else {
                indicator.className = 'status-indicator status-offline';
                text.textContent = 'System Offline';
            }
        }
        
        function showOfflineMessage() {
            // Clear dashboard values
            document.getElementById('currentLux').textContent = '--';
            document.getElementById('currentLevel').textContent = '--';
            document.getElementById('totalReadings').textContent = '--';
            document.getElementById('avgLux').textContent = '--';
            
            // Clear chart
            if (chart) {
                chart.data.labels = [];
                chart.data.datasets[0].data = [];
                chart.update();
            }
            
            // Clear table
            const tbody = document.getElementById('dataTableBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #666; padding: 20px;">No sensor data available - ESP32 or BH1750 sensor not connected</td></tr>';
            
            // Update last update time
            document.getElementById('lastUpdate').textContent = 'Last updated: ' + new Date().toLocaleTimeString();
        }
        
        function refreshData() {
            fetchData();
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
            fetchData();
            
            // Auto-refresh every 30 seconds
            setInterval(fetchData, 30000);
        });
    </script>
</body>
</html>
