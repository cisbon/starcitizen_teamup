# Fixing Bot Protection/DDoS Protection Issues

## Problem

Your server is returning a JavaScript challenge instead of JSON responses:

```html
<html><body><script type="text/javascript" src="/aes.js" ></script>...
```

This is a **DDoS/bot protection service** (like Cloudflare Under Attack Mode, Sucuri, or similar) that blocks API calls because they can't execute JavaScript challenges.

## Solutions (Choose One)

### Solution 1: Disable Bot Protection for /api/ Directory (RECOMMENDED)

If using **Cloudflare**:

1. Log into your Cloudflare dashboard
2. Go to **Firewall** → **Firewall Rules**
3. Create a new rule:
   - **Name**: Allow API requests
   - **Expression**:
     ```
     (http.request.uri.path contains "/api/")
     ```
   - **Action**: Allow
4. Save and deploy

Alternatively in Cloudflare:
1. Go to **Firewall** → **Settings**
2. Change **Security Level** from "I'm Under Attack" to "Medium" or "Low"
3. Or add a **Page Rule** for `starcitizen.gamer.gd/api/*`:
   - Set **Security Level** to "Essentially Off"
   - Set **Browser Integrity Check** to "Off"

### Solution 2: Create a Page Rule Exception

If using **Cloudflare Page Rules**:

1. Go to **Rules** → **Page Rules**
2. Create new rule for: `starcitizen.gamer.gd/api/*`
3. Add settings:
   - Security Level: Off
   - Browser Integrity Check: Off
   - Cache Level: Bypass
4. Save

### Solution 3: Use a Subdomain Without Protection

Create a subdomain specifically for the API:

1. Create subdomain: `api.starcitizen.gamer.gd`
2. Point it to the same directory
3. Disable DDoS protection for this subdomain
4. Update frontend `API_BASE_URL` to: `https://api.starcitizen.gamer.gd`

### Solution 4: Whitelist Your Domain

If using a hosting control panel bot protection:

1. Log into your hosting control panel (cPanel, Plesk, etc.)
2. Find DDoS or Bot Protection settings
3. Add your domain to whitelist
4. Or disable bot protection for `/api/` path

### Solution 5: Contact Your Hosting Provider

If none of the above work:

1. Contact your hosting provider support
2. Ask them to disable bot protection/challenge for:
   - Path: `/api/`
   - Or entire domain
3. Explain you need to allow API requests

## Testing After Changes

After applying any solution, test with curl:

```bash
# Test load groups
curl https://starcitizen.gamer.gd/api/load_groups.php

# Expected response (JSON, not HTML):
{"success":true,"groups":[]}
```

If you still see HTML with JavaScript, the protection is still active.

## Alternative: Proxy Solution

If you can't disable the protection, create a proxy endpoint without protection:

1. Create new subdomain or directory without protection
2. Place a simple PHP proxy that forwards requests to your protected API
3. Update frontend to use the proxy URL

## Important Notes

- **Don't disable protection for the entire site** - only for `/api/`
- Keep DDoS protection enabled for the main website
- The API has its own rate limiting, so it's safe to bypass bot challenges
- After changes, clear your Cloudflare cache if applicable

## Quick Check

Run this command to see if protection is active:

```bash
curl -I https://starcitizen.gamer.gd/api/load_groups.php
```

**If protected**, you'll see:
- Content-Type: text/html (wrong - should be application/json)
- Small content-length like 866 bytes (JavaScript challenge)

**If working**, you'll see:
- Content-Type: application/json
- Larger content-length (actual data)

## Configuration File Locations

After disabling protection, make sure these files are in place:

- `/api/.htaccess` - Apache configuration
- `/api/config.php` - Database credentials (configured)
- `/api/*.php` - API endpoints

## Need Help?

If you're using a specific service and need help:
- **Cloudflare**: Check Firewall Rules and Page Rules
- **Sucuri**: Contact support to whitelist /api/
- **cPanel**: Check "ModSecurity" or "Firewall" sections
- **Other**: Contact your hosting provider's support team
