<?php
class Event {
    private $conn;
    private $table_name = "events";
    
    // Event properties
    public $id;
    public $case_id;
    public $title;
    public $description;
    public $event_date;
    public $event_time;
    public $end_time;
    public $event_type;

    public $location;
    public $reminder;
    public $created_by;
    public $created_at;
    public $updated_at;
    public $client_id; // Add this property
    public $advocate_id; // Add this property if not already present
    public $status;


    public $case_number;
    public $case_title;
    public $client_name;
    public $advocate_name;
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create event

public function create() {
    // Sanitize inputs - check for null values before using strip_tags
    $this->case_id = $this->case_id !== null ? htmlspecialchars(strip_tags($this->case_id)) : null;
    $this->title = htmlspecialchars(strip_tags($this->title));
    $this->description = $this->description !== null ? htmlspecialchars(strip_tags($this->description)) : null;
    $this->event_date = htmlspecialchars(strip_tags($this->event_date));
    $this->event_time = htmlspecialchars(strip_tags($this->event_time));
    $this->end_time = $this->end_time !== null ? htmlspecialchars(strip_tags($this->end_time)) : null;
    $this->location = $this->location !== null ? htmlspecialchars(strip_tags($this->location)) : null;
    $this->event_type = $this->event_type !== null ? htmlspecialchars(strip_tags($this->event_type)) : null;
    $this->client_id = $this->client_id !== null ? htmlspecialchars(strip_tags($this->client_id)) : null;
    $this->advocate_id = $this->advocate_id !== null ? htmlspecialchars(strip_tags($this->advocate_id)) : null;
    
    // Query - update to match the actual database schema
    $query = "INSERT INTO " . $this->table_name . "
            SET
                case_id = :case_id,
                title = :title,
                description = :description,
                event_date = :event_date,
                event_time = :event_time,
                end_time = :end_time,
                location = :location,
                client_id = :client_id,
                advocate_id = :advocate_id,
                event_type = :event_type,
                status = :status";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Set default status if not provided
    $this->status = isset($this->status) ? $this->status : 'Scheduled';    
    // Bind values
    $stmt->bindParam(":case_id", $this->case_id);
    $stmt->bindParam(":title", $this->title);
    $stmt->bindParam(":description", $this->description);
    $stmt->bindParam(":event_date", $this->event_date);
    $stmt->bindParam(":event_time", $this->event_time);
    $stmt->bindParam(":end_time", $this->end_time);
    $stmt->bindParam(":location", $this->location);
    $stmt->bindParam(":client_id", $this->client_id);
    $stmt->bindParam(":advocate_id", $this->advocate_id);
    $stmt->bindParam(":event_type", $this->event_type);
    $stmt->bindParam(":status", $this->status);
    
    // Execute query
    if($stmt->execute()) {
        // Add to case history if associated with a case
        if($this->case_id > 0) {
            $this->addToHistory($this->case_id, "Event created", "Event '{$this->title}' scheduled for {$this->event_date}");
        }
        
        return $this->conn->lastInsertId();
    }
    
    return false;
}

    
    // Read all events
    public function read() {
        // Query
        $query = "SELECT e.*, c.case_number, c.title as case_title, CONCAT(u.first_name, ' ', u.last_name) as creator_name
                FROM " . $this->table_name . " e
                LEFT JOIN cases c ON e.case_id = c.id
                LEFT JOIN users u ON e.created_by = u.id
                ORDER BY e.event_date ASC, e.event_time ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
// Read events by case ID
public function readByCaseId() {
    // Query
    $query = "SELECT e.*, 
                CONCAT(u.first_name, ' ', u.last_name) as creator_name
              FROM " . $this->table_name . " e
              LEFT JOIN advocates a ON e.advocate_id = a.id
              LEFT JOIN users u ON a.user_id = u.id
              WHERE e.case_id = ?
              ORDER BY e.event_date ASC, e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameter
    $stmt->bindParam(1, $this->case_id);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}


    
    // Read events by date range
    public function readByDateRange($start_date, $end_date) {
        // Query
        $query = "SELECT e.*, c.case_number, c.title as case_title, CONCAT(u.first_name, ' ', u.last_name) as creator_name
                FROM " . $this->table_name . " e
                LEFT JOIN cases c ON e.case_id = c.id
                LEFT JOIN users u ON e.created_by = u.id
                WHERE e.event_date BETWEEN ? AND ?
                ORDER BY e.event_date ASC, e.event_time ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(1, $start_date);
        $stmt->bindParam(2, $end_date);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read single event
    public function readOne() {
        // Query
        $query = "SELECT e.*, c.case_number, c.title as case_title, CONCAT(u.first_name, ' ', u.last_name) as creator_name
                FROM " . $this->table_name . " e
                LEFT JOIN cases c ON e.case_id = c.id
                LEFT JOIN users u ON e.advocate_id = u.id
                WHERE e.id = ?
                LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If event exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->case_id = $row['case_id'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->event_date = $row['event_date'];
            $this->event_time = $row['event_time'];
            $this->location = $row['location'];
            $this->reminder = isset($row['reminder']) ? $row['reminder'] : null;
            $this->created_by = isset($row['created_by']) ? $row['created_by'] : null;
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Update event
    public function update() {
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->event_date = htmlspecialchars(strip_tags($this->event_date));
        $this->event_time = htmlspecialchars(strip_tags($this->event_time));
        $this->location = htmlspecialchars(strip_tags($this->location));
        $this->reminder = $this->reminder !== null ? htmlspecialchars(strip_tags($this->reminder)) : null;
        
        // Query
        $query = "UPDATE " . $this->table_name . "
        SET
            title = :title,
            description = :description,
            event_date = :event_date,
            event_time = :event_time,
            location = :location
        WHERE
            id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
$stmt->bindParam(":title", $this->title);
$stmt->bindParam(":description", $this->description);
$stmt->bindParam(":event_date", $this->event_date);
$stmt->bindParam(":event_time", $this->event_time);
$stmt->bindParam(":location", $this->location);
$stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            // Add to case history if associated with a case
            if($this->case_id > 0) {
                $this->addToHistory($this->case_id, "Event updated", "Event '{$this->title}' has been updated");
            }
            
            return true;
        }
        
        return false;
    }
    
    // Delete event
    public function delete() {
        // First get event details for history
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
            // Add to case history if associated with a case
            if($this->case_id > 0) {
                $this->addToHistory($this->case_id, "Event deleted", "Event '{$this->title}' has been deleted");
            }
            
            return true;
        }
        
        return false;
    }
    
    // Get upcoming events
public function getUpcomingEvents($days = 7) {
    // Get current date
    $current_date = date('Y-m-d');
    
    // Calculate end date
    $end_date = date('Y-m-d', strtotime("+{$days} days"));
    
    // Query
    $query = "SELECT e.*, c.case_number 
              FROM " . $this->table_name . " e
              LEFT JOIN cases c ON e.case_id = c.id
              WHERE e.event_date BETWEEN ? AND ?
              ORDER BY e.event_date ASC, e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(1, $current_date);
    $stmt->bindParam(2, $end_date);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}

public function getTodayEvents() {
    // Get current date
    $current_date = date('Y-m-d');
    
    // Query
    $query = "SELECT e.*, c.case_number 
              FROM " . $this->table_name . " e
              LEFT JOIN cases c ON e.case_id = c.id
              WHERE e.event_date = ?
              ORDER BY e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameter
    $stmt->bindParam(1, $current_date);
    
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
                    action_type = :action_type,
                    description = :description,
                    performed_by = :performed_by";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Get current user ID (assuming it's stored in session)
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        
        // Bind values
        $stmt->bindParam(":case_id", $case_id);
        $stmt->bindParam(":action_type", $action);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":performed_by", $user_id);
        
        // Execute query
        $stmt->execute();
    }
    // Add these methods to the Event class

public function getUpcomingEventsByClient($days = 7) {
    // Get current date
    $current_date = date('Y-m-d');
    
    // Calculate end date
    $end_date = date('Y-m-d', strtotime("+{$days} days"));
    
    // Query
    $query = "SELECT e.*, c.case_number 
              FROM " . $this->table_name . " e
              LEFT JOIN cases c ON e.case_id = c.id
              WHERE e.client_id = ? AND e.event_date BETWEEN ? AND ?
              ORDER BY e.event_date ASC, e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(1, $this->client_id);
    $stmt->bindParam(2, $current_date);
    $stmt->bindParam(3, $end_date);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}

public function getTodayEventsByClient() {
    // Get current date
    $current_date = date('Y-m-d');
    
    // Query
    $query = "SELECT e.*, c.case_number 
              FROM " . $this->table_name . " e
              LEFT JOIN cases c ON e.case_id = c.id
              WHERE e.client_id = ? AND e.event_date = ?
              ORDER BY e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(1, $this->client_id);
    $stmt->bindParam(2, $current_date);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}
// Add these methods to the Event class

public function getUpcomingEventsByAdvocate($days = 7) {
    // Get current date
    $current_date = date('Y-m-d');
    
    // Calculate end date
    $end_date = date('Y-m-d', strtotime("+{$days} days"));
    
    // Query
    $query = "SELECT e.*, c.case_number 
              FROM " . $this->table_name . " e
              LEFT JOIN cases c ON e.case_id = c.id
              WHERE e.advocate_id = ? AND e.event_date BETWEEN ? AND ?
              ORDER BY e.event_date ASC, e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(1, $this->advocate_id);
    $stmt->bindParam(2, $current_date);
    $stmt->bindParam(3, $end_date);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}

public function getTodayEventsByAdvocate() {
    // Get current date
    $current_date = date('Y-m-d');
    
    // Query
    $query = "SELECT e.*, c.case_number 
              FROM " . $this->table_name . " e
              LEFT JOIN cases c ON e.case_id = c.id
              WHERE e.advocate_id = ? AND e.event_date = ?
              ORDER BY e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(1, $this->advocate_id);
    $stmt->bindParam(2, $current_date);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}
// Add this method to the Event class
public function readByAdvocate() {
    // Query
    $query = "SELECT e.*, c.case_number, c.title as case_title
              FROM " . $this->table_name . " e
              LEFT JOIN cases c ON e.case_id = c.id
              WHERE e.advocate_id = ?
              ORDER BY e.event_date ASC, e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameter
    $stmt->bindParam(1, $this->advocate_id);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}

// Add this method to the Event class if it doesn't exist
public function readAll() {
    // Query to get all events with case information
    $query = "SELECT e.*, c.id as case_id, c.case_number, c.title as case_title
              FROM " . $this->table_name . " e
              LEFT JOIN cases c ON e.case_id = c.id
              ORDER BY e.event_date ASC, e.event_time ASC";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Execute query
    $stmt->execute();
    
    return $stmt;
}
}
?>
