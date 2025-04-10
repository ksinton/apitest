<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Ramsey\Uuid\Uuid;
use App\Persist;

// Include the database initialization file
require __DIR__ . '/../src/db_init.php';

return function (App $app) {

    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler would go here
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Welcome to our customer API');
        return $response;
    });

    //add a new customer
    $app->post('/customers', function (Request $request, Response $response) {

        $data = $request->getParsedBody();

        // Sanitize amd clip inputs
        $data['name'] = isset($data['name'])
                            ? mb_substr(htmlspecialchars(strip_tags(trim($data['name'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 0, 100)
                            : '';
        $data['email'] = isset($data['email'])
                            ? mb_substr(htmlspecialchars(strip_tags(trim($data['email'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 0, 255)
                            : '';
        $data['annualSpend'] = isset($data['annualSpend']) ? filter_var($data['annualSpend'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : '';

        // Validate the "name" field (should be a non-empty string)
        if (empty($data['name']) || !is_string($data['name'])) {
            $errors[] = '"name" should be a non-empty string.';
        }

        // Validate the "email" field (should be a non-empty string)
        if (empty($data['email']) || !is_string($data['email'])) {
            $errors[] = '"email" should be a non-empty string.';
        }

        // Validate the "annualSpend" field (should be a non-empty numeric value)
        if (empty($data['annualSpend']) || !is_numeric($data['annualSpend'])) {
            $errors[] = '"annualSpend" should be a non-empty numeric value.';
        }

        // If there are errors, return them as a JSON response
        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withStatus(400); // Return 400 Bad Request status
        }

        // Initialize the database and capture the message (success or error)
        $db = new SQLite3(__DIR__ . '/../sqlite/customers.sqlite', SQLITE3_OPEN_READWRITE);
        $db->enableExceptions(true);

        $initMessage = initializeDatabase($db);

        $data['userId'] = Uuid::uuid4();
        $data['time'] = time();

        $persist = new Persist($db);

        try {
            $persist->addCustomer($data);
        } catch (Exception $e) {
            // Handle any exceptions (e.g., database errors)
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withStatus(500); // Return 500 Internal Server Error
        }

        $jsonData = json_encode($data);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Customer deleted successfully',
            'data' => $data
        ]));

        return $response;
    });

    // Get customer by id
    $app->get('/customers/{id}', function (Request $request, Response $response, array $args) {
    $customerId = $args['id'];

    // Initialize the database and capture the message (success or error)

    // Open SQLite3 database
    // Initialize and store the SQLite3 connection in $this->db
    $db = new SQLite3(__DIR__ . '/../sqlite/customers.sqlite', SQLITE3_OPEN_READONLY);
    $db->enableExceptions(true);

    $initMessage = initializeDatabase($db);
    $persist = new Persist($db);

        try {
            $customer = $persist->getCustomer($customerId);

            if ($customer) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => $customer
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $response->getBody()->write(json_encode(['error' => 'Customer not found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });


    // GET customers by email or by name
    $app->get('/customers', function (Request $request, Response $response) {
        $name = $request->getQueryParams()['name'] ?? null;
        $email = $request->getQueryParams()['email'] ?? null;

        if (!$name && !$email) {
            $response->getBody()->write(json_encode(['error' => 'Missing query parameter']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        } else if ($name && $email) {
            $response->getBody()->write(json_encode(['error' => 'Conflicting query parameters']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Initialize the database and capture the message (success or error)
        $db = new SQLite3(__DIR__ . '/../sqlite/customers.sqlite', SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);

        $initMessage = initializeDatabase($db);
        $persist = new Persist($db);

        try {

            $customers = $persist->getCustomersByNameOrEmail($name, $email);

            if ($customers) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => $customers
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $response->getBody()->write(json_encode(['error' => 'No customers found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Update customer by ID
    $app->put('/customers/{id}', function (Request $request, Response $response, array $args) {
        $customerId = $args['id'];
        $data = $request->getParsedBody();
        $fieldsToUpdate = [];
        $params = [];

        // Handle each potential field
        if (isset($data['name'])) {
            $name = mb_substr(htmlspecialchars(strip_tags(trim($data['name'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 0, 100);
            $fieldsToUpdate[] = '"name" = :name';
            $params[':name'] = [$name, SQLITE3_TEXT];
        }

        if (isset($data['email'])) {
            $email = mb_substr(htmlspecialchars(strip_tags(trim($data['email'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 0, 255);
            $fieldsToUpdate[] = '"email" = :email';
            $params[':email'] = [$email, SQLITE3_TEXT];
        }

        if (isset($data['annualSpend'])) {
            $annualSpend = filter_var($data['annualSpend'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $fieldsToUpdate[] = '"annualSpend" = :annualSpend';
            $params[':annualSpend'] = [$annualSpend, SQLITE3_FLOAT];
        }

        // Always update the time if any field is being updated
        if (!empty($fieldsToUpdate)) {
            $fieldsToUpdate[] = '"time" = :time';
            $params[':time'] = [time(), SQLITE3_INTEGER];
        } else {
            $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Initialize the database and capture the message (success or error)
        $db = new SQLite3(__DIR__ . '/../sqlite/customers.sqlite', SQLITE3_OPEN_READWRITE);
        $db->enableExceptions(true);

        $initMessage = initializeDatabase($db);
        $persist = new Persist($db);

        try {
            $result = $persist->updateCustomer($data, $customerId, $fieldsToUpdate,$params);

            if($result === 'not found') {
                $response->getBody()->write(json_encode(['error' => 'Customer not found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Customer updated successfully',
                'updatedFields' => array_keys($params)
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Delete customer by ID
    $app->delete('/customers/{id}', function (Request $request, Response $response, array $args) {
        $customerId = $args['id'];

        // Initialize the database and capture the message (success or error)
        $db = new SQLite3(__DIR__ . '/../sqlite/customers.sqlite', SQLITE3_OPEN_READWRITE);
        $db->enableExceptions(true);

        $initMessage = initializeDatabase($db);
        $persist = new Persist($db);

        try {
            $result = $persist->deleteCustomer($customerId);

            if($result === 'not found') {
                $response->getBody()->write(json_encode(['error' => 'Customer not found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Customer deleted successfully',
                'id' => $customerId
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

};
