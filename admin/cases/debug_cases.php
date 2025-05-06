<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is an admin
requireLogin();
requireUserType('admin');

// Get database connection
$conn = getDBConnection();

// Simple query to get all cases
$query = "SELECT * FROM cases";
$result = $conn->query($query);

echo "<h1>Debug Cases</h1>";
echo "<p>Total cases found: " . $result->num_rows . "</p>";

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Case Number</th><th>Title</th><th>Status</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['case_id'] . "</td>";
        echo "<td>" . $row['case_number'] . "</td>";
        echo "<td>" . $row['title'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No cases found in the database.</p>";
}
?>