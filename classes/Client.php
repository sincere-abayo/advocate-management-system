<?php
class Client {
    private $conn;
    private $table_name = "clients";
    
    // Client properties
    public $id;
    public $user_id;
    public $occupation;
    public $company;
    public $reference_source;
    public $notes;
    
     // User properties (from joined user table)
     public $first_name;
     public $last_name;
     public $email;
     public $phone;
     public $address;
     public $profile_image;
     public $is_active;
     public $created_at;
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create client
    public function create() {
        // Sanitize inputs
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->occupation = htmlspecialchars(strip_tags($this->occupation));
        $this->company = htmlspecialchars(strip_tags($this->company));
        $this->reference_source = htmlspecialchars(strip_tags($this->reference_source));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        
        // Query
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    user_id = :user_id,
                    occupation = :occupation,
                    company = :company,
                    reference_source = :reference_source,
                    notes = :notes";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":occupation", $this->occupation);
        $stmt->bindParam(":company", $this->company);
        $stmt->bindParam(":reference_source", $this->reference_source);
        $stmt->bindParam(":notes", $this->notes);
        
        // Execute query
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Read all clients
    public function read() {
        // Query
        $query = "SELECT c.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image, u.is_active
                FROM " . $this->table_name . " c
                LEFT JOIN users u ON c.user_id = u.id
                ORDER BY u.first_name ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read single client
  // Read single client
public function readOne() {
    // Query
    $query = "SELECT c.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.profile_image, u.is_active, u.created_at
            FROM " . $this->table_name . " c
            LEFT JOIN users u ON c.user_id = u.id
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
    
    // If client exists
    if($num > 0) {
        // Get record details
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set properties
        $this->id = $row['id'];
        $this->user_id = $row['user_id'];
        $this->occupation = $row['occupation'];
        $this->company = $row['company'];
        $this->reference_source = $row['reference_source'];
        $this->notes = $row['notes'];
        
        // Set user properties
        $this->first_name = $row['first_name'];
        $this->last_name = $row['last_name'];
        $this->email = $row['email'];
        $this->phone = $row['phone'];
        $this->address = $row['address'];
        $this->profile_image = $row['profile_image'];
        $this->is_active = $row['is_active'];
        $this->created_at = $row['created_at'];
        
        return true;
    }
    
    return false;
}

    
    // Read client by user ID
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
        
        // If client exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set properties
            $this->id = $row['id'];
            $this->occupation = $row['occupation'];
            $this->company = $row['company'];
            $this->reference_source = $row['reference_source'];
            $this->notes = $row['notes'];
            
            return true;
        }
        
        return false;
    }
    
    // Update client
    public function update() {
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->occupation = htmlspecialchars(strip_tags($this->occupation));
        $this->company = htmlspecialchars(strip_tags($this->company));
        $this->reference_source = htmlspecialchars(strip_tags($this->reference_source));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        
        // Query
        $query = "UPDATE " . $this->table_name . "
                SET
                    occupation = :occupation,
                    company = :company,
                    reference_source = :reference_source,
                    notes = :notes
                WHERE
                    id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":occupation", $this->occupation);
        $stmt->bindParam(":company", $this->company);
        $stmt->bindParam(":reference_source", $this->reference_source);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Delete client
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
    
    // Search clients
    public function search($keywords) {
        // Query
        $query = "SELECT c.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image, u.is_active
                FROM " . $this->table_name . " c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE
                    c.occupation LIKE ? OR
                    c.company LIKE ? OR
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
    
    // Count total clients
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
    // Add this method to the LegalCase class

public function getClientsByAdvocate($advocate_id) {

    $query = "SELECT DISTINCT clients.id AS client_id, clients.first_name, clients.last_name, clients.email, clients.phone, 

                     COUNT(cases.id) AS case_count

              FROM clients

              INNER JOIN cases ON clients.id = cases.client_id

              WHERE cases.advocate_id = :advocate_id

              GROUP BY clients.id";

    $stmt = $this->conn->prepare($query);

    $stmt->bindParam(':advocate_id', $advocate_id);

    $stmt->execute();

    return $stmt;

}

public function countByClient() {
    // Query to count cases for a specific client
    $query = "SELECT COUNT(*) as total FROM cases WHERE client_id = ?";
    
    // Prepare statement
    $stmt = $this->conn->prepare($query);
    
    // Bind parameter
    $stmt->bindParam(1, $this->id);  // Use $this->id instead of $this->client_id
    
    // Execute query
    $stmt->execute();
    
    // Get row
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}


}
?>
