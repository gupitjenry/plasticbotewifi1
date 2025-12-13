// Function to load and update settings
async function loadSettings() {
    try {
        const response = await fetch('/save_settings.php');
        const settings = await response.json();
        
        // Update minutes per bottle display
        const minutesPerBottle = settings.minutesPerBottle;
        const bottleRatioElements = document.querySelectorAll('.bottle-ratio');
        bottleRatioElements.forEach(element => {
            element.textContent = `${minutesPerBottle} minutes = 1 bottle`;
        });
        
        // Update any time displays that show bottle equivalents
        updateTimeDisplays(settings.minutesPerBottle);
    } catch (error) {
        console.error('Error loading settings:', error);
    }
}

// Function to update time displays with bottle equivalents
function updateTimeDisplays(minutesPerBottle) {
    const timeDisplays = document.querySelectorAll('.time-display');
    timeDisplays.forEach(display => {
        const minutes = parseInt(display.dataset.minutes);
        if (!isNaN(minutes)) {
            const bottles = Math.round(minutes / minutesPerBottle);
            display.querySelector('.bottle-count').textContent = 
                `${bottles} bottle${bottles !== 1 ? 's' : ''} of water`;
        }
    });
}

// Load settings initially
loadSettings();

// Refresh settings every minute to catch updates
setInterval(loadSettings, 60000);