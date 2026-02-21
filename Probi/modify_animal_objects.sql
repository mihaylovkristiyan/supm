-- First, get information about the foreign key constraint
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME = 'animal_objects'
AND REFERENCED_COLUMN_NAME IN ('nov_jo', 'star_jo');

-- Drop the foreign key constraint first (we'll get the exact name from the above query)
ALTER TABLE samples
DROP FOREIGN KEY IF EXISTS samples_ibfk_1;

-- Now we can safely modify the indexes
ALTER TABLE animal_objects
MODIFY COLUMN nov_jo VARCHAR(255),
MODIFY COLUMN star_jo VARCHAR(255);

-- Add the auto-increment ID if it doesn't exist
ALTER TABLE animal_objects
ADD COLUMN IF NOT EXISTS id INT AUTO_INCREMENT PRIMARY KEY FIRST;

-- Add normal (non-unique) indexes
ALTER TABLE animal_objects
ADD INDEX idx_star_jo (star_jo),
ADD INDEX idx_nov_jo (nov_jo);

-- Recreate the foreign key constraint
ALTER TABLE samples
ADD CONSTRAINT samples_ibfk_1
FOREIGN KEY (nov_jo) REFERENCES animal_objects(nov_jo)
ON DELETE RESTRICT
ON UPDATE CASCADE; 