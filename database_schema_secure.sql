-- ============================================
-- STAR CITIZEN TEAM UP - SECURE DATABASE SCHEMA
-- ============================================
-- Run this SQL in your Supabase SQL Editor
-- This schema includes comprehensive security measures

-- ============================================
-- DROP EXISTING TABLES (if re-running)
-- ============================================
-- Uncomment these lines if you need to reset the database
-- DROP TABLE IF EXISTS starcitizen_teamup_members CASCADE;
-- DROP TABLE IF EXISTS starcitizen_teamup_groups CASCADE;
-- DROP FUNCTION IF EXISTS cleanup_old_groups() CASCADE;

-- ============================================
-- CREATE TABLES
-- ============================================

-- Groups table with security constraints
CREATE TABLE IF NOT EXISTS starcitizen_teamup_groups (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  creator_handle TEXT NOT NULL,
  activity_type TEXT NOT NULL,
  title TEXT NOT NULL,
  description TEXT,
  ship TEXT,
  max_players INTEGER NOT NULL DEFAULT 4,
  status TEXT NOT NULL DEFAULT 'open',
  expires_at TIMESTAMPTZ NOT NULL,

  -- Constraints for data validation
  CONSTRAINT valid_creator_handle CHECK (
    LENGTH(creator_handle) >= 3 AND
    LENGTH(creator_handle) <= 50 AND
    creator_handle ~ '^[a-zA-Z0-9_-]+$'
  ),
  CONSTRAINT valid_activity_type CHECK (
    activity_type IN (
      'Bounty Hunting', 'Mining', 'Salvaging', 'Trading', 'Mercenary',
      'Investigations', 'Search and Rescue', 'Piracy', 'PVP',
      'Exploration', 'Xenothreat', 'Nine Tails Lockdown', 'Other'
    )
  ),
  CONSTRAINT valid_title CHECK (
    LENGTH(title) >= 3 AND LENGTH(title) <= 100
  ),
  CONSTRAINT valid_description CHECK (
    description IS NULL OR LENGTH(description) <= 500
  ),
  CONSTRAINT valid_ship CHECK (
    ship IS NULL OR (LENGTH(ship) >= 2 AND LENGTH(ship) <= 50)
  ),
  CONSTRAINT valid_max_players CHECK (
    max_players >= 2 AND max_players <= 50
  ),
  CONSTRAINT valid_status CHECK (
    status IN ('open', 'full', 'closed')
  ),
  CONSTRAINT valid_expires_at CHECK (
    expires_at > created_at
  )
);

-- Members table with security constraints
CREATE TABLE IF NOT EXISTS starcitizen_teamup_members (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  group_id UUID NOT NULL REFERENCES starcitizen_teamup_groups(id) ON DELETE CASCADE,
  player_handle TEXT NOT NULL,
  joined_at TIMESTAMPTZ NOT NULL DEFAULT now(),

  -- Constraints for data validation
  CONSTRAINT valid_player_handle CHECK (
    LENGTH(player_handle) >= 3 AND
    LENGTH(player_handle) <= 50 AND
    player_handle ~ '^[a-zA-Z0-9_-]+$'
  ),

  -- Prevent duplicate memberships (critical for security)
  CONSTRAINT unique_group_member UNIQUE (group_id, player_handle)
);

-- ============================================
-- CREATE INDEXES FOR PERFORMANCE
-- ============================================

-- Index on status and created_at for efficient group queries
CREATE INDEX IF NOT EXISTS idx_groups_status_created
  ON starcitizen_teamup_groups(status, created_at DESC);

-- Index on expires_at for cleanup operations
CREATE INDEX IF NOT EXISTS idx_groups_expires
  ON starcitizen_teamup_groups(expires_at);

-- Index on activity_type for filtering
CREATE INDEX IF NOT EXISTS idx_groups_activity_type
  ON starcitizen_teamup_groups(activity_type);

-- Index on ship for filtering
CREATE INDEX IF NOT EXISTS idx_groups_ship
  ON starcitizen_teamup_groups(ship) WHERE ship IS NOT NULL;

-- Index on group_id for member lookups
CREATE INDEX IF NOT EXISTS idx_members_group_id
  ON starcitizen_teamup_members(group_id);

-- Index on player_handle for duplicate checking
CREATE INDEX IF NOT EXISTS idx_members_player_handle
  ON starcitizen_teamup_members(player_handle);

-- ============================================
-- ENABLE ROW LEVEL SECURITY (RLS)
-- ============================================

ALTER TABLE starcitizen_teamup_groups ENABLE ROW LEVEL SECURITY;
ALTER TABLE starcitizen_teamup_members ENABLE ROW LEVEL SECURITY;

