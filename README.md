# Enterprise Resource Planning System (ERP)

## PROJECT OVERVIEW

This project is an Enterprise Resource Planning (ERP) System originally developed for the course **"Business Management Systems"** during the second year of the Higher Level Training Cycle (CFGS) in Multiplatform Application Development (DAM) at DigiTech, by Óscar Rosales Roca.

The project has been expanded and enhanced to serve as my **Final Degree Project (TFG)**, focusing on inventory, personnel, and invoicing management.

### Current Features:

The system offers the essential functions of an ERP, including:

* **Authentication and Profiles:** User login and registration (Administrators and Employees).
* **Personnel Management:** Allows employees to modify their own personal data. Administrators have access to full functionality (CRUD: Create, Read, Update, Delete) over all employee profiles.
* **Master Data Management:** Comprehensive management of Clients, Suppliers, Warehouses, and Products.
* **Invoicing:** Generation of **Purchase** invoices (which register the supplier and increase stock in the destination warehouse) and **Sales** invoices (which register the client and deduct stock from the source warehouse).
* **Documentation:** Detailed viewing of invoices (PDF/print simulation).
* **Activity History:** Displays a chronological list of all invoicing movements performed within the system (purchases and sales), serving as a primary record of economic and inventory activity.

The project is under continuous development.

## INSTRUCTIONS FOR USE

To run the ERP locally, you will need a web server environment (such as XAMPP or LAMP/WAMP) and a MySQL/MariaDB database.

### Prerequisites

* IDE (Visual Studio Code, PhpStorm, etc.)
* Web Browser.
* **XAMPP** (or similar) installed.

### Step 1: Initial Setup and Cloning

1.  **Download and Location:** Download the entire repository and place the root folder inside your web server's document root directory.
    * **XAMPP (Windows/Linux):** Save the root folder (which we will refer to as `ERP`) inside `xampp/htdocs/`.

2.  **Configure Application Base Path (CRITICAL):**
    * Open the `config/config_path.php` file.
    * Edit the `BASE_URL` constant to reflect your local server path.
    
    The content you must configure is similar to this (adjust the constant value to your folder's path):

    ```php
    <?php
    // The value must start with a forward slash (/) and MUST NOT end with a forward slash (/)

    // SET YOUR BASE PATH HERE:
    const BASE_URL = "/ERP/ERP-project"; 
    
    // If the root folder were named only 'ERP', it would be:
    // const BASE_URL = "/ERP";
    ?>
    ```

### Step 2: Database Configuration

The project uses MariaDB (included in XAMPP) and needs to be configured for data persistence.

#### Database and Table Creation

1.  Ensure that the **MySQL** module in XAMPP is started.
2.  Import the database schema from `db.sql` (located in the `sql/` folder). You can do this via **phpMyAdmin** or by using the XAMPP Shell:

    ```bash
    # In the XAMPP Shell (MariaDB/MySQL)
    mysql -u root -p
    # NOTE: Using '-p' indicates the system will prompt for the password (which may be empty).
    # If you know your 'root' user has no password, you can omit '-p'.

    # Inside the MySQL terminal:
    CREATE DATABASE <your_database_name>;
    USE <your_database_name>;
    # The 'SOURCE' command executes the SQL script.
    SOURCE <absolute_or_relative_path_to_db.sql>; 
    ```

#### PHP Connection Configuration

1.  Open the `config/config_db.php` file.
2.  Fill in the connection credentials with your MariaDB/MySQL server details:

    ```php
    // config/config_db.php
    $host = "localhost";
    $db = "the_name_you_gave_to_your_db"; 
    $user = "your_mysql_user (usually root)";
    $pass = "your_mysql_password (usually empty by default)";
    ```

### Step 3: Execution

1.  Ensure that both the **Apache** and **MySQL** modules are started in XAMPP.
2.  Open your browser and navigate to your project's path (replace `/ERP/ERP-project` with your `BASE_URL`):

    ```
    http://localhost/ERP/ERP-project/index.php
    ```

All set! You can now register a new user and start using the system. 😄

---

## PENDING TASKS (THINGS TO DO)

| Task | Status |
| :--- | :--- |
| **Dynamic Paths (`BASE_URL`)** | 😄 Done |
| **README Improvement** | 😄 Done |
| **Employee Management (Admin CRUD)** | 😱 Pending |
| **Implement User Image Functionality** | 😱 Pending |
| **Testing (Unit Tests)** | 😱 Pending |