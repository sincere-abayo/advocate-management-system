<?php
class Document {
    private $conn;
    private $table_name = "documents";
    
    // Document properties
    public $id;
    public $case_id;
    public $title;
    public $description;
    public $file_name;
    public $file_type;
    public $file_size;
    public $uploaded_by;
    public $created_at;
    public $updated_at;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create document
    public function create() {
        // Sanitize inputs
        $this->case_id = htmlspecialchars(strip_tags($this->case_id));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->file_name = htmlspecialchars(strip_tags($this->file_name));
        $this->file_type = htmlspecialchars(strip_tags($this->file_type));
        $this->file_size = htmlspecialchars(strip_tags($this->file_size));
        $this->uploaded_by = htmlspecialchars(strip_tags($this->uploaded_by));
        
        // Query
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    case_id = :case_id,
                    title = :title,
                    description = :description,
                    file_name = :file_name,
                    file_type = :file_type,
                    file_size = :file_size,
                    uploaded_by = :uploaded_by";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":case_id", $this->case_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":file_name", $this->file_name);
        $stmt->bindParam(":file_type", $this->file_type);
        $stmt->bindParam(":file_size", $this->file_size);
        $stmt->bindParam(":uploaded_by", $this->uploaded_by);
        
        // Execute query
        if($stmt->execute()) {
            // Add to case history
            $this->addToHistory($this->case_id, "Document uploaded", "Document '{$this->title}' has been uploaded");
            
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Read documents by case ID
    public function readByCaseId() {
        // Query
        $query = "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploader_name
                FROM " . $this->table_name . " d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.case_id = ?
                ORDER BY d.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->case_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read single document
    public function readOne() {
        // Query
        $query = "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploader_name
                FROM " . $this->table_name . " d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = ?
                LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If document exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->case_id = $row['case_id'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->file_name = $row['file_name'];
            $this->file_type = $row['file_type'];
            $this->file_size = $row['file_size'];
            $this->uploaded_by = $row['uploaded_by'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Update document
    public function update() {
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET
                    title = :title,
                    description = :description
                WHERE
                    id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            // Add to case history
            $this->addToHistory($this->case_id, "Document updated", "Document '{$this->title}' has been updated");
            
            return true;
        }
        
        return false;
    }
    
    // Delete document
    public function delete() {
        // First get document details for history
        $this->readOne();
        
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
            // Add to case history
            $this->addToHistory($this->case_id, "Document deleted", "Document '{$this->title}' has been deleted");
            
            // Delete the actual file
            $upload_dir = "../uploads/documents/";
            if(file_exists($upload_dir . $this->file_name)) {
                unlink($upload_dir . $this->file_name);
            }
            
            return true;
        }
        
        return false;
    }
    
    // Count documents by case
    public function countByCaseId() {
        // Query
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE case_id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->case_id);
        
        // Execute query
        $stmt->execute();
        
        // Get row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
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
}
?>
