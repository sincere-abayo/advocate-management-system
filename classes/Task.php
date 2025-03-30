<?php
class Task {
    private $conn;
    private $table_name = "tasks";
    
    // Task properties
    public $id;
    public $case_id;
    public $title;
    public $description;
    public $assigned_to;
    public $due_date;
    public $status;
    public $priority;
    public $created_by;
    public $created_at;
    public $updated_at;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create task
    public function create() {
        // Sanitize inputs
        $this->case_id = htmlspecialchars(strip_tags($this->case_id));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->assigned_to = htmlspecialchars(strip_tags($this->assigned_to));
        $this->due_date = htmlspecialchars(strip_tags($this->due_date));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->priority = htmlspecialchars(strip_tags($this->priority));
        $this->created_by = htmlspecialchars(strip_tags($this->created_by));
        
        // Query
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    case_id = :case_id,
                    title = :title,
                    description = :description,
                    assigned_to = :assigned_to,
                    due_date = :due_date,
                    status = :status,
                    priority = :priority,
                    created_by = :created_by";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":case_id", $this->case_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":assigned_to", $this->assigned_to);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":created_by", $this->created_by);
        
        // Execute query
        if($stmt->execute()) {
            // Add to case history
            $this->addToHistory($this->case_id, "Task created", "Task '{$this->title}' has been created");
            
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Read tasks by case ID
    public function readByCaseId() {
        // Query
        $query = "SELECT t.*, 
                    CONCAT(a.first_name, ' ', a.last_name) as assigned_name,
                    CONCAT(c.first_name, ' ', c.last_name) as creator_name
                FROM " . $this->table_name . " t
                LEFT JOIN users a ON t.assigned_to = a.id
                LEFT JOIN users c ON t.created_by = c.id
                WHERE t.case_id = ?
                ORDER BY t.due_date ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->case_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read tasks by assigned user
    public function readByAssignedUser() {
        // Query
        $query = "SELECT t.*, 
                    c.case_number, c.title as case_title,
                    CONCAT(cr.first_name, ' ', cr.last_name) as creator_name
                FROM " . $this->table_name . " t
                LEFT JOIN cases c ON t.case_id = c.id
                LEFT JOIN users cr ON t.created_by = cr.id
                WHERE t.assigned_to = ?
                ORDER BY t.due_date ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->assigned_to);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read single task
    public function readOne() {
        // Query
        $query = "SELECT t.*, 
                    CONCAT(a.first_name, ' ', a.last_name) as assigned_name,
                    CONCAT(c.first_name, ' ', c.last_name) as creator_name
                FROM " . $this->table_name . " t
                LEFT JOIN users a ON t.assigned_to = a.id
                LEFT JOIN users c ON t.created_by = c.id
                WHERE t.id = ?
                LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If task exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->case_id = $row['case_id'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->assigned_to = $row['assigned_to'];
            $this->due_date = $row['due_date'];
            $this->status = $row['status'];
            $this->priority = $row['priority'];
            $this->created_by = $row['created_by'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Update task
    public function update() {
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->assigned_to = htmlspecialchars(strip_tags($this->assigned_to));
        $this->due_date = htmlspecialchars(strip_tags($this->due_date));
        $this->priority = htmlspecialchars(strip_tags($this->priority));
        
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET
                    title = :title,
                    description = :description,
                    assigned_to = :assigned_to,
                    due_date = :due_date,
                    priority = :priority
                WHERE
                    id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":assigned_to", $this->assigned_to);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            // Add to case history
            $this->addToHistory($this->case_id, "Task updated", "Task '{$this->title}' has been updated");
            
            return true;
        }
        
        return false;
    }
    
    // Update task status
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
            // Get task details for history
            $this->readOne();
            
            // Add to case history
            $this->addToHistory($this->case_id, "Task status changed", "Task '{$this->title}' status changed to {$this->status}");
            
            return true;
        }
        
        return false;
    }
    
    // Delete task
    public function delete() {
        // First get task details for history
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
            $this->addToHistory($this->case_id, "Task deleted", "Task '{$this->title}' has been deleted");
            
            return true;
        }
        
        return false;
    }
    
    // Count tasks by case
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
    
    // Count tasks by status
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
    
    // Get overdue tasks
    public function getOverdueTasks() {
        // Query
        $query = "SELECT t.*, 
                    c.case_number, c.title as case_title,
                    CONCAT(a.first_name, ' ', a.last_name) as assigned_name
                FROM " . $this->table_name . " t
                LEFT JOIN cases c ON t.case_id = c.id
                LEFT JOIN users a ON t.assigned_to = a.id
                WHERE t.due_date < CURDATE() AND t.status != 'Completed'
                ORDER BY t.due_date ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
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
    // Add this method to the Task class

/**
 * Get recent tasks with limit
 * @param int $limit Number of tasks to return
 * @return PDOStatement
 */
// Add to classes/Task.php
public function getRecentTasks($limit = 5) {
    // Query
    $query = "SELECT t.*, c.case_number 
              FROM " . $this->table_name . " t
              LEFT JOIN cases c ON t.case_id = c.id
              ORDER BY t.created_at DESC
              LIMIT ?";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameter
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}


}
?>
