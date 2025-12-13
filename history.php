<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History — BottleWifi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <div class="dust-container"></div>
    <div class="sidebar-backdrop"></div>
    
    <!-- Mobile Header -->
    <header class="mobile-header">
        <button class="mobile-menu-trigger" aria-label="Open Menu">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <div class="logo">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
            </svg>
            <span>BottleWifi</span>
        </div>
    </header>

    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Include your sidebar navigation -->
        </aside>

        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="dashboard.php" class="back-button">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Dashboard
                    </a>
                    <h2 class="welcome">Connection History</h2>
                </div>
                <div class="date" id="current-date"></div>
            </header>

            <div class="history-filters">
                <select class="filter-select">
                    <option>Last 24 Hours</option>
                    <option>Last 7 Days</option>
                    <option>Last 30 Days</option>
                    <option>Custom Range</option>
                </select>
                <input type="search" placeholder="Search devices..." class="search-input">
            </div>

            <div class="table-container">
                <table class="history-table responsive-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Device</th>
                            <th>Event</th>
                            <th>Data Used</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic content will be loaded here -->
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="dashboard.js"></script>
    <script src="responsive.js"></script>
    <script src="history.js"></script>
</body>
</html>