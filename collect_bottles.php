<?php
session_start();
// Reset bottle count when starting fresh
$_SESSION['bottle_count'] = 0;
$_SESSION['verification_tokens'] = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collect Bottles - Bottle WiFi</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
  --bg: #e6f0ff;
  --card: rgba(255,255,255,0.65);
  --accent-start: #3b82f6;
  --accent-end: #06b6d4;
  --text: #1e3a8a;
  --muted: #475569;
  font-family: 'Inter', sans-serif;
}

* { margin:0; padding:0; box-sizing:border-box; }

body {
  min-height:100vh;
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  color: var(--text);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.container {
  background: var(--card);
  padding:2rem;
  border-radius:22px;
  border:1px solid rgba(255,255,255,0.4);
  box-shadow:0 6px 30px rgba(0,0,0,0.08);
  max-width:30rem;
  width:100%;
  backdrop-filter: blur(18px);
  position: relative;
}

.dust-container {
  position: absolute;
  top:0; left:0; right:0; bottom:0;
  overflow:hidden;
  pointer-events:none;
  border-radius:22px;
}

.dust {
  position: absolute;
  width:3px; height:3px;
  background: rgba(96, 165, 250, 0.4);
  border-radius:50%;
}

@keyframes float-up {
  0%{transform:translateY(100%) translateX(0) scale(0);opacity:0;}
  50%{opacity:0.5;}
  100%{transform:translateY(-100%) translateX(var(--tx)) scale(1);opacity:0;}
}

.header {
  text-align: center;
  margin-bottom: 1.5rem;
}

.emoji-row {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.emoji {
  font-size: 1.5rem;
}

.title {
  font-size:2rem;
  font-weight:700;
  background: linear-gradient(90deg, var(--accent-start), var(--accent-end));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 0.5rem;
}

.subtitle {
  color:var(--muted);
  font-size: 0.95rem;
}

.bottle-count-display {
  margin:1.5rem 0;
  padding:1.5rem;
  border-radius:18px;
  background:white;
  border:1px solid #dbeafe;
  box-shadow:0 2px 12px rgba(37,99,235,0.1);
  text-align: center;
}

.count-number {
  font-size:4.5rem;
  font-weight:700;
  background: linear-gradient(90deg, var(--accent-start), var(--accent-end));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  line-height: 1;
  margin-bottom: 0.5rem;
}

.pulse-animation {
  animation: pulse 0.5s ease-out;
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.15); }
  100% { transform: scale(1); }
}

.total-time {
  color: var(--accent-start);
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 0.5rem;
}

.timer-section {
  text-align: center;
  margin-bottom: 1.5rem;
  display: none;
}

.timer-display {
  font-size:2.5rem;
  font-weight: 700;
  background: linear-gradient(90deg, var(--accent-start), var(--accent-end));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 0.5rem;
}

.progress-bar-container {
  width: 100%;
  background: rgba(239, 246, 255, 0.5);
  border-radius: 9999px;
  padding: 2px;
  margin-top: 1rem;
  border: 1px solid rgba(37, 99, 235, 0.2);
}

.progress-bar {
  height:10px;
  border-radius:9999px;
  background: linear-gradient(90deg, var(--accent-start), var(--accent-end));
  transition: width 1s linear;
}

