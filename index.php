<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/object.php';

$app = new \Slim\App;

$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);
$app->get('/', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        return $response->withStatus(200)->write('Hello World!');
    });
});

$app->post('/login', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        $body = $request->getParsedBody();

        $username = filter_var($body["username"]);
        $password = filter_var($body["password"]);

        $data = array('status' => false);

        $db = new DB();
        $db->connect();

        $authUsername = false;
        $authPassword = false;

        $sql = "SELECT * 
                FROM `setting` 
                WHERE 1";
        $result = $db->query($sql);
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row["key"] == "USERNAME" && $row["value"] == $username) {
                $authUsername = true;
            }
            if ($row["key"] == "PASSWORD" && $row["value"] == $password) {
                $authPassword = true;
            }
        }

        $data['status'] = $authUsername && $authPassword;

        $db->close();
        
        return $response->withJson($data);
    });
});

$app->post('/changepwd', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        $body = $request->getParsedBody();

        $oldPassword = trim(filter_var($body["oldpwd"]));
        $newPassword = trim(filter_var($body["newpwd"]));

        $data = array('status' => false);

        $db = new DB();
        $db->connect();

        $authPassword = false;

        $sql = "SELECT * 
                FROM `setting` 
                WHERE 1";
        $result = $db->query($sql);
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row["key"] == "PASSWORD" && $row["value"] == $oldPassword) {
                $authPassword = true;
            }
        }
        
        if ($authPassword) {
            $sql = "UPDATE `setting` 
                    SET `value` = '". $newPassword ."' 
                    WHERE `id` = 2;";
            $result = $db->query($sql);

            $data['status'] = true;
        }

        $db->close();
        
        return $response->withJson($data);
    });
});

$app->get('/number', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        $db = new DB();
        $db->connect();

        $data = array();

        $arrNumber2 = array();
        $arrNumber3 = array();

        $sqlBase = "SELECT n.id as id, n.number as `number`, SUM(t.top) as `top`, SUM(t.bottom) as bottom
                FROM `number` as n 
                LEFT JOIN `transaction` as t 
                ON n.id = t.number_id";

        $additionalQuery2 = $sqlBase." WHERE CHAR_LENGTH(n.number) = '2' GROUP BY n.id, n.number ORDER by n.number ASC";
        $result = $db->query($additionalQuery2);
        while ($row = mysqli_fetch_assoc($result)) {
            $lotto = new Lotto($row['id'], $row['number'], $row['top'], $row['bottom']);
            array_push($arrNumber2, $lotto);
        }

        $additionalQuery3 = $sqlBase." WHERE CHAR_LENGTH(n.number) = '3' GROUP BY n.id, n.number ORDER by n.number ASC";
        $result = $db->query($additionalQuery3);
        while ($row = mysqli_fetch_assoc($result)) {
            $lottoObj = new Lotto($row['id'], $row['number'], $row['top'], $row['bottom']);
            array_push($arrNumber3, $lottoObj);
        }
        
        $data = array_merge($arrNumber2,$arrNumber3);

        $db->close();
        
        return $response->withJson($data);
    });
});

$app->get('/customer', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        $db = new DB();
        $db->connect();

        $data = array();

        $sql = "SELECT *
                FROM `customer`
                WHERE 1
                ORDER by `name` ASC";

        $result = $db->query($sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $userObj = new User($row['id'], $row['name']);
            array_push($data, $userObj);
        }

        $db->close();
        
        return $response->withJson($data);
    });
});

$app->get('/lotto/{numberID}', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        $route = $request->getAttribute('route');
        $numberID = trim($route->getArgument('numberID'));

        $db = new DB();
        $db->connect();

        $data = array();
        $lottoList = array();

        $sql = "SELECT *
                FROM `number`
                WHERE id = '$numberID'";

        $result = $db->query($sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $data['number'] = $row['number'];
        }

        $sql = "SELECT t.id as id, n.number as `number`, c.name as customer, `top`, `bottom`, create_at
                FROM `transaction` as t
                LEFT JOIN `number` as n 
                ON t.number_id = n.id
                LEFT JOIN `customer` as c
                ON t.customer_id = c.id
                WHERE t.number_id = '". $numberID ."'
                ORDER by `create_at` ASC";

        $result = $db->query($sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $lottoDetailObj = new LottoDetail($row['id'], $row['customer'], $row['top'], $row['bottom'], $row['create_at']);
            array_push($lottoList, $lottoDetailObj);
        }

        $data['lotteries'] = $lottoList;

        $db->close();
        
        return $response->withJson($data);
    });
});

$app->get('/customer/{customerID}', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        $route = $request->getAttribute('route');
        $customerID = trim($route->getArgument('customerID'));

        $db = new DB();
        $db->connect();

        $data = array();
        $lottoList = array();

        $sql = "SELECT *
                FROM `customer`
                WHERE id = '$customerID'";

        $result = $db->query($sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $data['customer'] = $row['name'];
        }

        $sql = "SELECT t.id as id, n.number as `number`, c.name as customer, SUM(t.top) as `top`, SUM(t.bottom) as bottom, create_at
                FROM `transaction` as t
                LEFT JOIN `number` as n 
                ON t.number_id = n.id
                LEFT JOIN `customer` as c
                ON t.customer_id = c.id
                WHERE t.customer_id = '". $customerID ."'
                GROUP BY t.id, n.id, n.number
                ORDER BY n.number ASC";

                echo $sql; die;

        $result = $db->query($sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $customerDetailObj = new CustomerDetail($row['id'], $row['number'], $row['top'], $row['bottom'], $row['create_at']);
            array_push($lottoList, $customerDetailObj);
        }

        $data['lotteries'] = $lottoList;

        $db->close();
        
        return $response->withJson($data);
    });
});

