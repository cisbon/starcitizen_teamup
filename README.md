# Star Citizen Team Up - Secure LFG Platform

A production-ready, security-hardened single-page application for Star Citizen players to form groups for in-game activities.

## Features

- Create and join groups for various Star Citizen activities
- Real-time group updates
- Automatic expiration management
- Mobile-responsive design
- Comprehensive security measures

## Security Features

This application implements **multiple layers of security** to protect against common web attacks:

### 1. Frontend Security

#### Input Validation & Sanitization
- **Handle validation**: 3-50 characters, alphanumeric + underscores/dashes only
- **Title validation**: 3-100 characters
- **Description validation**: Maximum 500 characters
- **Activity type validation**: Whitelist of allowed values
- **Max players validation**: 2-50 players range
- **UUID validation**: Prevents injection of malicious group IDs

#### XSS Protection
- All user inputs are sanitized before database insertion
- HTML escaping on all displayed content
- Control character filtering
- CSP headers to restrict script execution

#### Rate Limiting (Client-Side)
- **Group creation**: Maximum 3 attempts per minute
- **Group joining**: Maximum 10 attempts per minute
- Sliding window implementation prevents abuse

#### Security Headers
- Content Security Policy (CSP)
- X-Frame-Options: DENY (prevents clickjacking)
- X-Content-Type-Options: nosniff
- Referrer-Policy: strict-origin-when-cross-origin
- Subresource Integrity (SRI) for CDN resources

#### Additional Frontend Protections
- `novalidate` forms with custom JavaScript validation
- Button disable states during operations (prevents double-submission)
- `rel="noopener noreferrer"` on external links
- `autocomplete="off"` on sensitive inputs
- Word-wrap on user content (prevents layout breaking)

### 2. Database Security

#### Row Level Security (RLS)
- **Groups**: Only open/full groups visible to users
- **Updates**: Strictly limited to status changes only
- **Immutable fields**: Creator, title, activity type cannot be modified
- **Members**: Insert validation ensures group is open and not full

#### Data Validation Constraints
```sql
- valid_creator_handle: Length and pattern validation
- valid_activity_type: Whitelist enforcement
- valid_title: Length constraints
- valid_description: Length constraints
- valid_max_players: Range validation (2-50)
- valid_status: Whitelist of allowed statuses
- unique_group_member: Prevents duplicate memberships
```

#### Performance & Integrity
- Indexes on frequently queried columns
- Cascading deletes for referential integrity
- Foreign key constraints
- Automatic cleanup of expired groups

### 3. Application Logic Security

#### Race Condition Prevention
- Unique constraint on (group_id, player_handle)
- Status checks before member insertion
- Optimistic locking with status verification
- Error code 23505 handling for duplicate keys

#### Data Integrity
- Member count verification before allowing joins
- Group status verification before operations
- Automatic closure of expired groups
- 100 group display limit (prevents performance issues)

#### Error Handling
- No sensitive information exposed in error messages
- Generic user-facing error messages
- Detailed logging to console for debugging
- Graceful degradation on failures

## Setup Instructions