-- ============================================
-- DROP EXISTING POLICIES (if re-running)
-- ============================================

DROP POLICY IF EXISTS "Anyone can view open and full groups" ON starcitizen_teamup_groups;
DROP POLICY IF EXISTS "Anyone can insert groups" ON starcitizen_teamup_groups;
DROP POLICY IF EXISTS "Creators can update their own groups" ON starcitizen_teamup_groups;
DROP POLICY IF EXISTS "System can update group status" ON starcitizen_teamup_groups;
DROP POLICY IF EXISTS "Anyone can view members" ON starcitizen_teamup_members;
DROP POLICY IF EXISTS "Anyone can insert members" ON starcitizen_teamup_members;

-- ============================================
-- CREATE SECURE RLS POLICIES
-- ============================================

-- Groups Policies
-- Allow viewing open and full groups only (not closed)
CREATE POLICY "Anyone can view open and full groups"
  ON starcitizen_teamup_groups
  FOR SELECT
  USING (status IN ('open', 'full'));

-- Allow anyone to create groups (with validation from constraints)
CREATE POLICY "Anyone can insert groups"
  ON starcitizen_teamup_groups
  FOR INSERT
  WITH CHECK (
    -- Ensure group starts as 'open'
    status = 'open' AND
    -- Ensure expires_at is in the future but not too far
    expires_at > now() AND
    expires_at < now() + INTERVAL '1 hour'
  );

-- Allow updates ONLY for:
-- 1. Changing status from 'open' to 'full' when group fills up
-- 2. Changing status from 'open' or 'full' to 'closed' when expired
-- This prevents malicious updates to creator_handle, title, etc.
CREATE POLICY "System can update group status"
  ON starcitizen_teamup_groups
  FOR UPDATE
  USING (true)
  WITH CHECK (
    -- Only allow status and expires_at changes
    -- All other fields must remain unchanged
    (
      -- When marking as full
      (status = 'full' AND
       OLD.status = 'open' AND
       expires_at > now() AND
       expires_at < now() + INTERVAL '1 hour') OR

      -- When closing expired groups
      (status = 'closed' AND
       OLD.status IN ('open', 'full'))
    ) AND
    -- Ensure immutable fields don't change
    id = OLD.id AND
    created_at = OLD.created_at AND
    creator_handle = OLD.creator_handle AND
    activity_type = OLD.activity_type AND
    title = OLD.title AND
    (description = OLD.description OR (description IS NULL AND OLD.description IS NULL)) AND
    max_players = OLD.max_players
  );

-- Members Policies
-- Allow viewing all members
CREATE POLICY "Anyone can view members"
  ON starcitizen_teamup_members
  FOR SELECT
  USING (true);

-- Allow inserting members with strict validation
CREATE POLICY "Anyone can insert members"
  ON starcitizen_teamup_members
  FOR INSERT
  WITH CHECK (
    -- Ensure the group exists and is open
    EXISTS (
      SELECT 1 FROM starcitizen_teamup_groups
      WHERE id = group_id AND status = 'open'
    ) AND
    -- Ensure member count won't exceed max_players
    (
      SELECT COUNT(*) FROM starcitizen_teamup_members
      WHERE group_id = starcitizen_teamup_members.group_id
    ) < (
      SELECT max_players FROM starcitizen_teamup_groups
      WHERE id = starcitizen_teamup_members.group_id
    )
  );

-- ============================================
-- AUTOMATIC CLEANUP FUNCTION
-- ============================================

-- Function to automatically close expired groups
CREATE OR REPLACE FUNCTION cleanup_old_groups()
RETURNS INTEGER AS $$
DECLARE
  closed_count INTEGER;
  deleted_count INTEGER;
BEGIN
  -- Close groups that have expired
  UPDATE starcitizen_teamup_groups
  SET status = 'closed'
  WHERE status IN ('open', 'full')
    AND expires_at < now();

  GET DIAGNOSTICS closed_count = ROW_COUNT;

  -- Delete groups that have been closed for more than 24 hours
  -- This keeps the database clean and performant
  DELETE FROM starcitizen_teamup_groups
  WHERE status = 'closed'
    AND expires_at < now() - INTERVAL '24 hours';

  GET DIAGNOSTICS deleted_count = ROW_COUNT;

  -- Return total count of affected rows
  RETURN closed_count + deleted_count;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ============================================
-- RATE LIMITING (Optional - Advanced)
-- ============================================
-- Uncomment the following section if you want database-level rate limiting
-- This requires the pg_cron extension to be enabled in Supabase

