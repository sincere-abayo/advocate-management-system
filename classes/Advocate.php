<?php
class Advocate {
    private $conn;
    private $table_name = "advocates";
    
    // Advocate properties
    public $id;
    public $user_id;
    public $license_number;
    public $specialization;
    public $experience_years;
    public $education;
    public $bio;
    public $hourly_rate;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create advocate
    public function create() {
        // Sanitize inputs
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->license_number = htmlspecialchars(strip_tags($this->license_number));
        $this->specialization = htmlspecialchars(strip_tags($this->specialization));
        $this->experience_years = htmlspecialchars(strip_tags($this->experience_years));
        $this->education = htmlspecialchars(strip_tags($this->education));
        $this->bio = htmlspecialchars(strip_tags($this->bio));
        $this->hourly_rate = htmlspecialchars(strip_tags($this->hourly_rate));
        
        // Query
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    user_id = :user_id,
                    license_number = :license_number,
                    specialization = :specialization,
                    experience_years = :experience_years,
                    education = :education,
                    bio = :bio,
                    hourly_rate = :hourly_rate";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":license_number", $this->license_number);
        $stmt->bindParam(":specialization", $this->specialization);
        $stmt->bindParam(":experience_years", $this->experience_years);
        $stmt->bindParam(":education", $this->education);
        $stmt->bindParam(":bio", $this->bio);
        $stmt->bindParam(":hourly_rate", $this->hourly_rate);
        
        // Execute query
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Read all advocates
    public function read() {
        // Query
        $query = "SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image, u.is_active
                FROM " . $this->table_name . " a
                LEFT JOIN users u ON a.user_id = u.id
                ORDER BY u.first_name ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read single advocate
    public function readOne() {
        // Query
        $query = "SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.profile_image, u.is_active
                FROM " . $this->table_name . " a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.id = ?
                LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If advocate exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->license_number = $row['license_number'];
            $this->specialization = $row['specialization'];
            $this->experience_years = $row['experience_years'];
            $this->education = $row['education'];
            $this->bio = $row['bio'];
            $this->hourly_rate = $row['hourly_rate'];
            
            return true;
        }
        
        return false;
    }
    
    // Read advocate by user ID
    public function readByUserId() {
        // Query
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = ? LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->user_id);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If advocate exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->license_number = $row['license_number'];
            $this->specialization = $row['specialization'];
            $this->experience_years = $row['experience_years'];
            $this->education = $row['education'];
            $this->bio = $row['bio'];
            $this->hourly_rate = $row['hourly_rate'];
            
            return true;
        }
        
        return false;
    }
    
    // Update advocate
    public function update() {
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->license_number = htmlspecialchars(strip_tags($this->license_number));
        $this->specialization = htmlspecialchars(strip_tags($this->specialization));
        $this->experience_years = htmlspecialchars(strip_tags($this->experience_years));
        $this->education = htmlspecialchars(strip_tags($this->education));
        $this->bio = htmlspecialchars(strip_tags($this->bio));
        $this->hourly_rate = htmlspecialchars(strip_tags($this->hourly_rate));
        
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET
                    license_number = :license_number,
                    specialization = :specialization,
                    experience_years = :experience_years,
                    education = :education,
                    bio = :bio,
                    hourly_rate = :hourly_rate
                WHERE
                    id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":license_number", $this->license_number);
        $stmt->bindParam(":specialization", $this->specialization);
        $stmt->bindParam(":experience_years", $this->experience_years);
        $stmt->bindParam(":education", $this->education);
        $stmt->bindParam(":bio", $this->bio);
        $stmt->bindParam(":hourly_rate", $this->hourly_rate);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Delete advocate
    public function delete() {
        // Query
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Search advocates
    public function search($keywords) {
        // Query
        $query = "SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image, u.is_active
                FROM " . $this->table_name . " a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE
                    a.license_number LIKE ? OR
                    a.specialization LIKE ? OR
                    u.first_name LIKE ? OR
                    u.last_name LIKE ? OR
                    u.email LIKE ?
                ORDER BY
                    u.first_name ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";
        
        // Bind parameters
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        $stmt->bindParam(3, $keywords);
        $stmt->bindParam(4, $keywords);
        $stmt->bindParam(5, $keywords);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Count total advocates
    public function count() {
        // Query
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        // Get row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
    }
    
    // Check if license number exists
    public function licenseNumberExists() {
        // Query
        $query = "SELECT id FROM " . $this->table_name . " WHERE license_number = ? LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->license_number = htmlspecialchars(strip_tags($this->license_number));
        
        // Bind parameter
        $stmt->bindParam(1, $this->license_number);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If license number exists
        if($num > 0) {
            return true;
        }
        
        return false;
    }
}
?>
