<?php
use \Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/object.php';

$app = new \Slim\App;

$app->get('/', function ($request, $response, $args) {
    return checkAuth($request, $response, function ($request, $response) {
        return $response->withStatus(200)->write('Hello World!');
    });
});

$app->post('/login', function ($request, $response, $args) {
    return checkAuth($request, $response, function ($request, $response) {
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
    return checkAuth($request, $response, function ($request, $response) {
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
                    SET `value` = '" . $newPassword . "'
                    WHERE `id` = 2;";
            $result = $db->query($sql);

            $data['status'] = true;
        }

        $db->close();

        return $response->withJson($data);
    });
});

$app->get('/number', function ($request, $response, $args) {
    return checkAuth($request, $response, function ($request, $response) {
        $db = new DB();
        $db->connect();

        $data = array();

        $arrNumber2 = array();
        $arrNumber3 = array();

        $sqlBase = "SELECT n.id as id, n.number as `number`, SUM(t.top) as `top`, SUM(t.bottom) as bottom
                FROM `number` as n
                LEFT JOIN `transaction` as t
                ON n.id = t.number_id";

        $additionalQuery2 = $sqlBase . " WHERE CHAR_LENGTH(n.number) = '2' GROUP BY n.id, n.number ORDER by n.number ASC";
        $result = $db->query($additionalQuery2);
        while ($row = mysqli_fetch_assoc($result)) {
            $lotto = new Lotto($row['id'], $row['number'], $row['top'], $row['bottom']);
            array_push($arrNumber2, $lotto);
        }

        $additionalQuery3 = $sqlBase . " WHERE CHAR_LENGTH(n.number) = '3' GROUP BY n.id, n.number ORDER by n.number ASC";
        $result = $db->query($additionalQuery3);
        while ($row = mysqli_fetch_assoc($result)) {
            $lottoObj = new Lotto($row['id'], $row['number'], $row['top'], $row['bottom']);
            array_push($arrNumber3, $lottoObj);
        }

        $data = array_merge($arrNumber2, $arrNumber3);

        $db->close();

        return $response->withJson($data);
    });
});

$app->get('/customer', function ($request, $response, $args) {
    return checkAuth($request, $response, function ($request, $response) {
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
    return checkAuth($request, $response, function ($request, $response) {
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
                WHERE t.number_id = '" . $numberID . "'
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
    return checkAuth($request, $response, function ($request, $response) {
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
                WHERE t.customer_id = '" . $customerID . "'
                GROUP BY t.id, n.id, n.number
                ORDER BY n.number ASC";

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
    return checkAuth($request, $response, function ($request, $response) {
        $body = $request->getParsedBody();

        $customerID = filter_var($body["customer_id"]);
        $lotteries = $body["lotteries"];

        $db = new DB();
        $db->connect();

        $data = array('status' => false);

        foreach ($lotteries as $lotto) {
            $sql = "SELECT *
                    FROM `number`
                    WHERE `number` = '" . trim($lotto["number"]) . "'";

            $result = $db->query($sql);
            $row = mysqli_fetch_assoc($result);

            $numberID = $row["id"];
            $top = trim($lotto["top"]);
            $bottom = trim($lotto["bottom"]);

            $insertSQL = "INSERT INTO `transaction` (`id`, `customer_id`, `number_id`, `top`, `bottom`)
                        VALUES ('" . sha1(getSecertKey() . date("Y-m-d H:i:s") . $customerID . $numberID) . "', '" . $customerID . "', '" . $numberID . "', '" . $top . "', '" . $bottom . "');";

            $result = $db->query($insertSQL);
            $data['status'] = true;
        }

        $db->close();

        return $response->withJson($data);
    });
});

$app->post('/editlotto', function ($request, $response, $args) {
    return checkAuth($request, $response, function ($request, $response) {
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
                    SET `top` = " . $top . ", `bottom` = " . $bottom . "
                    WHERE `id` = '" . $transactionID . "' ;";

            $result = $db->query($sql);
            $data['status'] = true;
        }

        $db->close();

        return $response->withJson($data);
    });
});

$app->post('/deletelotto', function ($request, $response) {
    return checkAuth($request, $response, function ($request, $response) {
        $body = $request->getParsedBody();

        $lotteries = $body["lotteries"];

        $db = new DB();
        $db->connect();

        $data = array('status' => false);

        foreach ($lotteries as $lotto) {
            $transactionID = trim($lotto["transactionID"]);

            $sql = "DELETE FROM `transaction`
                    WHERE `id` = '" . $transactionID . "'";

            $result = $db->query($sql);
            $data['status'] = true;
        }

        $db->close();

        return $response->withJson($data);
    });
});

$app->delete('/cleardata', function ($request, $response, $args) {
    return checkAuth($request, $response, function ($request, $response) {
        truncateTransaction();
        return $response->withJson(array('status' => true));
    });
});

$app->post('/{order}/{lock}', function ($request, $response, $args) {
    $route = $request->getAttribute('route');
    $order = trim($route->getArgument('order'));
    $lock = trim($route->getArgument('lock'));

    $body = $request->getParsedBody();

    $data = array();

    $allPostPutVars = $request->getParsedBody();
    foreach ($allPostPutVars as $key => $param) {
        $data[$key] = $param;
    }

    $res = array();
    $saved = array();
    $html = '<div style="border: 1px solid #ccc; height:400px;margin: 0 auto;overflow-y:scroll;"><table style="background-color: #ffffff; width:100%; ">';
    $result = $data["result"];
    $result = str_replace("[", "", $result);
    $result = str_replace("]", "", $result);

    $nums = explode(",", $result);
    $arraySize = sizeof($nums);

    if ($arraySize + 1 != $order) {
        if (isset($data['unique'])) {
            do {
                $no = rand($data["min"], $data["max"]);
            } while (in_array($no, $nums));
        } else {
            $no = rand($data["min"], $data["max"]);
        }
    } else {
        $no = intval($lock);
    }

    if (isset($data['unique'])) {
        if (strlen($result) > 0) {
            $i = 0;

            while ($i < $arraySize) {
                if ($i == 0 && $arraySize < $data["max"]) {
                    array_push($saved, $no);
                    $html = $html . '<tr style="background-color:#eee;"><td style="font-size:1.0em;width:100px;text-align:right;">' . ($arraySize + 1) . '.</td><td style="font-size:1.6em; color: #FF0000;"><strong>' . $no . '</strong></td></tr>';
                }

                array_push($saved, intval($nums[$i]));

                if ($i % 2 == 0) {
                    $html = $html . '<tr style="background-color:#fff;"><td style="width:100px;text-align:right;">' . ($arraySize - $i) . '.</td><td>' . $nums[$i] . '</td></tr>';
                } else {
                    $html = $html . '<tr style="background-color:#eee;"><td style="width:100px;text-align:right;">' . ($arraySize - $i) . '.</td><td>' . $nums[$i] . '</td></tr>';
                }
                $i++;
            }
        } else {
            array_push($saved, $no);
            $html = $html . '<tr style="background-color:#eee;"><td style="font-size:1.0em;width:100px;text-align:right;">1.</td><td style="font-size:1.6em; color: #FF0000;"><strong>' . $no . '</strong></td></tr>';
        }

        $res["html"] = $html . '</table></div>';

    } else {
        array_push($saved, $no);

        $i = 0;
        while ($i < $arraySize) {
            array_push($saved, intval($nums[$i]));
            $i++;
        }

        $res["html"] = '<div style="font-size:2em;padding:3px 15px;">' . $no . '</div>';

        if (in_array(0, $saved)) {
            array_pop($saved);
        }
    }

    $res["max"] = intval($data['max']);
    $res["min"] = intval($data['min']);
    $res["result"] = $saved;
    $res["status"] = "success";

    return $response->write(json_encode($res));
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
        $sql = "INSERT INTO `customer` (`id`, `name`) VALUES ('" . sha1(getSecertKey() . sprintf('%s', $char)) . "', '" . sprintf('%s', $char) . "');";
        $result = $db->query($sql);
    }

    $db->close();
}

function generateNumbers()
{
    $db = new DB();
    $db->connect();

    foreach (range(0, 99) as $i) {
        $sql = "INSERT INTO `number` (`id`, `number`) VALUES ('" . sha1(getSecertKey() . sprintf('%02d', $i)) . "', '" . sprintf('%02d', $i) . "');";
        $result = $db->query($sql);
    }

    foreach (range(0, 999) as $i) {
        $sql = "INSERT INTO `number` (`id`, `number`) VALUES ('" . sha1(getSecertKey() . sprintf('%03d', $i)) . "', '" . sprintf('%03d', $i) . "');";
        $result = $db->query($sql);
    }

    $db->close();
}

function checkAuth($request, $response, $call)
{
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
