<?php
require_once 'config/db_connect.php';

try {
    // First, get foreign key information
    $fkQuery = "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_NAME = 'animal_objects'
                AND REFERENCED_COLUMN_NAME IN ('nov_jo', 'star_jo')";
    
    $stmt = $pdo->query($fkQuery);
    $fkInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Drop foreign key constraints
    foreach ($fkInfo as $fk) {
        $dropFkSql = "ALTER TABLE " . $fk['TABLE_NAME'] . " DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME'];
        $pdo->exec($dropFkSql);
        echo "Dropped foreign key constraint: " . $fk['CONSTRAINT_NAME'] . "<br>";
    }
    
    // Modify the columns and add indexes
    $pdo->exec("ALTER TABLE animal_objects MODIFY COLUMN nov_jo VARCHAR(255)");
    $pdo->exec("ALTER TABLE animal_objects MODIFY COLUMN star_jo VARCHAR(255)");
    echo "Modified columns to allow duplicates<br>";
    
    // Add auto-increment ID if it doesn't exist
    $pdo->exec("ALTER TABLE animal_objects ADD COLUMN IF NOT EXISTS id INT AUTO_INCREMENT PRIMARY KEY FIRST");
    echo "Added/verified auto-increment ID column<br>";
    
    // Add normal indexes
    $pdo->exec("ALTER TABLE animal_objects ADD INDEX idx_star_jo (star_jo)");
    $pdo->exec("ALTER TABLE animal_objects ADD INDEX idx_nov_jo (nov_jo)");
    echo "Added normal indexes<br>";
    
    // Recreate foreign key constraints
    foreach ($fkInfo as $fk) {
        $addFkSql = "ALTER TABLE " . $fk['TABLE_NAME'] . 
                    " ADD CONSTRAINT " . $fk['CONSTRAINT_NAME'] . 
                    " FOREIGN KEY (" . $fk['COLUMN_NAME'] . ") " .
                    " REFERENCES " . $fk['REFERENCED_TABLE_NAME'] . "(" . $fk['REFERENCED_COLUMN_NAME'] . ")" .
                    " ON DELETE RESTRICT ON UPDATE CASCADE";
        $pdo->exec($addFkSql);
        echo "Recreated foreign key constraint: " . $fk['CONSTRAINT_NAME'] . "<br>";
    }
    
    echo "<br>Database structure updated successfully!";
    
} catch (PDOException $e) {
    echo "Error updating database structure: " . $e->getMessage();
}
?> 