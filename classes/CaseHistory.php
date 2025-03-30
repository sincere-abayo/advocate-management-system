<?php
class CaseHistory {
    private $conn;
    private $table_name = "case_history";
    
    // Case history properties
    public $id;
    public $case_id;
    public $user_id;
    public $action;
    public $description;
    public $created_at;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create case history entry
    public function create() {
        // Sanitize inputs
        $this->case_id = htmlspecialchars(strip_tags($this->case_id));
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->action = htmlspecialchars(strip_tags($this->action));
        $this->description = htmlspecialchars(strip_tags($this->description));
        
        // Query
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    case_id = :case_id,
                    user_id = :user_id,
                    action = :action,
                    description = :description";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":case_id", $this->case_id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":action", $this->action);
        $stmt->bindParam(":description", $this->description);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Read case history by case ID
    public function readByCaseId() {
        // Query
        $query = "SELECT h.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM " . $this->table_name . " h
                LEFT JOIN users u ON h.user_id = u.id
                WHERE h.case_id = ?
                ORDER BY h.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->case_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read single history entry
    public function readOne() {
        // Query
        $query = "SELECT h.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM " . $this->table_name . " h
                LEFT JOIN users u ON h.user_id = u.id
                WHERE h.id = ?
                LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If history entry exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->case_id = $row['case_id'];
            $this->user_id = $row['user_id'];
            $this->action = $row['action'];
            $this->description = $row['description'];
            $this->created_at = $row['created_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Delete history entry
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
    
    // Delete all history entries for a case
    public function deleteAllByCaseId() {
        // Query
        $query = "DELETE FROM " . $this->table_name . " WHERE case_id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->case_id = htmlspecialchars(strip_tags($this->case_id));
        
        // Bind parameter
        $stmt->bindParam(1, $this->case_id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
}
?>
