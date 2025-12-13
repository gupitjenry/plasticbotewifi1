<?php
/**
 * Session Manager for Per-Device Internet Access Control
 * 
 * Manages session-based WiFi access where:
 * - Devices that drop a bottle get internet access
 * - Devices that just connect without bottle don't get access
 * - Each device session has a unique token, MAC address, and bottle_donated flag
 */

class SessionManager {
    private $sessionFile;
    private $currentTime;
    
    public function __construct() {
        $this->sessionFile = __DIR__ . '/device_sessions.json';
        $this->currentTime = time();
        $this->initializeSessionFile();
    }
    
    /**
     * Initialize session file if it doesn't exist
     */
    private function initializeSessionFile() {
        if (!file_exists($this->sessionFile)) {
            file_put_contents($this->sessionFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Get all sessions
     */
    public function getAllSessions() {
        $content = file_get_contents($this->sessionFile);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * Create a new session when bottle is detected
     * 
     * @param string $verificationToken - Token from bottle detection
     * @param string $clientIP - Client IP address
     * @param string $clientMAC - Client MAC address
     * @param int $durationMinutes - Session duration
     * @return array Session data
     */
    public function createSessionWithBottle($verificationToken, $clientIP, $clientMAC, $durationMinutes = 5) {
        $sessions = $this->getAllSessions();
        
        // Create new session with bottle_donated = true
        $session = [
            'session_id' => $verificationToken,
            'device_mac' => $clientMAC,
            'device_ip' => $clientIP,
            'bottle_donated' => true,  // KEY: Device dropped a bottle
            'created_at' => $this->currentTime,
            'expires_at' => $this->currentTime + ($durationMinutes * 60),
            'duration_minutes' => $durationMinutes,
            'internet_granted' => false,
            'internet_granted_at' => null,
            'last_verified' => $this->currentTime
        ];
        
        $sessions[$verificationToken] = $session;
        $this->saveSessions($sessions);
        
        return $session;
    }
    
    /**
     * Create a session for device that just connected (no bottle yet)
     * 
     * @param string $sessionToken - Unique session token
     * @param string $clientIP - Client IP address  
     * @param string $clientMAC - Client MAC address
     * @return array Session data
     */
    public function createSessionWithoutBottle($sessionToken, $clientIP, $clientMAC) {
        $sessions = $this->getAllSessions();
        
        // Create new session with bottle_donated = false
        $session = [
            'session_id' => $sessionToken,
            'device_mac' => $clientMAC,
            'device_ip' => $clientIP,
            'bottle_donated' => false,  // KEY: Device has NOT dropped a bottle
            'created_at' => $this->currentTime,
            'expires_at' => $this->currentTime + 300,  // 5 minute initial window
            'duration_minutes' => 5,
            'internet_granted' => false,
            'internet_granted_at' => null,
            'last_verified' => $this->currentTime
        ];
        
        $sessions[$sessionToken] = $session;
        $this->saveSessions($sessions);
        
        return $session;
    }
    
    /**
     * Mark WiFi as granted for a session
     * Only can be granted if bottle_donated is true
     * 
     * @param string $verificationToken - Session token
     * @param string $clientMAC - Client MAC to verify
     * @return bool Success status
     */
    public function grantInternetAccess($verificationToken, $clientMAC) {
        $sessions = $this->getAllSessions();
        
        // Check if session exists
        if (!isset($sessions[$verificationToken])) {
            return false;
        }
        
        $session = $sessions[$verificationToken];
        
        // CRITICAL CHECK: Only grant if bottle was actually donated
        if (!$session['bottle_donated']) {
            return false;
        }
        
        // Verify MAC address matches
        if ($session['device_mac'] !== $clientMAC) {
            return false;
        }
        
        // Check if session is still valid
        if ($session['expires_at'] < $this->currentTime) {
            return false;
        }
        
        // Mark as granted
        $sessions[$verificationToken]['internet_granted'] = true;
        $sessions[$verificationToken]['internet_granted_at'] = $this->currentTime;
        $sessions[$verificationToken]['last_verified'] = $this->currentTime;
        
        $this->saveSessions($sessions);
        return true;
    }
    
    /**
     * Check if device has active internet session with bottle
     * 
     * @param string $clientMAC - Client MAC address
     * @return array|false Session data if valid, false otherwise
     */
    public function getActiveSessionByMAC($clientMAC) {
        $sessions = $this->getAllSessions();
        
        foreach ($sessions as $session) {
            // Check if session is active and has bottle
            if ($session['device_mac'] === $clientMAC && 
                $session['bottle_donated'] === true &&
                $session['internet_granted'] === true &&
                $session['expires_at'] > $this->currentTime) {
                return $session;
            }
        }
        
        return false;
    }
    
    /**
     * Check if device has ANY active session (bottle or not)
     * 
     * @param string $clientMAC - Client MAC address
     * @return array|false Session data if exists, false otherwise
     */
    public function getActiveSessionByMACRegardlessOfBottle($clientMAC) {
        $sessions = $this->getAllSessions();
        
        foreach ($sessions as $session) {
            if ($session['device_mac'] === $clientMAC && 
                $session['expires_at'] > $this->currentTime) {
                return $session;
            }
        }
        
        return false;
    }
    
    /**
     * Revoke session (when time expires or manually)
     * 
     * @param string $verificationToken - Session token
     * @return bool Success status
     */
    public function revokeSession($verificationToken) {
        $sessions = $this->getAllSessions();
        
        if (isset($sessions[$verificationToken])) {
            $sessions[$verificationToken]['internet_granted'] = false;
            $sessions[$verificationToken]['expires_at'] = $this->currentTime;
            $this->saveSessions($sessions);
            return true;
        }
        
        return false;
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        $sessions = $this->getAllSessions();
        $cleaned = [];
        
        foreach ($sessions as $token => $session) {
            // Keep sessions for 1 hour after expiry for logging
            if ($session['expires_at'] > ($this->currentTime - 3600)) {
                $cleaned[$token] = $session;
            }
        }
        
        $this->saveSessions($cleaned);
    }
    
    /**
     * Get session statistics
     */
    public function getStats() {
        $sessions = $this->getAllSessions();
        
        $activeSessions = 0;
        $devicesWithBottle = 0;
        $devicesWithoutBottle = 0;
        $internetGrantedCount = 0;
        
        foreach ($sessions as $session) {
            if ($session['expires_at'] > $this->currentTime) {
                $activeSessions++;
                
                if ($session['bottle_donated']) {
                    $devicesWithBottle++;
                    if ($session['internet_granted']) {
                        $internetGrantedCount++;
                    }
                } else {
                    $devicesWithoutBottle++;
                }
            }
        }
        
        return [
            'total_sessions' => count($sessions),
            'active_sessions' => $activeSessions,
            'devices_with_bottle' => $devicesWithBottle,
            'devices_without_bottle' => $devicesWithoutBottle,
            'internet_granted' => $internetGrantedCount,
            'devices_blocked' => $devicesWithoutBottle + ($devicesWithBottle - $internetGrantedCount)
        ];
    }
    
    /**
     * Save sessions to file
     */
    private function saveSessions($sessions) {
        file_put_contents($this->sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
    }
}
?>

<?php
/**
 * Session Manager for Per-Device Internet Access Control
 * 
 * Manages session-based WiFi access where:
 * - Devices that drop a bottle get internet access
 * - Devices that just connect without bottle don't get access
 * - Each device session has a unique token, MAC address, and bottle_donated flag
 */

class SessionManager {
    private $sessionFile;
    private $currentTime;
    
    public function __construct() {
        $this->sessionFile = __DIR__ . '/device_sessions.json';
        $this->currentTime = time();
        $this->initializeSessionFile();
    }
    
    /**
     * Initialize session file if it doesn't exist
     */
    private function initializeSessionFile() {
        if (!file_exists($this->sessionFile)) {
            file_put_contents($this->sessionFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Get all sessions
     */
    public function getAllSessions() {
        $content = file_get_contents($this->sessionFile);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * Create a new session when bottle is detected
     * 
     * @param string $verificationToken - Token from bottle detection
     * @param string $clientIP - Client IP address
     * @param string $clientMAC - Client MAC address
     * @param int $durationMinutes - Session duration
     * @return array Session data
     */
    public function createSessionWithBottle($verificationToken, $clientIP, $clientMAC, $durationMinutes = 5) {
        $sessions = $this->getAllSessions();
        
        // Create new session with bottle_donated = true
        $session = [
            'session_id' => $verificationToken,
            'device_mac' => $clientMAC,
            'device_ip' => $clientIP,
            'bottle_donated' => true,  // KEY: Device dropped a bottle
            'created_at' => $this->currentTime,
            'expires_at' => $this->currentTime + ($durationMinutes * 60),
            'duration_minutes' => $durationMinutes,
            'internet_granted' => false,
            'internet_granted_at' => null,
            'last_verified' => $this->currentTime
        ];
        
        $sessions[$verificationToken] = $session;
        $this->saveSessions($sessions);
        
        return $session;
    }
    
    /**
     * Create a session for device that just connected (no bottle yet)
     * 
     * @param string $sessionToken - Unique session token
     * @param string $clientIP - Client IP address  
     * @param string $clientMAC - Client MAC address
     * @return array Session data
     */
    public function createSessionWithoutBottle($sessionToken, $clientIP, $clientMAC) {
        $sessions = $this->getAllSessions();
        
        // Create new session with bottle_donated = false
        $session = [
            'session_id' => $sessionToken,
            'device_mac' => $clientMAC,
            'device_ip' => $clientIP,
            'bottle_donated' => false,  // KEY: Device has NOT dropped a bottle
            'created_at' => $this->currentTime,
            'expires_at' => $this->currentTime + 300,  // 5 minute initial window
            'duration_minutes' => 5,
            'internet_granted' => false,
            'internet_granted_at' => null,
            'last_verified' => $this->currentTime
        ];
        
        $sessions[$sessionToken] = $session;
        $this->saveSessions($sessions);
        
        return $session;
    }
    
    /**
     * Mark WiFi as granted for a session
     * Only can be granted if bottle_donated is true
     * 
     * @param string $verificationToken - Session token
     * @param string $clientMAC - Client MAC to verify
     * @return bool Success status
     */
    public function grantInternetAccess($verificationToken, $clientMAC) {
        $sessions = $this->getAllSessions();
        
        // Check if session exists
        if (!isset($sessions[$verificationToken])) {
            return false;
        }
        
        $session = $sessions[$verificationToken];
        
        // CRITICAL CHECK: Only grant if bottle was actually donated
        if (!$session['bottle_donated']) {
            return false;
        }
        
        // Verify MAC address matches
        if ($session['device_mac'] !== $clientMAC) {
            return false;
        }
        
        // Check if session is still valid
        if ($session['expires_at'] < $this->currentTime) {
            return false;
        }
        
        // Mark as granted
        $sessions[$verificationToken]['internet_granted'] = true;
        $sessions[$verificationToken]['internet_granted_at'] = $this->currentTime;
        $sessions[$verificationToken]['last_verified'] = $this->currentTime;
        
        $this->saveSessions($sessions);
        return true;
    }
    
    /**
     * Check if device has active internet session with bottle
     * 
     * @param string $clientMAC - Client MAC address
     * @return array|false Session data if valid, false otherwise
     */
    public function getActiveSessionByMAC($clientMAC) {
        $sessions = $this->getAllSessions();
        
        foreach ($sessions as $session) {
            // Check if session is active and has bottle
            if ($session['device_mac'] === $clientMAC && 
                $session['bottle_donated'] === true &&
                $session['internet_granted'] === true &&
                $session['expires_at'] > $this->currentTime) {
                return $session;
            }
        }
        
        return false;
    }
    
    /**
     * Check if device has ANY active session (bottle or not)
     * 
     * @param string $clientMAC - Client MAC address
     * @return array|false Session data if exists, false otherwise
     */
    public function getActiveSessionByMACRegardlessOfBottle($clientMAC) {
        $sessions = $this->getAllSessions();
        
        foreach ($sessions as $session) {
            if ($session['device_mac'] === $clientMAC && 
                $session['expires_at'] > $this->currentTime) {
                return $session;
            }
        }
        
        return false;
    }
    
    /**
     * Revoke session (when time expires or manually)
     * 
     * @param string $verificationToken - Session token
     * @return bool Success status
     */
    public function revokeSession($verificationToken) {
        $sessions = $this->getAllSessions();
        
        if (isset($sessions[$verificationToken])) {
            $sessions[$verificationToken]['internet_granted'] = false;
            $sessions[$verificationToken]['expires_at'] = $this->currentTime;
            $this->saveSessions($sessions);
            return true;
        }
        
        return false;
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        $sessions = $this->getAllSessions();
        $cleaned = [];
        
        foreach ($sessions as $token => $session) {
            // Keep sessions for 1 hour after expiry for logging
            if ($session['expires_at'] > ($this->currentTime - 3600)) {
                $cleaned[$token] = $session;
            }
        }
        
        $this->saveSessions($cleaned);
    }
    
    /**
     * Get session statistics
     */
    public function getStats() {
        $sessions = $this->getAllSessions();
        
        $activeSessions = 0;
        $devicesWithBottle = 0;
        $devicesWithoutBottle = 0;
        $internetGrantedCount = 0;
        
        foreach ($sessions as $session) {
            if ($session['expires_at'] > $this->currentTime) {
                $activeSessions++;
                
                if ($session['bottle_donated']) {
                    $devicesWithBottle++;
                    if ($session['internet_granted']) {
                        $internetGrantedCount++;
                    }
                } else {
                    $devicesWithoutBottle++;
                }
            }
        }
        
        return [
            'total_sessions' => count($sessions),
            'active_sessions' => $activeSessions,
            'devices_with_bottle' => $devicesWithBottle,
            'devices_without_bottle' => $devicesWithoutBottle,
            'internet_granted' => $internetGrantedCount,
            'devices_blocked' => $devicesWithoutBottle + ($devicesWithBottle - $internetGrantedCount)
        ];
    }
    
    /**
     * Save sessions to file
     */
    private function saveSessions($sessions) {
        file_put_contents($this->sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
    }
}
?>
