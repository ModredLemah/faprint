## FA Print Project - Updated with Location Features

This project has been updated to include location-based features for vendors and students, along with a refined database schema and an Entity-Relationship (ER) diagram.

### Project Structure

- `fa print/`: Contains the HTML frontend files.
- `api/`: Contains the PHP backend files for database interaction and authentication.
- `database.sql`: SQL script to set up the MySQL database.
- `faprint_er_diagram.png`: Entity-Relationship diagram of the database.

### New Features

1.  **Vendor Map Location**: Vendors can now set their active location on a map.
2.  **Student Map View**: Students can view nearby vendors on a map, including their distance and status (active/busy).
3.  **Updated API**: The `api/auth.php` file has been updated to handle vendor location saving and retrieval.

### Setup Instructions

#### 1. Database Setup

1.  **Create Database**: Create a MySQL database named `faprint`.
    ```sql
    CREATE DATABASE IF NOT EXISTS faprint;
    USE faprint;
    ```
2.  **Import Schema**: Import the `database.sql` file into your `faprint` database.
    ```bash
    mysql -u your_username -p faprint < database.sql
    ```
    (Replace `your_username` with your MySQL username. You will be prompted for your password.)

#### 2. PHP Backend Setup

1.  **Place Files**: Place the `api/` directory and its contents (`db.php`, `auth.php`, `document_upload.php`) in your web server's root directory or a subdirectory accessible by your frontend.
2.  **Configure `db.php`**: Ensure the database connection details in `api/db.php` are correct for your environment.
    ```php
    <?php
    $host = 'localhost';
    $db   = 'faprint';
    $user = 'root'; // Your MySQL username
    $pass = '';     // Your MySQL password

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
    ?>
    ```

#### 3. Frontend Setup

1.  **Place Files**: Place the contents of the `fa print/` directory in your web server's root directory or a subdirectory.
2.  **Access**: Open `fa_print_landing.html` or `fa_print_student.html` in your web browser.

### Database ER Diagram

Refer to `faprint_er_diagram.png` for a visual representation of the database schema.

---

**Note**: The frontend still uses `localStorage` for some data persistence for demo purposes. For a full production environment, you would integrate all data interactions with the PHP backend via AJAX calls.
