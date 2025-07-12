<?php
// Image Migration Script
// This script moves all images from admin/uploads to uploads and updates database paths

require_once 'dbh.inc.php';

echo "<h2>Image Migration Script</h2>";
echo "<p>Moving images from admin/uploads to uploads and updating database paths...</p>";

// Check if admin/uploads directory exists
if (!is_dir('admin/uploads')) {
    echo "<p>❌ admin/uploads directory does not exist.</p>";
    exit;
}

// Check if main uploads directory exists, create if not
if (!is_dir('uploads')) {
    if (mkdir('uploads', 0755, true)) {
        echo "<p>✅ Created uploads directory.</p>";
    } else {
        echo "<p>❌ Could not create uploads directory.</p>";
        exit;
    }
}

// Get all image files from admin/uploads
$adminUploadsPath = 'admin/uploads/';
$mainUploadsPath = 'uploads/';

$files = glob($adminUploadsPath . '*');
$movedFiles = [];
$errors = [];

echo "<h3>Step 1: Moving Files</h3>";

foreach ($files as $file) {
    if (is_file($file)) {
        $filename = basename($file);
        $newPath = $mainUploadsPath . $filename;
        
        // Check if file already exists in main uploads
        if (file_exists($newPath)) {
            echo "<p>⚠️ File already exists in main uploads: $filename</p>";
            continue;
        }
        
        // Move the file
        if (copy($file, $newPath)) {
            echo "<p>✅ Moved: $filename</p>";
            $movedFiles[] = $filename;
        } else {
            echo "<p>❌ Failed to move: $filename</p>";
            $errors[] = $filename;
        }
    }
}

echo "<h3>Step 2: Updating Database Paths</h3>";

// Update database paths for images that were moved
if (!empty($movedFiles)) {
    foreach ($movedFiles as $filename) {
        // Find reports with this image path
        $oldPaths = [
            'admin/uploads/' . $filename,
            'uploads/' . $filename, // in case it was stored as relative path
            $filename // in case only filename was stored
        ];
        
        foreach ($oldPaths as $oldPath) {
            $sql = "UPDATE Report SET image_path = ? WHERE image_path = ?";
            $stmt = mysqli_prepare($connection, $sql);
            $newPath = 'uploads/' . $filename;
            mysqli_stmt_bind_param($stmt, 'ss', $newPath, $oldPath);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_stmt_affected_rows($stmt);
                if ($affected > 0) {
                    echo "<p>✅ Updated $affected record(s) from '$oldPath' to '$newPath'</p>";
                }
            } else {
                echo "<p>❌ Failed to update database for: $oldPath</p>";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

echo "<h3>Step 3: Removing Original Files</h3>";

// Remove original files from admin/uploads after successful migration
foreach ($movedFiles as $filename) {
    $originalFile = $adminUploadsPath . $filename;
    if (file_exists($originalFile)) {
        if (unlink($originalFile)) {
            echo "<p>✅ Removed original: $filename</p>";
        } else {
            echo "<p>❌ Failed to remove original: $filename</p>";
        }
    }
}

echo "<h3>Step 4: Verification</h3>";

// Verify the migration
$sql = "SELECT ReportID, item_name, image_path FROM Report WHERE image_path IS NOT NULL AND image_path != ''";
$result = mysqli_query($connection, $sql);

if ($result) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Report ID</th><th>Item Name</th><th>Image Path</th><th>File Exists?</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['ReportID'] . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['image_path']) . "</td>";
        
        if (file_exists($row['image_path'])) {
            echo "<td>✅ Yes</td>";
        } else {
            echo "<td>❌ No</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Migration Summary</h3>";
echo "<p>✅ Files moved: " . count($movedFiles) . "</p>";
echo "<p>❌ Errors: " . count($errors) . "</p>";

if (empty($errors)) {
    echo "<p><strong>🎉 Migration completed successfully!</strong></p>";
    echo "<p>All images should now be accessible from the dashboard.</p>";
} else {
    echo "<p><strong>⚠️ Migration completed with some errors.</strong></p>";
    echo "<p>Please check the errors above and fix them manually.</p>";
}

echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
