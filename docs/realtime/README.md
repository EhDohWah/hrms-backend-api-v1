# Real-Time Communication & WebSockets

**Location:** `/docs/realtime/`  
**Purpose:** Documentation for Laravel Reverb WebSocket server and real-time event broadcasting

---

## 📄 Files in This Folder

### Setup & Quick Start
- **[QUICK_START_REVERB.md](./QUICK_START_REVERB.md)**
  - Quick start guide for enabling real-time permission updates
  - Step-by-step setup instructions
  - Expected output and verification
  - 3-step setup process

### Debugging & Troubleshooting
- **[REVERB_DEBUG_FINDINGS.md](./REVERB_DEBUG_FINDINGS.md)**
  - Debugging findings for WebSocket connectivity
  - What's working and what's not
  - Common issues and solutions
  - Connection diagnostics

- **[WEBSOCKET_PERMISSION_FIX.md](./WEBSOCKET_PERMISSION_FIX.md)**
  - Diagnostic report for permission update issues
  - Root cause analysis
  - Complete fix implementation
  - Broadcast routes configuration

---

## 🚀 Quick Start

**To enable real-time features:**

1. Start Reverb server:
   ```bash
   php artisan reverb:start
   ```

2. Ensure `.env` is configured:
   ```
   BROADCAST_DRIVER=reverb
   REVERB_APP_ID=local
   REVERB_APP_KEY=local
   REVERB_APP_SECRET=local
   REVERB_HOST=127.0.0.1
   REVERB_PORT=8081
   REVERB_SCHEME=http
   ```

3. Test real-time permission updates

---

## 🔍 Common Issues

### Events Not Reaching Frontend
- Check if Reverb server is running
- Verify broadcast routes are in `routes/api.php`
- Ensure auth:sanctum middleware is applied
- Check frontend Echo configuration

### Connection Failures
- Verify Reverb server is accessible
- Check CORS settings
- Verify credentials match between frontend/backend

---

## 🎯 Use Cases

### Real-Time Permission Updates
Users receive instant permission changes without logout/login when admin modifies their permissions.

### Broadcasting Events
- User permission updates
- Notification delivery
- Real-time data updates
- Status changes

---

## 📚 Related Documentation

- [User Management](../user-management/) - Permission system
- [General/Architecture](../general/) - System architecture
- [Notifications](../notifications/) - Notification system

---

**Last Updated:** January 8, 2026  
**Technology:** Laravel Reverb v1, WebSockets, Broadcasting  


