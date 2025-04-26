<?php
class Message {
    private $conn;
    private $table_name = "messages";
    
    // Message properties
    public $id;
    public $sender_id;
    public $recipient_id;
    public $subject;
    public $message;
    public $attachment;
    public $attachment_name;
    public $is_read;
    public $is_starred;
    public $is_deleted_by_sender;
    public $is_deleted_by_recipient;
    public $parent_id;
    public $created_at;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create message
    public function create() {
        // Sanitize inputs
        $this->sender_id = htmlspecialchars(strip_tags($this->sender_id));
        $this->recipient_id = htmlspecialchars(strip_tags($this->recipient_id));
        $this->subject = htmlspecialchars(strip_tags($this->subject));
        // Don't strip tags from message to allow HTML formatting
        $this->attachment = !is_null($this->attachment) ? htmlspecialchars(strip_tags($this->attachment)) : null;
        $this->attachment_name = !is_null($this->attachment_name) ? htmlspecialchars(strip_tags($this->attachment_name)) : null;
        
        $this->is_read = htmlspecialchars(strip_tags($this->is_read));
        $this->is_starred = htmlspecialchars(strip_tags($this->is_starred));
        $this->is_deleted_by_sender = htmlspecialchars(strip_tags($this->is_deleted_by_sender));
        $this->is_deleted_by_recipient = htmlspecialchars(strip_tags($this->is_deleted_by_recipient));
        $this->parent_id = !empty($this->parent_id) ? htmlspecialchars(strip_tags($this->parent_id)) : null;
        
        // Query
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    sender_id = :sender_id,
                    recipient_id = :recipient_id,
                    subject = :subject,
                    message = :message,
                    attachment = :attachment,
                    attachment_name = :attachment_name,
                    is_read = :is_read,
                    is_starred = :is_starred,
                    is_deleted_by_sender = :is_deleted_by_sender,
                    is_deleted_by_recipient = :is_deleted_by_recipient,
                    parent_id = :parent_id,
                    created_at = NOW()";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":sender_id", $this->sender_id);
        $stmt->bindParam(":recipient_id", $this->recipient_id);
        $stmt->bindParam(":subject", $this->subject);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":attachment", $this->attachment);
        $stmt->bindParam(":attachment_name", $this->attachment_name);
        $stmt->bindParam(":is_read", $this->is_read);
        $stmt->bindParam(":is_starred", $this->is_starred);
        $stmt->bindParam(":is_deleted_by_sender", $this->is_deleted_by_sender);
        $stmt->bindParam(":is_deleted_by_recipient", $this->is_deleted_by_recipient);
        $stmt->bindParam(":parent_id", $this->parent_id);
        
        // Execute query
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Read single message
    public function readOne() {
        // Query
        $query = "SELECT m.*, 
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    s.profile_image as sender_profile_image,
                    CONCAT(r.first_name, ' ', r.last_name) as recipient_name,
                    r.profile_image as recipient_profile_image
                FROM " . $this->table_name . " m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                WHERE m.id = ?
                LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get row count
        $num = $stmt->rowCount();
        
        // If message exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->sender_id = $row['sender_id'];
            $this->recipient_id = $row['recipient_id'];
            $this->subject = $row['subject'];
            $this->message = $row['message'];
            $this->attachment = $row['attachment'];
            $this->attachment_name = $row['attachment_name'];
            $this->is_read = $row['is_read'];
            $this->is_starred = $row['is_starred'];
            $this->is_deleted_by_sender = $row['is_deleted_by_sender'];
            $this->is_deleted_by_recipient = $row['is_deleted_by_recipient'];
            $this->parent_id = $row['parent_id'];
            $this->created_at = $row['created_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Get messages by user (inbox)
    public function getMessagesByUser($user_id) {
        // Query
        $query = "SELECT m.*, 
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    s.profile_image as sender_profile_image
                FROM " . $this->table_name . " m
                LEFT JOIN users s ON m.sender_id = s.id
                WHERE m.recipient_id = ? 
                AND m.is_deleted_by_recipient = 0
                ORDER BY m.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $user_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get sent messages by user
    public function getSentMessagesByUser($user_id) {
        // Query
        $query = "SELECT m.*, 
                    CONCAT(r.first_name, ' ', r.last_name) as recipient_name,
                    r.profile_image as recipient_profile_image
                FROM " . $this->table_name . " m
                LEFT JOIN users r ON m.recipient_id = r.id
                WHERE m.sender_id = ? 
                AND m.is_deleted_by_sender = 0
                ORDER BY m.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $user_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get starred messages by user
    public function getStarredMessagesByUser($user_id) {
        // Query
        $query = "SELECT m.*, 
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    s.profile_image as sender_profile_image,
                    CONCAT(r.first_name, ' ', r.last_name) as recipient_name,
                    r.profile_image as recipient_profile_image
                FROM " . $this->table_name . " m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                WHERE (m.recipient_id = ? OR m.sender_id = ?)
                AND m.is_starred = 1
                AND ((m.recipient_id = ? AND m.is_deleted_by_recipient = 0) OR (m.sender_id = ? AND m.is_deleted_by_sender = 0))
                ORDER BY m.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $user_id);
        $stmt->bindParam(3, $user_id);
        $stmt->bindParam(4, $user_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get trash messages by user
    public function getTrashMessagesByUser($user_id) {
        // Query
        $query = "SELECT m.*, 
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    s.profile_image as sender_profile_image,
                    CONCAT(r.first_name, ' ', r.last_name) as recipient_name,
                    r.profile_image as recipient_profile_image
                FROM " . $this->table_name . " m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                WHERE ((m.recipient_id = ? AND m.is_deleted_by_recipient = 1) OR (m.sender_id = ? AND m.is_deleted_by_sender = 1))
                ORDER BY m.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $user_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get unread message count
    public function getUnreadCount($user_id) {
        // Query
        $query = "SELECT COUNT(*) as unread_count
                FROM " . $this->table_name . "
                WHERE recipient_id = ? 
                AND is_read = 0
                AND is_deleted_by_recipient = 0";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $user_id);
        
        // Execute query
        $stmt->execute();
        
        // Get row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['unread_count'];
    }
    
    // Mark message as read
    public function markAsRead() {
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET is_read = 1
                WHERE id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Update star status
    public function updateStarStatus() {
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET is_starred = :is_starred
                WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":is_starred", $this->is_starred);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Update delete status
    public function updateDeleteStatus() {
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET is_deleted_by_sender = :is_deleted_by_sender,
                    is_deleted_by_recipient = :is_deleted_by_recipient
                WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":is_deleted_by_sender", $this->is_deleted_by_sender);
        $stmt->bindParam(":is_deleted_by_recipient", $this->is_deleted_by_recipient);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Permanently delete message
    public function delete() {
        // Query
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Search messages
    public function search($keywords, $user_id) {
        // Query
        $query = "SELECT m.*, 
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    s.profile_image as sender_profile_image,
                    CONCAT(r.first_name, ' ', r.last_name) as recipient_name,
                    r.profile_image as recipient_profile_image
                FROM " . $this->table_name . " m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.recipient_id = r.id
                WHERE ((m.recipient_id = ? AND m.is_deleted_by_recipient = 0) OR (m.sender_id = ? AND m.is_deleted_by_sender = 0))
                AND (m.subject LIKE ? OR m.message LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)
                ORDER BY m.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";
        
        // Bind parameters
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $user_id);
        $stmt->bindParam(3, $keywords);
        $stmt->bindParam(4, $keywords);
        $stmt->bindParam(5, $keywords);
        $stmt->bindParam(6, $keywords);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
}
?>