-- Migration: Update User Ratings Table Unique Constraint
-- This migration changes the rating system to prevent unlimited table growth
-- Old: Users could rate the same player once per day (unlimited entries)
-- New: Users can only rate each player once ever (but can update their rating)

-- Step 1: Drop the old unique constraint
ALTER TABLE `starcitizen_teamup_user_ratings`
DROP INDEX `unique_ip_handle_daily`;

-- Step 2: For existing data, keep only the most recent rating for each (IP, handle) pair
-- This prevents duplicate key errors when adding the new constraint
DELETE t1 FROM `starcitizen_teamup_user_ratings` t1
INNER JOIN `starcitizen_teamup_user_ratings` t2
WHERE t1.rated_by_ip = t2.rated_by_ip
  AND t1.player_handle = t2.player_handle
  AND t1.rated_at < t2.rated_at;

-- Step 3: Add the new unique constraint
ALTER TABLE `starcitizen_teamup_user_ratings`
ADD UNIQUE KEY `unique_ip_handle` (`rated_by_ip`, `player_handle`);

-- Notes:
-- - This migration will delete old duplicate ratings, keeping only the most recent one
-- - After this migration, each IP can only have ONE rating per player
-- - Users can still update their ratings, which will update the existing row
-- - This prevents the table from growing infinitely