.button {
  width:100%;
  padding:1rem 1.5rem;
  border-radius:14px;
  font-size:1.1rem;
  font-weight:600;
  background: linear-gradient(90deg, var(--accent-start), var(--accent-end));
  color:white;
  border:none;
  cursor:pointer;
  transition:0.2s;
  box-shadow:0 4px 14px rgba(37,99,235,0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-top: 1rem;
}

.button:hover {
  filter: brightness(1.1);
  transform: translateY(-2px);
  box-shadow:0 6px 18px rgba(37,99,235,0.4);
}

.button:disabled {
  background: #9ca3af;
  cursor: not-allowed;
  opacity: 0.6;
  transform: none;
  filter: none;
}

.button-done {
  background: linear-gradient(90deg, var(--accent-start), #1d4ed8);
}

.status-message {
  padding:0.9rem;
  margin-bottom:0.8rem;
  border-radius:14px;
  background:#e0fbea;
  border:1px solid #86efac;
  color:#166534;
  text-align: center;
  font-weight: 500;
  display:none;
}

.waiting-message {
  background:#dbeafe;
  border:1px solid #93c5fd;
  color:var(--accent-start);
}

.error-message {
  display: none;
  background: #fee2e2;
  border: 2px solid #fca5a5;
  color: #991b1b;
  padding: 1rem;
  border-radius: 14px;
  margin-top: 1rem;
  font-size: 0.9rem;
}

.error-message > div:first-child {
  font-weight: 600;
  margin-bottom: 0.5rem;
}

@media (max-width:640px){
  .container{
    padding:1.5rem;
    border-radius:18px;
  }
  .title{
    font-size:1.5rem;
  }
  .emoji{
    font-size:1.25rem;
  }
  .count-number{
    font-size:3.5rem;
  }
  .timer-display{
    font-size:2rem;
  }
  .button{
    padding:0.875rem 1.25rem;
    font-size:1rem;
  }
}
</style>
</head>
<body>
    <div class="container">
        <div class="dust-container"></div>
        
        <div class="header">
            
            <h1 class="title">Bottle WiFi</h1>
            <p class="subtitle" id="headerSubtitle">Drop bottles to earn WiFi time</p>
        </div>

        <div class="bottle-count-display">
            <div class="count-number" id="bottleCount">0</div>
            <p class="subtitle" id="bottlesCollectedText">No Bottles Yet</p>
            <p class="total-time" id="totalTime">0 minutes WiFi</p>
        </div>

        <div id="timerSection" class="timer-section">
            <div class="timer-display" id="timer">30</div>
            <p class="subtitle">Waiting for bottle...</p>
            <div class="progress-bar-container">
                <div id="progressBar" class="progress-bar" style="width: 100%"></div>
            </div>
        </div>

        <div id="statusMessage" class="status-message">
            ✓ Bottle detected!
        </div>

        <div id="waitingMessage" class="status-message waiting-message" style="display: block;">
            🔍 Waiting for bottles...
        </div>

        <button id="doneButton" class="button button-done" disabled>
            <span>Done - Get WiFi</span>
            <span class="emoji">✓</span>
        </button>

        <div id="errorMessage" class="error-message">
            <div>⚠️ Error</div>
            <div id="errorText"></div>
        </div>
    </div>

    <script>
        function createDust() {
            const container = document.querySelector('.dust-container');
            for (let i = 0; i < 30; i++) {
                const dust = document.createElement('div');
                dust.className = 'dust';
                const size = Math.random() * 3 + 1;
                const startX = Math.random() * 100;
                const tx = (Math.random() - 0.5) * 50;
                const duration = Math.random() * 3 + 2;
                const delay = Math.random() * 2;
                
                dust.style.cssText = `
                    left: ${startX}%;
                    width: ${size}px;
                    height: ${size}px;
                    --tx: ${tx}px;
                    animation: float-up ${duration}s ease-in infinite ${delay}s;
                `;
                
                container.appendChild(dust);
            }
        }

        document.addEventListener('DOMContentLoaded', createDust);

        let bottleCount = 0;
        let verificationTokens = [];
        let isDetecting = false;
        let checkIR = null;
        let countdownInterval = null;

        const bottleCountDisplay = document.getElementById('bottleCount');
        const totalTimeDisplay = document.getElementById('totalTime');
        const doneButton = document.getElementById('doneButton');
        const timerSection = document.getElementById('timerSection');
        const timer = document.getElementById('timer');
        const progressBar = document.getElementById('progressBar');
        const statusMessage = document.getElementById('statusMessage');
        const waitingMessage = document.getElementById('waitingMessage');

        // Start automatic detection on page load
        startBottleDetection();

        doneButton.addEventListener('click', function() {
            if (bottleCount === 0) {
                alert('Please insert at least one bottle!');
                return;
            }

            // Redirect to index.php with bottle count and tokens
            const params = new URLSearchParams({
                bottles: bottleCount,
                tokens: verificationTokens.join(',')  // ← Tokens passed here
            });
            window.location.href = 'index.php?' + params.toString();
        });

        function startBottleDetection() {
            if (isDetecting) return;
            
            isDetecting = true;
            statusMessage.style.display = 'none';
            waitingMessage.style.display = 'block';

            // Check for bottle every 500ms
            checkIR = setInterval(async function() {
                try {
                    const res = await fetch('ir.php');
                    const data = await res.json();

                    console.log('IR Response:', data);

                    // Check for errors from sensor
                    if (data.error) {
                        clearInterval(checkIR);
                        clearInterval(countdownInterval);
                        stopDetection();
                        showError('Sensor error: ' + data.error);
                        return;
                    }

                    if (data.detected) {
                        // Bottle detected!
                        verificationTokens.push(data.verification_token);
                        bottleCount++;

                        // Animate count
                        bottleCountDisplay.classList.add('pulse-animation');
                        bottleCountDisplay.textContent = bottleCount;
                        setTimeout(() => {
                            bottleCountDisplay.classList.remove('pulse-animation');
                        }, 500);

                        // Update text for singular/plural
                        const bottlesCollectedText = document.getElementById('bottlesCollectedText');
                        if (bottleCount === 1) {
                            bottlesCollectedText.textContent = 'Bottle Collected';
                        } else {
                            bottlesCollectedText.textContent = 'Bottles Collected';
                        }
                       
                        // Fetch duration and update total time
                        fetch('settings_handler.php')
                            .then(res => res.json())
                            .catch(() => ({ wifi_time: 300 }))
                            .then(settings => {
                                const minutesPerBottle = Math.floor(settings.wifi_time / 60);
                                const totalMinutes = bottleCount * minutesPerBottle;
                                totalTimeDisplay.textContent = `${totalMinutes} minutes WiFi`;
                            });

                        // Show success message briefly
                        waitingMessage.style.display = 'none';
                        statusMessage.style.display = 'block';
                        setTimeout(() => {
                            statusMessage.style.display = 'none';
                            waitingMessage.style.display = 'block';
                        }, 2000);

                        // Log bottle
                        fetch('log_bottle.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'log_bottle' })
                        }).catch(err => console.error('Log error:', err));

                        // Enable done button
                        doneButton.disabled = false;

                        // Wait 2 seconds then continue detecting for next bottle
                        clearInterval(checkIR);
                        setTimeout(() => {
                            stopDetection();
                            startBottleDetection();
                        }, 2000);
                    }
                } catch (e) {
                    console.error('Fetch error:', e);
                }
            }, 500);
        }

        function stopDetection() {
            isDetecting = false;
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            errorText.textContent = message;
            errorDiv.style.display = 'block';
        }
    </script>
</body>
</html>