### Prerequisites
- A Supabase account ([supabase.com](https://supabase.com))
- GitHub Pages or any static hosting service

### Step 1: Set Up Supabase Database

1. Log in to your Supabase dashboard
2. Create a new project or select an existing one
3. Go to the **SQL Editor**
4. Copy the entire contents of `database_schema_secure.sql`
5. Paste and run the SQL script
6. Verify the setup by checking the verification queries at the bottom

### Step 2: Configure the Application

1. Open `index.html` in a text editor
2. Find these lines near the top of the `<script>` section:
   ```javascript
   const SUPABASE_URL = 'YOUR_SUPABASE_URL_HERE';
   const SUPABASE_ANON_KEY = 'YOUR_SUPABASE_ANON_KEY_HERE';
   ```
3. Replace with your actual Supabase credentials:
   - Go to your Supabase project settings
   - Navigate to **API** section
   - Copy the **Project URL** and paste it as `SUPABASE_URL`
   - Copy the **anon public** key and paste it as `SUPABASE_ANON_KEY`

### Step 3: Deploy

#### Option A: GitHub Pages
1. Push `index.html` to your GitHub repository
2. Go to repository Settings → Pages
3. Select your branch and save
4. Your site will be live at `https://yourusername.github.io/repository-name`

#### Option B: Other Static Hosting
- Upload `index.html` to any static hosting service
- Works with: Netlify, Vercel, Cloudflare Pages, etc.

### Step 4: Optional Enhancements

#### Enable Automatic Cleanup (Recommended)
Set up a scheduled task to clean up expired groups:

1. In Supabase SQL Editor, enable the pg_cron extension:
   ```sql
   CREATE EXTENSION IF NOT EXISTS pg_cron;
   ```

2. Schedule the cleanup function to run every 5 minutes:
   ```sql
   SELECT cron.schedule(
     'cleanup-groups',
     '*/5 * * * *',
     'SELECT cleanup_old_groups()'
   );
   ```

#### Enable Database-Level Rate Limiting (Advanced)
Uncomment the rate limiting section in `database_schema_secure.sql` and run it for additional protection at the database level.

## Security Best Practices

### For Administrators

1. **Monitor Usage**
   - Regularly check Supabase logs for suspicious patterns
   - Monitor group creation rates
   - Watch for unusual member join patterns

2. **Database Maintenance**
   - Run `cleanup_old_groups()` periodically if not using pg_cron
   - Vacuum database monthly for optimal performance
   - Review and update activity types as needed

3. **Backup Strategy**
   - Enable Supabase automatic backups
   - Download periodic manual backups
   - Test restore procedures

4. **Updates**
   - Keep Supabase client library updated
   - Monitor security advisories
   - Update CSP headers as needed

### For Users

1. **Handle Privacy**
   - Use your public Star Citizen handle only
   - Don't include personal information in descriptions
   - Don't share Discord/contact info publicly

2. **Safe Usage**
   - Only join groups you intend to participate in
   - Report suspicious activity
   - Don't spam group creation

## Architecture

### Technology Stack
- **Frontend**: Vanilla HTML5, CSS3, JavaScript (ES6+)
- **Backend**: Supabase (PostgreSQL)
- **Hosting**: GitHub Pages (or any static host)
- **CDN**: jsDelivr for Supabase client

### Database Schema
```
starcitizen_teamup_groups
├── id (UUID, PK)
├── created_at (TIMESTAMPTZ)
├── creator_handle (TEXT)
├── activity_type (TEXT)
├── title (TEXT)
├── description (TEXT, nullable)
├── max_players (INTEGER)
├── status (TEXT: open/full/closed)
└── expires_at (TIMESTAMPTZ)

starcitizen_teamup_members
├── id (UUID, PK)
├── created_at (TIMESTAMPTZ)
├── group_id (UUID, FK → groups.id)
├── player_handle (TEXT)
├── joined_at (TIMESTAMPTZ)
└── UNIQUE(group_id, player_handle)
```

### Application Flow

1. **Group Creation**
   - Validate input → Check rate limit → Create group → Add creator as member
   - Groups expire in 10 minutes if not filled

2. **Group Joining**
   - Validate input → Verify group is open → Check member limit → Add member
   - Auto-close when full with new 10-minute expiration

3. **Expiration**
   - Client checks every 60 seconds
   - Server-side cleanup via scheduled function
   - Groups auto-reload every 30 seconds

## Activity Types

The application supports the following Star Citizen activities:
- Bounty Hunting
- Mining
- Salvaging
- Trading
- Mercenary
- Investigations
- Search and Rescue
- Piracy
- PVP
- Exploration
- Xenothreat
- Nine Tails Lockdown
- Other

## Troubleshooting

### Groups not loading
- Check Supabase credentials in `index.html`
- Verify database schema was created successfully
- Check browser console for errors
- Ensure RLS policies are enabled

### Can't create groups
- Check rate limiting (wait 1 minute)
- Verify all form fields are valid
- Check Supabase project is active
- Review browser console for validation errors

### Can't join groups
- Verify you're not already in the group
- Check if group is still open
- Verify group hasn't expired
- Check rate limiting (wait 1 minute)

### Database errors
- Ensure `database_schema_secure.sql` ran successfully
- Check for constraint violations in Supabase logs
- Verify anon role has proper permissions
- Review RLS policies are correctly configured

## Performance Considerations

- Maximum 100 groups displayed at once
- Groups auto-refresh every 30 seconds
- Expired groups cleaned up every 60 seconds
- Database indexes optimize common queries
- Efficient member counting with `head: true`

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari 14+, Chrome Mobile)

## Contributing

This is a single-file application designed for easy deployment and modification. Key areas for enhancement:

- Add Discord webhook notifications
- Implement WebSocket for real-time updates
- Add group search/filter functionality
- Implement user authentication
- Add group tags and categories

## License

This project is provided as-is for the Star Citizen community. Feel free to modify and deploy for non-commercial use.

## Support

Support the developer on Ko-Fi: [https://ko-fi.com/](https://ko-fi.com/)

## Security Disclosure

If you discover a security vulnerability, please email the repository owner directly. Do not open public issues for security concerns.

---

**Built for the Star Citizen community** 🚀

Last updated: 2025
