<?php
class LegalCase {
    private $conn;
    private $table_name = "cases";
    
    // Case properties
    public $id;
    public $case_number;
    public $title;
    public $description;
    public $client_id;
    public $advocate_id;
    public $case_type;
    public $court_name;
    public $filing_date;
    public $hearing_date;
    public $status;
    public $priority;
    public $created_at;
    public $updated_at;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create case
    public function create() {
        // Generate unique case number
        $this->case_number = $this->generateCaseNumber();
        
        // Sanitize inputs
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $this->advocate_id = htmlspecialchars(strip_tags($this->advocate_id));
        $this->case_type = htmlspecialchars(strip_tags($this->case_type));
        $this->court_name = htmlspecialchars(strip_tags($this->court_name));
        $this->filing_date = htmlspecialchars(strip_tags($this->filing_date));
        $this->hearing_date = htmlspecialchars(strip_tags($this->hearing_date));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->priority = htmlspecialchars(strip_tags($this->priority));
        
        // Query
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    case_number = :case_number,
                    title = :title,
                    description = :description,
                    client_id = :client_id,
                    advocate_id = :advocate_id,
                    case_type = :case_type,
                    court_name = :court_name,
                    filing_date = :filing_date,
                    hearing_date = :hearing_date,
                    status = :status,
                    priority = :priority";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":case_number", $this->case_number);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":advocate_id", $this->advocate_id);
        $stmt->bindParam(":case_type", $this->case_type);
        $stmt->bindParam(":court_name", $this->court_name);
        $stmt->bindParam(":filing_date", $this->filing_date);
        $stmt->bindParam(":hearing_date", $this->hearing_date);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":priority", $this->priority);
        
        // Execute query
        if($stmt->execute()) {
            // Add to case history
            $case_id = $this->conn->lastInsertId();
            $this->addToHistory($case_id, "Case created", "New case has been created");
            
            return $case_id;
        }
        