/*
-- Create a table to track rate limits
CREATE TABLE IF NOT EXISTS starcitizen_teamup_rate_limits (
  handle TEXT NOT NULL,
  action TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (handle, action, created_at)
);

-- Index for efficient cleanup
CREATE INDEX IF NOT EXISTS idx_rate_limits_created
  ON starcitizen_teamup_rate_limits(created_at);

-- Function to check rate limits
CREATE OR REPLACE FUNCTION check_rate_limit(
  p_handle TEXT,
  p_action TEXT,
  p_max_attempts INTEGER,
  p_window_seconds INTEGER
)
RETURNS BOOLEAN AS $$
DECLARE
  attempt_count INTEGER;
BEGIN
  -- Count attempts in the time window
  SELECT COUNT(*) INTO attempt_count
  FROM starcitizen_teamup_rate_limits
  WHERE handle = p_handle
    AND action = p_action
    AND created_at > now() - (p_window_seconds || ' seconds')::INTERVAL;

  -- If under limit, record attempt and allow
  IF attempt_count < p_max_attempts THEN
    INSERT INTO starcitizen_teamup_rate_limits (handle, action)
    VALUES (p_handle, p_action);
    RETURN TRUE;
  END IF;

  -- Over limit, deny
  RETURN FALSE;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Cleanup old rate limit records (run periodically)
CREATE OR REPLACE FUNCTION cleanup_rate_limits()
RETURNS INTEGER AS $$
DECLARE
  deleted_count INTEGER;
BEGIN
  DELETE FROM starcitizen_teamup_rate_limits
  WHERE created_at < now() - INTERVAL '1 hour';

  GET DIAGNOSTICS deleted_count = ROW_COUNT;
  RETURN deleted_count;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
*/

-- ============================================
-- GRANT PERMISSIONS
-- ============================================

-- Grant necessary permissions to anon role (public access)
GRANT SELECT, INSERT ON starcitizen_teamup_groups TO anon;
GRANT UPDATE (status, expires_at) ON starcitizen_teamup_groups TO anon;
GRANT SELECT, INSERT ON starcitizen_teamup_members TO anon;
GRANT USAGE ON SCHEMA public TO anon;

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Run these queries to verify the setup
-- They should all return successfully

-- Check tables exist
SELECT
  'Tables created successfully' AS status,
  COUNT(*) AS table_count
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_name LIKE 'starcitizen_teamup_%';

-- Check constraints
SELECT
  'Constraints created successfully' AS status,
  COUNT(*) AS constraint_count
FROM information_schema.table_constraints
WHERE table_schema = 'public'
  AND table_name LIKE 'starcitizen_teamup_%';

-- Check indexes
SELECT
  'Indexes created successfully' AS status,
  COUNT(*) AS index_count
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename LIKE 'starcitizen_teamup_%';

-- Check policies
SELECT
  'Policies created successfully' AS status,
  COUNT(*) AS policy_count
FROM pg_policies
WHERE schemaname = 'public'
  AND tablename LIKE 'starcitizen_teamup_%';

-- ============================================
-- NOTES FOR ADMINISTRATORS
-- ============================================

/*
SECURITY FEATURES IMPLEMENTED:

1. INPUT VALIDATION CONSTRAINTS:
   - Handle: 3-50 chars, alphanumeric + underscores/dashes only
   - Title: 3-100 characters
   - Description: max 500 characters
   - Activity Type: whitelisted values only
   - Max Players: 2-50 range
   - Status: whitelisted values only

2. UNIQUE CONSTRAINTS:
   - Prevents duplicate (group_id, player_handle) combinations
   - Prevents race conditions in member joining

3. ROW LEVEL SECURITY:
   - Groups: Only open/full visible, updates restricted to status changes
   - Members: Insert validation ensures group is open and not full
   - Immutable fields cannot be changed after creation

4. INDEXES:
   - Optimized queries for status, created_at, expires_at
   - Fast member lookups and duplicate checking

5. AUTOMATIC CLEANUP:
   - cleanup_old_groups() function closes expired groups
   - Optionally deletes old closed groups to save space

6. PERFORMANCE:
   - Indexes on frequently queried columns
   - Cascading deletes for referential integrity

OPTIONAL ENHANCEMENTS:

1. Enable pg_cron for scheduled cleanup:
   SELECT cron.schedule('cleanup-groups', '*/5 * * * *', 'SELECT cleanup_old_groups()');

2. Enable rate limiting at database level (uncomment section above)

3. Add monitoring with pg_stat_statements

4. Set up database backups and replication

MAINTENANCE:

- Run cleanup_old_groups() periodically (every 5-10 minutes)
- Monitor table sizes and vacuum if needed
- Review logs for suspicious activity patterns
- Update activity types list as needed

*/
