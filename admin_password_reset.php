<?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Admin Password Reset Tool</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 900px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        h2 { color: #667eea; margin-top: 30px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin: 15px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin: 15px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin: 15px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .btn { 
            display: inline-block;
            padding: 12px 24px; 
            background: #667eea; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            margin: 10px 5px;
            font-weight: bold;
        }
        .btn:hover { background: #5568d3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔐 Admin Password Reset Tool</h1>";

$conn = getConnection();

// STEP 1: Check if admin user exists
echo "<h2>Step 1: Checking Admin User</h2>";

$result = $conn->query("SELECT * FROM admin_users WHERE username = 'admin'");

if ($result->num_rows === 0) {
    echo "<div class='error'>❌ <strong>No admin user found!</strong></div>";
    echo "<p>Creating admin user now...</p>";
    
    // Create admin user
    $username = 'admin';
    $email = 'admin@makemyholiday.com';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO admin_users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);
    
    if ($stmt->execute()) {
        echo "<div class='success'>✅ Admin user created successfully!</div>";
    } else {
        echo "<div class='error'>❌ Error creating admin user: " . $stmt->error . "</div>";
    }
    $stmt->close();
    
    // Re-fetch
    $result = $conn->query("SELECT * FROM admin_users WHERE username = 'admin'");
}

$admin = $result->fetch_assoc();

echo "<table>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>ID</td><td>" . $admin['id'] . "</td></tr>";
echo "<tr><td>Username</td><td>" . htmlspecialchars($admin['username']) . "</td></tr>";
echo "<tr><td>Email</td><td>" . htmlspecialchars($admin['email']) . "</td></tr>";
echo "<tr><td>Password Hash</td><td><code style='font-size: 10px;'>" . htmlspecialchars(substr($admin['password'], 0, 50)) . "...</code></td></tr>";
echo "</table>";

// STEP 2: Check password format
echo "<h2>Step 2: Password Analysis</h2>";

$current_password = $admin['password'];
$password_length = strlen($current_password);

echo "<div class='info'>";
echo "<strong>Password Length:</strong> " . $password_length . " characters<br>";

if ($password_length < 30) {
    echo "<br><div class='error' style='margin-top: 10px;'>";
    echo "❌ <strong>PROBLEM DETECTED!</strong><br>";
    echo "Your password appears to be stored as PLAIN TEXT: <strong>" . htmlspecialchars($current_password) . "</strong><br>";
    echo "This is why login is failing. Passwords must be hashed!";
    echo "</div>";
} else if (substr($current_password, 0, 4) === '$2y$' || substr($current_password, 0, 4) === '$2a$') {
    echo "✅ Password is properly hashed (BCrypt format)";
} else {
    echo "<div class='warning'>";
    echo "⚠️ Password format is unusual. It should start with '$2y$'";
    echo "</div>";
}
echo "</div>";

// STEP 3: Test password verification
echo "<h2>Step 3: Testing Password Verification</h2>";

$test_passwords = ['admin123', 'admin', 'password', $current_password];

echo "<table>";
echo "<tr><th>Test Password</th><th>Result</th></tr>";

foreach ($test_passwords as $test_pass) {
    if (password_verify($test_pass, $current_password)) {
        echo "<tr style='background: #d4edda;'>";
        echo "<td><strong>" . htmlspecialchars($test_pass) . "</strong></td>";
        echo "<td>✅ <strong>MATCH!</strong> This password works!</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($test_pass) . "</td>";
        echo "<td>❌ Does not match</td>";
        echo "</tr>";
    }
}
echo "</table>";

// STEP 4: Fix the password
echo "<h2>Step 4: Fix Password</h2>";

if (isset($_GET['fix']) && $_GET['fix'] === 'yes') {
    $new_password = 'admin123';
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
    $stmt->bind_param("s", $hashed);
    
    if ($stmt->execute()) {
        echo "<div class='success'>";
        echo "✅ <strong>PASSWORD UPDATED SUCCESSFULLY!</strong><br><br>";
        echo "<strong>New Login Credentials:</strong><br>";
        echo "Username: <code>admin</code><br>";
        echo "Password: <code>admin123</code><br><br>";
        echo "Hash: <code style='font-size: 10px;'>" . htmlspecialchars($hashed) . "</code>";
        echo "</div>";
        
        // Verify it works
        if (password_verify('admin123', $hashed)) {
            echo "<div class='success'>";
            echo "✅ Verification test PASSED! The password will work now.";
            echo "</div>";
        }
        
        echo "<div class='warning' style='margin-top: 20px;'>";
        echo "<strong>⚠️ IMPORTANT SECURITY NOTICE:</strong><br>";
        echo "DELETE THIS FILE IMMEDIATELY!<br>";
        echo "File location: <code>admin_password_reset.php</code>";
        echo "</div>";
        
        echo "<a href='admin/login.php' class='btn'>🔐 Go to Admin Login</a>";
        echo "<a href='?' class='btn btn-danger'>🔄 Run Diagnostics Again</a>";
    } else {
        echo "<div class='error'>❌ Error updating password: " . $stmt->error . "</div>";
    }
    $stmt->close();
} else {
    echo "<div class='info'>";
    echo "Click the button below to reset the admin password to <strong>admin123</strong>";
    echo "</div>";
    echo "<a href='?fix=yes' class='btn' style='font-size: 18px;'>🔧 FIX PASSWORD NOW</a>";
}

// STEP 5: Manual SQL commands
echo "<h2>Step 5: Manual Fix (Alternative Method)</h2>";
echo "<div class='info'>";
echo "<strong>If the automatic fix doesn't work, use this SQL query:</strong><br><br>";
echo "<code style='display: block; padding: 15px; background: #f8f9fa; border: 1px solid #ddd;'>";
echo "UPDATE admin_users<br>";
echo "SET password = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'<br>";
echo "WHERE username = 'admin';";
echo "</code>";
echo "<br><strong>How to use:</strong>";
echo "<ol>";
echo "<li>Open phpMyAdmin: <a href='http://localhost/phpmyadmin/' target='_blank'>http://localhost/phpmyadmin/</a></li>";
echo "<li>Select database: <code>make_my_holiday</code></li>";
echo "<li>Click 'SQL' tab</li>";
echo "<li>Copy and paste the query above</li>";
echo "<li>Click 'Go'</li>";
echo "</ol>";
echo "</div>";

$conn->close();

echo "</div></body></html>";
?>