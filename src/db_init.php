<?php
// db_init.php


function initializeDatabase($db) {
    try {
        // Create the table if it does not exist
        $db->query('CREATE TABLE IF NOT EXISTS "Customer" (
            "id" VARCHAR PRIMARY KEY NOT NULL,
            "name" VARCHAR NOT NULL,
            "email" VARCHAR NOT NULL,
            "annualSpend" REAL,
            "time" INTEGER
        )');

        // Return success message
        return "Database and tables initialized successfully!";
    } catch (Exception $e) {
        // Handle errors and return error message
        return "Error initializing database: " . $e->getMessage();
    }
}