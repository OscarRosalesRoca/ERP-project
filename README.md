# PRESENTATION

ERP project created for the subject ‘Business Management Systems’ at DigiTech by Óscar Rosales Roca.
Work in progress. I intend to continue improving the functionalities of the project once the delivery is finished.

## INSTRUCTIONS FOR USE

First of all you must have an IDE, a web browser and XAMPP installed on your computer.

To run ERP you will need to download the entire repository and save the root folder inside the XAMPP folders, specifically in ‘xampp/htdocs/...’. You can call the root folder whatever you want but from now on we will call it ‘ERP’.

Inside the repository you will find the following folders:

```text
ERP/
│
├── assets/
│   ├── css/
│   ├── img/
│   └── js/
├── config/
│   └── config_db.php
├── includes/
│   ├── functions/
│   │     ├── almacen/
│   │     ├── cliente/
│   │     ├── empleado/
│   │     ├──producto/
│   │     └── proveedor/
│   └── connection.php
├── modules/
│   ├── home/
│   │    └── sections/
│   ├── login/
│   └── register/
├── sql/
│    └── db.sql
├── index.php
└── README.md
```

Only the files to be used are mentioned, the rest have been omitted.

Before configuring "connection.php," you'll need to create the database.

Using XAMPP, you'll need to click "Start" in the "MySQL" module and then click the "Shell" button in the right-hand menu. Once in the terminal, start MariaDB with:

```sql
mysql -u root -p erp < path/db.sql -- In the file path you should write the absolute or relative path of your db.sql file, within ../ERP/sql/db.sql. Also in -p erp you can type whatever name you want.
```

Inside the folder config/ in ‘config_db.php’ you will have to configure:

```php
$host = "localhost";

$db = "your database name";

$user = "your user, you can put root";

$pass = "your password, MySQL leaves it empty by default when creating a database";
```

After creating the database and configuring the connection, all we have to do is return to XAMPP and click "Admin" in the "Apache" section. This will open a window in our browser with the path [http://localhost/dashboard/](http://localhost/dashboard/). We'll need to change it to [http://localhost/ERP/index.php](http://localhost/ERP/index.php).

All set.😄

## THINGS TO DO

- Finsih invoices.
- Fix paths
- Add search function.
- Add user img function.
