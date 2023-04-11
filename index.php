<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Database configuration
$dbHost = 'localhost';
$dbUsername = 'root';
$dbPassword = '';
$dbName = 'alko_prices';

// Create a new connection
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create table in database
$sql = 'CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  number INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  bottlesize VARCHAR(255) NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  priceGBP DECIMAL(10, 2) NOT NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  orderamount INT DEFAULT 0
)';

$conn->query($sql);

// Fetch the daily currency exchange rate (EUR to GBP)
$apiKey = '2jJD29Cpm0SHup9EV2ip0aR2QCdsxCWs';
$myUrl = "https://api.apilayer.com/currency_data/live?access_key={$apiKey}&source=EUR&currencies=GBP";
$url = "http://apilayer.net/api/live?access_key={$apiKey}&currencies=GBP&source=EUR&format=1";
$response = file_get_contents($myUrl);
$data = json_decode($response, true);
$exchangeRate = $data['quotes']['EURGBP'];

// Download and parse the Alko price list
$alkoUrl = 'https://www.alko.fi/INTERSHOP/static/WFS/Alko-OnlineShop-Site/-/Alko-OnlineShop/fi_FI/Alkon%20Hinnasto%20Tekstitiedostona/alkon-hinnasto-tekstitiedostona.xlsx';
$spreadsheet = IOFactory::load($alkoUrl);
$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray();

// Process and insert/update data
// Process and insert/update data
foreach ($rows as $row) {
  $number = $row[0]; // Numero
  $name = $row[1]; // Nimi
  $bottleSize = $row[2]; // Pullokoko
  $price = $row[3]; // Hinta

  // Convert price to GBP
  $priceGBP = $price * $exchangeRate;

  // Check if the item already exists in the database
  $sql = "SELECT * FROM products WHERE number = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $number);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
      // Update the existing item in the database
      $sql = "UPDATE products SET name = ?, bottlesize = ?, price = ?, priceGBP = ?, timestamp = NOW() WHERE number = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ssdss", $name, $bottleSize, $price, $priceGBP, $number);
      $stmt->execute();
  } else {
      // Insert a new item into the database
      $sql = "INSERT INTO products (number, name, bottlesize, price, priceGBP, timestamp) VALUES (?, ?, ?, ?, ?, NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ssdds", $number, $name, $bottleSize, $price, $priceGBP);
      $stmt->execute();
  }
}

// Close the database connection
$conn->close();

?>