<?php
namespace App;

use SQLite3;
use Exception;

class Persist {
    public SQLite3 $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function addCustomer($data) {
        // Prepare the SQL insert statement
        $query = $this->db->prepare('
            INSERT INTO "Customer" ("id", "name", "email", "annualSpend", "time")
            VALUES (:id, :name, :email,:annualSpend, :time)
        ');

        // Bind values to the prepared statement
        $query->bindValue(':id', $data['userId'], SQLITE3_TEXT);
        $query->bindValue(':name', $data['name'], SQLITE3_TEXT);
        $query->bindValue(':email', $data['email'], SQLITE3_TEXT);
        $query->bindValue(':annualSpend', $data['annualSpend'], SQLITE3_FLOAT);
        $query->bindValue(':time', $data['time'], SQLITE3_INTEGER);

        // Execute the statement to insert the data into the database
        $query->execute();
    }

    public function getCustomer($customerId) {
        // Prepare and execute a SELECT query to fetch the customer by ID
        $query = $this->db->prepare('
            SELECT *,
              CASE
                WHEN annualSpend >= 10000 AND (strftime(\'%s\',\'now\') - time) <= 60*60*24*30*6 THEN \'Platinum\'
                WHEN annualSpend >= 1000 AND (strftime(\'%s\',\'now\') - time) <= 60*60*24*30*12 THEN \'Gold\'
                ELSE \'Silver\'
              END AS tier
            FROM "Customer"
            WHERE id = :id
        ');
        $query->bindValue(':id', $customerId, SQLITE3_TEXT);

        $result = $query->execute();
        $customer = $result->fetchArray(SQLITE3_ASSOC);

        // Close the result and DB connection
        $result->finalize();

        return $customer;
    }


    public function getCustomersByNameOrEmail($name, $email) {

        if ($name && !$email) {
            // Prepare and execute a SELECT query to fetch the customer by name
            $query = $this->db->prepare('SELECT *,
               CASE
                 WHEN annualSpend >= 10000 AND (strftime(\'%s\',\'now\') - time) <= 60*60*24*30*6 THEN \'Platinum\'
                 WHEN annualSpend >= 1000 AND (strftime(\'%s\',\'now\') - time) <= 60*60*24*30*12 THEN \'Gold\'
                 ELSE \'Silver\'
               END AS tier
             FROM "Customer" WHERE "name" LIKE :name');
            $query->bindValue(':name', '%' . $name . '%', SQLITE3_TEXT);
        } else if (!$name && $email) {
            // Prepare and execute a SELECT query to fetch the customer by email
            $query = $db->prepare('SELECT *,
                  CASE
                    WHEN annualSpend >= 10000 AND (strftime(\'%s\',\'now\') - time) <= 60*60*24*30*6 THEN \'Platinum\'
                    WHEN annualSpend >= 1000 AND (strftime(\'%s\',\'now\') - time) <= 60*60*24*30*12 THEN \'Gold\'
                    ELSE \'Silver\'
                  END AS tier
             FROM "Customer" WHERE "email" LIKE :email');
            $query->bindValue(':email', '%' . $email . '%', SQLITE3_TEXT);
        }

        $result = $query->execute();
        $customers = [];
        while ($customer = $result->fetchArray(SQLITE3_ASSOC)) {
            $customers[] = $customer;
        }

        // Close the result and DB connection
        $result->finalize();

        return $customers;
    }

    public function updateCustomer($data, $customerId, $fieldsToUpdate, $params) {
        // Check if the customer exists before updating
        $check = $this->db->prepare('SELECT COUNT(*) as count FROM "Customer" WHERE "id" = :id');
        $check->bindValue(':id', $customerId, SQLITE3_TEXT);
        $checkResult = $check->execute()->fetchArray(SQLITE3_ASSOC);

        if ($checkResult['count'] == 0) {
            return "not found";
        }

        // Dynamically build and execute the update query
        $sql = 'UPDATE "Customer" SET ' . implode(', ', $fieldsToUpdate) . ' WHERE "id" = :id';
        $query = $this->db->prepare($sql);

        foreach ($params as $key => [$value, $type]) {
            $query->bindValue($key, $value, $type);
        }

        $query->bindValue(':id', $customerId, SQLITE3_TEXT);
        $query->execute();

        return "success";
    }

    public function deleteCustomer($customerId) {
        // Check if the customer exists
        $checkQuery = $this->db->prepare('SELECT * FROM "Customer" WHERE "id" = :id');
        $checkQuery->bindValue(':id', $customerId, SQLITE3_TEXT);
        $checkResult = $checkQuery->execute();
        $customer = $checkResult->fetchArray(SQLITE3_ASSOC);

        if (!$customer) {
            return "not found";
        }

        // Delete the customer
        $deleteQuery = $this->db->prepare('DELETE FROM "Customer" WHERE "id" = :id');
        $deleteQuery->bindValue(':id', $customerId, SQLITE3_TEXT);
        $deleteQuery->execute();

        return "success";
    }
}