$app->post('/lotto', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        $body = $request->getParsedBody();

        $customerID = filter_var($body["customer_id"]);
        $lotteries = $body["lotteries"];

        $db = new DB();
        $db->connect();

        $data = array('status' => false);

        foreach ($lotteries as $lotto) {
            $sql = "SELECT *
                    FROM `number`
                    WHERE `number` = '".trim($lotto["number"])."'";

            $result = $db->query($sql);
            $row = mysqli_fetch_assoc($result);
            
            $numberID = $row["id"];
            $top = trim($lotto["top"]);
            $bottom = trim($lotto["bottom"]);

            $insertSQL = "INSERT INTO `transaction` (`id`, `customer_id`, `number_id`, `top`, `bottom`) 
                        VALUES ('".sha1(getSecertKey().date("Y-m-d H:i:s").$customerID.$numberID)."', '". $customerID ."', '". $numberID ."', '". $top ."', '". $bottom ."');";
            
            $result = $db->query($insertSQL);
            $data['status'] = true;
        }

        $db->close();
        
        return $response->withJson($data);
    });
});

$app->post('/editlotto', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        $body = $request->getParsedBody();

        $lotteries = $body["lotteries"];

        $db = new DB();
        $db->connect();

        $data = array('status' => false);

        foreach ($lotteries as $lotto) {
            $transactionID = trim($lotto["transactionID"]);
            $top = trim($lotto["top"]);
            $bottom = trim($lotto["bottom"]);

            $sql = "UPDATE `transaction` 
                    SET `top` = ". $top .", `bottom` = ". $bottom ."
                    WHERE `id` = '". $transactionID ."' ;";
            
            $result = $db->query($sql);
            $data['status'] = true;
        }

        $db->close();
        
        return $response->withJson($data);
    });
});

$app->post('/deletelotto', function ($request, $response) {
    return checkAuth($request, $response, function($request, $response) {
        $body = $request->getParsedBody();

        $lotteries = $body["lotteries"];

        $db = new DB();
        $db->connect();

        $data = array('status' => false);

        foreach ($lotteries as $lotto) {
            $transactionID = trim($lotto["transactionID"]);

            $sql = "DELETE FROM `transaction` 
                    WHERE `id` = '". $transactionID ."'";

            $result = $db->query($sql);
            $data['status'] = true;
        }

        $db->close();

        return $response->withJson($data);
    });
});

$app->delete('/cleardata', function ($request, $response, $args) {
    return checkAuth($request, $response, function($request, $response) {
        truncateTransaction();
        truncateUser();
        truncateNumber();

        generateUsers();
        generateNumbers();
        return $response->withJson(array('status' => true));
    });
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', '*')
            ->withHeader('Access-Control-Allow-Methods', '*');
});

function truncateTransaction()
{
    $db = new DB();
    $db->connect();
    
    $sql = "DELETE FROM `transaction` WHERE 1";
    $result = $db->query($sql);
    
    $db->close();
}

function truncateUser()
{
    $db = new DB();
    $db->connect();
    
    $sql = "DELETE FROM `customer` WHERE 1";
    $result = $db->query($sql);
    
    $db->close();
}

function truncateNumber()
{
    $db = new DB();
    $db->connect();
    
    $sql = "DELETE FROM `number` WHERE 1";
    $result = $db->query($sql);
    
    $db->close();
}

function generateUsers()
{
    $db = new DB();
    $db->connect();

    foreach (range('A', 'Z') as $char) {
        $sql = "INSERT INTO `customer` (`id`, `name`) VALUES ('".sha1(getSecertKey().sprintf('%s',$char))."', '".sprintf('%s',$char)."');";
        $result = $db->query($sql);
    }

    $db->close();
}

function generateNumbers()
{
    $db = new DB();
    $db->connect();

    foreach (range(0, 99) as $i) {
        $sql = "INSERT INTO `number` (`id`, `number`) VALUES ('".sha1(getSecertKey().sprintf('%02d',$i))."', '".sprintf('%02d',$i)."');";
        $result = $db->query($sql);
    }

    foreach (range(0, 999) as $i) {
        $sql = "INSERT INTO `number` (`id`, `number`) VALUES ('".sha1(getSecertKey().sprintf('%03d',$i))."', '".sprintf('%03d',$i)."');";
        $result = $db->query($sql);
    }

    $db->close();
}


function checkAuth($request, $response, $call) { 
    if (isAuth($request)) {
        return $call($request, $response);
    } else {
        return $response->withStatus(401)->write('authentication error');
    }
}

function isAuth($request)
{
    $secret = getTokenKey();
    return $request->hasHeader('x-session-token') && $request->getHeader('x-session-token')[0] == $secret;
}

function getSecertKey()
{
    return "bowiiandmeandlottoandfastwork";
}

function getTokenKey()
{
    return "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZF91c2VyIjoxMDEyOH0.mOke75-y0VzpApAmqh9FFED1NIjm4TBcDFYgk0bPUfo";
}

$app->run();