        return false;
    }
    
    // Read all cases
    public function read() {
        // Query
        $query = "SELECT c.*, 
                    cl.id as client_id, CONCAT(cu.first_name, ' ', cu.last_name) as client_name,
                    a.id as advocate_id, CONCAT(au.first_name, ' ', au.last_name) as advocate_name
                FROM " . $this->table_name . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN users cu ON cl.user_id = cu.id
                LEFT JOIN advocates a ON c.advocate_id = a.id
                LEFT JOIN users au ON a.user_id = au.id
                ORDER BY c.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read single case
    public function readOne() {
        // Query
        $query = "SELECT c.*, 
                    cl.id as client_id, CONCAT(cu.first_name, ' ', cu.last_name) as client_name, cu.email as client_email, cu.phone as client_phone,
                    a.id as advocate_id, CONCAT(au.first_name, ' ', au.last_name) as advocate_name, au.email as advocate_email, au.phone as advocate_phone
                FROM " . $this->table_name . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN users cu ON cl.user_id = cu.id
                LEFT JOIN advocates a ON c.advocate_id = a.id
                LEFT JOIN users au ON a.user_id = au.id
                WHERE c.id = ?
                LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If case exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->case_number = $row['case_number'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->client_id = $row['client_id'];
            $this->advocate_id = $row['advocate_id'];
            $this->case_type = $row['case_type'];
            $this->court_name = $row['court_name'];
            $this->filing_date = $row['filing_date'];
            $this->hearing_date = $row['hearing_date'];
            $this->status = $row['status'];
            $this->priority = $row['priority'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Read cases by advocate
    public function readByAdvocate() {
        // Query
        $query = "SELECT c.*, 
                    cl.id as client_id, CONCAT(cu.first_name, ' ', cu.last_name) as client_name,
                    a.id as advocate_id, CONCAT(au.first_name, ' ', au.last_name) as advocate_name
                FROM " . $this->table_name . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN users cu ON cl.user_id = cu.id
                LEFT JOIN advocates a ON c.advocate_id = a.id
                LEFT JOIN users au ON a.user_id = au.id
                WHERE c.advocate_id = ?
                ORDER BY c.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->advocate_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read cases by client
    public function readByClient() {
        // Query
        $query = "SELECT c.*, 
                    cl.id as client_id, CONCAT(cu.first_name, ' ', cu.last_name) as client_name,
                    a.id as advocate_id, CONCAT(au.first_name, ' ', au.last_name) as advocate_name
                FROM " . $this->table_name . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN users cu ON cl.user_id = cu.id
                LEFT JOIN advocates a ON c.advocate_id = a.id
                LEFT JOIN users au ON a.user_id = au.id
                WHERE c.client_id = ?
                ORDER BY c.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->client_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Update case
    public function update() {
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->case_type = htmlspecialchars(strip_tags($this->case_type));
        $this->court_name = htmlspecialchars(strip_tags($this->court_name));
        $this->filing_date = htmlspecialchars(strip_tags($this->filing_date));
        $this->hearing_date = htmlspecialchars(strip_tags($this->hearing_date));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->priority = htmlspecialchars(strip_tags($this->priority));
        
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET
                    title = :title,
                    description = :description,
                    case_type = :case_type,
                    court_name = :court_name,
                    filing_date = :filing_date,
                    hearing_date = :hearing_date,
                    status = :status,
                    priority = :priority
                WHERE
                    id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":case_type", $this->case_type);
        $stmt->bindParam(":court_name", $this->court_name);
        $stmt->bindParam(":filing_date", $this->filing_date);
        $stmt->bindParam(":hearing_date", $this->hearing_date);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            // Add to case history
            $this->addToHistory($this->id, "Case updated", "Case details have been updated");
            
            return true;
        }
        
        return false;
    }
    
    // Update case status
    public function updateStatus() {
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET
                    status = :status
                WHERE
                    id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            // Add to case history
            $this->addToHistory($this->id, "Status changed", "Case status changed to " . $this->status);
            
            return true;
        }
        
        return false;
    }
    
    // Delete case
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
    
    // Search cases
    public function search($keywords) {
        // Query
        $query = "SELECT c.*, 
                    cl.id as client_id, CONCAT(cu.first_name, ' ', cu.last_name) as client_name,
                    a.id as advocate_id, CONCAT(au.first_name, ' ', au.last_name) as advocate_name
                FROM " . $this->table_name . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN users cu ON cl.user_id = cu.id
                LEFT JOIN advocates a ON c.advocate_id = a.id
                LEFT JOIN users au ON a.user_id = au.id
                WHERE
                    c.case_number LIKE ? OR
                    c.title LIKE ? OR
                    c.description LIKE ? OR
                    c.case_type LIKE ? OR
                    c.court_name LIKE ? OR
                    CONCAT(cu.first_name, ' ', cu.last_name) LIKE ? OR
                    CONCAT(au.first_name, ' ', au.last_name) LIKE ?
                ORDER BY
                    c.created_at DESC";
        
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
        $stmt->bindParam(6, $keywords);
        $stmt->bindParam(7, $keywords);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Count total cases
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
    
    // Count cases by status
public function countByStatus($status) {
    // Query
    $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status = ?";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameter
    $stmt->bindParam(1, $status);
    
    // Execute query
    $stmt->execute();
    
    // Get row
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}

    // Generate unique case number
    private function generateCaseNumber() {
        // Format: CASE-YEAR-RANDOM
        $year = date('Y');
        $random = mt_rand(10000, 99999);
        
        return "CASE-" . $year . "-" . $random;
    }
    
    // Add to case history
    private function addToHistory($case_id, $action, $description) {
        // Query
        $query = "INSERT INTO case_history
                SET
                    case_id = :case_id,
                    action = :action,
                    description = :description,
                    user_id = :user_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Get current user ID (assuming it's stored in session)
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        
        // Bind values
        $stmt->bindParam(":case_id", $case_id);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":user_id", $user_id);
        
        // Execute query
        $stmt->execute();
    }
    
    // Get case history
    public function getHistory() {
        // Query
        $query = "SELECT h.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM case_history h
                LEFT JOIN users u ON h.user_id = u.id
                WHERE h.case_id = ?
                ORDER BY h.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get upcoming hearings
    public function getUpcomingHearings() {
        // Query
        $query = "SELECT c.*, 
                    cl.id as client_id, CONCAT(cu.first_name, ' ', cu.last_name) as client_name,
                    a.id as advocate_id, CONCAT(au.first_name, ' ', au.last_name) as advocate_name
                FROM " . $this->table_name . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN users cu ON cl.user_id = cu.id
                LEFT JOIN advocates a ON c.advocate_id = a.id
                LEFT JOIN users au ON a.user_id = au.id
                WHERE c.hearing_date >= CURDATE()
                ORDER BY c.hearing_date ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get cases by priority
    public function getByPriority($priority) {
        // Query
        $query = "SELECT c.*, 
                    cl.id as client_id, CONCAT(cu.first_name, ' ', cu.last_name) as client_name,
                    a.id as advocate_id, CONCAT(au.first_name, ' ', au.last_name) as advocate_name
                FROM " . $this->table_name . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN users cu ON cl.user_id = cu.id
                LEFT JOIN advocates a ON c.advocate_id = a.id
                LEFT JOIN users au ON a.user_id = au.id
                WHERE c.priority = ?
                ORDER BY c.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $priority);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
}
?>

