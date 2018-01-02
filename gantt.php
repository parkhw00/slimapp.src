<?php

use Slim\Http\Request;
use Slim\Http\Response;

$logger = $app->getContainer()['logger'];
$timezone = "Asia/Seoul";

function setArgs ($args, $db = false)
{
	global $logger;

	if ($db === false)
		$db = getConnection($args['name']);

	$args['workday'] = get_object_vars ($db->query("SELECT day0, day1, day2, day3, day4, day5, day6 FROM workday WHERE user = 0")->fetchObject());
	$args['holidays'] = $db->query("SELECT date FROM holidays WHERE user = 0 ORDER BY date ASC")->fetchAll();
        $args['users'] = $db->query("SELECT * FROM user ORDER BY name ASC")->fetchAll();

	return $args;
}

$app->get('/gantt/{name}', function (Request $request, Response $response, array $args) {
    global $logger;

    $this->renderer->setTemplatePath(__DIR__.'/gantt');

    $dbname = __DIR__.'/gantt/db/'.$args['name'];
    if (is_file ($dbname))
    {
	    $template = "view.phtml";

	    $args = setArgs ($args);
    }
    else
    {
	    $logger->debug ("no database.");
	    $template = "view_nodb.phtml";
    }

    return $this->renderer->render($response, $template, $args);
});

$app->get('/gantt/{name}/init', function (Request $request, Response $response, array $args) {
    global $logger;

    $this->renderer->setTemplatePath(__DIR__.'/gantt');

    $dbname = __DIR__.'/gantt/db/'.$args['name'];
    $db = false;
    if (!is_file ($dbname))
    {
	    $logger->info ("initialize database..");
	    unlink ($dbname);
	    mkdir (__DIR__.'/gantt/db');
	    $db = getConnection ($args['name']);
	    $sqls = array(
"CREATE TABLE `gantt_links` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `source` int(11) NOT NULL,
    `target` int(11) NOT NULL,
    `type` varchar(1) NOT NULL)",
"CREATE TABLE `gantt_tasks` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `user` int(11) NOT NULL,
    `text` varchar(255) NOT NULL,
    `start_date` datetime NOT NULL,
    `duration` int(11) NOT NULL,
    `progress` float NOT NULL,
    `parent` int(11) NOT NULL,
    `sortorder` int(11) NOT NULL
)",
"CREATE TABLE `holidays` (
    `user` int(11) NOT NULL,
    `date` datetime NOT NULL
)",
"CREATE TABLE `workday` (
    `user` int(11) NOT NULL,
    `day0` BIT,
    `day1` BIT,
    `day2` BIT,
    `day3` BIT,
    `day4` BIT,
    `day5` BIT,
    `day6` BIT
)",
"CREATE TABLE `user` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` varchar(255) NOT NULL
)",
"INSERT INTO `workday` VALUES (0,0,1,1,1,1,1,0)",
"INSERT INTO `user` VALUES (0,'N/A')",
"INSERT INTO `user`(name) VALUES ('Jessica')",
"INSERT INTO `user`(name) VALUES ('박현우')",
"INSERT INTO `user`(name) VALUES ('이순신')",
"INSERT INTO `user`(name) VALUES ('김유신')",
	    );
	    foreach ($sqls as $sql)
	    {
		    $logger->info ("sql : $sql");
		    $db->exec ($sql);
	    }
	    $logger->info ("table created");
    }

    $args = setArgs ($args, $db);

    return $this->renderer->render($response, 'view.phtml', $args);
});

$app->get('/gantt/{name}/calendar', function (Request $request, Response $response, array $args) {
    global $logger;

    $args = setArgs ($args);
    $logger->debug ("get  args : ".var_export ($args, true));

    $this->renderer->setTemplatePath(__DIR__.'/gantt');
    return $this->renderer->render($response, 'calendar.phtml', $args);
});

$app->post('/gantt/{name}/calendar', function (Request $request, Response $response, array $args) {
    global $logger;
    global $timezone;

    $db = getConnection ($args['name']);

    $logger->debug ("workday : ".$request->getParam("workday"));
    $logger->debug ("holidays : ".$request->getParam("holidays"));

    // workday
    $workday_key = array();
    for ($i=0; $i<7; $i++)
      $workday_key[$i] = ":day$i";
    $workday = array_combine ($workday_key, explode (",", $request->getParam ("workday")));

    $logger->debug ("workday : ".var_export ($workday, true));

    $query="UPDATE workday SET user = 0, ".
      "day0 = :day0, ".
      "day1 = :day1, ".
      "day2 = :day2, ".
      "day3 = :day3, ".
      "day4 = :day4, ".
      "day5 = :day5, ".
      "day6 = :day6 ".
      "WHERE user = 0";
    $logger->debug (var_export (array($query, $task), true));
    $db->prepare($query)->execute($workday);

    // holidays
    $query = "DELETE FROM holidays WHERE user = :user";
    $db->prepare($query)->execute([":user"=>0]);

    $holidays = explode (",", $request->getParam ("holidays"));
    foreach ($holidays as $holiday)
    {
      if ($holiday == '')
        continue;

      $h = new DateTime($holiday);
      $h->setTimezone(new DateTimeZone($timezone));
      $hStr = $h->format("Y-m-d H:i:s");
      $logger->debug ("holiday : ".$hStr);

      $query = "INSERT INTO holidays(user, date) VALUES (:user, :date)";
      $db->prepare($query)->execute([":user"=>0, ":date"=>$hStr]);
    }

    $args = setArgs ($args);

    $this->renderer->setTemplatePath(__DIR__.'/gantt');
    return $this->renderer->render($response, 'calendar.phtml', $args);
});

$app->get('/gantt/{name}/user', function (Request $request, Response $response, array $args) {
    global $logger;

    $args = setArgs ($args);
    $logger->debug ("get  args : ".var_export ($args, true));

    $this->renderer->setTemplatePath(__DIR__.'/gantt');
    return $this->renderer->render($response, 'user.phtml', $args);
});

$app->post('/gantt/{name}/user', function (Request $request, Response $response, array $args) {
    global $logger;

    $logger->debug ("users_add : ".$request->getParam("users_add"));
    $logger->debug ("users_del : ".$request->getParam("users_del"));

    $db = getConnection ($args['name']);

    $add = explode(",", $request->getParam("users_add"));
    foreach($add as $name)
    {
      if ($name == "")
        continue;
      $logger->debug ("add : $name");

      $query="INSERT INTO user(name) VALUES (:name)";
      $logger->debug ($query);
      $db->prepare($query)->execute([':name' => $name]);
    }

    if (0)
    {
      $del = explode(",", $request->getParam("users_del"));
      foreach($del as $name)
      {
        if ($name == "")
          continue;
        $logger->debug ("del : $name");

        $query="INSERT INTO user(name) VALUES (:name)";
        $logger->debug ($query, true);
        $db->prepare($query)->execute([':name' => $name]);
      }
    }

    $args = setArgs ($args);

    $this->renderer->setTemplatePath(__DIR__.'/gantt');
    return $this->renderer->render($response, 'user.phtml', $args);
});

function getConnection($name)
{
	return new PDO("sqlite:".__DIR__."/gantt/db/$name");
}

$app->get('/gantt/{name}/data', 'getGanttData');
function getGanttData($request, $response, $args)
{
	global $logger;

	$db = getConnection($args['name']);
	$result = [
		"data"=> [],
		"links"=> []
	];
	$logger->debug ("get data");

	foreach($db->query("SELECT * FROM gantt_tasks ORDER BY sortorder ASC") as $row){
		$row["open"] = true;
		array_push($result["data"], $row);
	}

	foreach ($db->query("SELECT * FROM gantt_links") as $link){
		array_push($result["links"], $link);
	}

	return $response->withJson($result);
};

// getting a task from the request data
function getTask($data)
{
	return [
		':user' => $data["user"],
		':text' => $data["text"],
		':start_date' => $data["start_date"],
		':duration' => $data["duration"],
		':progress' => isset($data["progress"]) ? $data["progress"] : 0,
		':parent' => $data["parent"]
	];
}

// getting a link from the request data
function getLink($data){
	return [
		":source" => $data["source"],
		":target" => $data["target"],
		":type" => $data["type"]
	];
}

// create a new task
$app->post("/gantt/{name}/data/task", 'addTask');
function addTask($request, $response, $args)
{
	global $logger;

	$task = getTask($request->getParsedBody());
	$db = getConnection($args['name']);

	$maxOrderQuery = "SELECT MAX(sortorder) AS maxOrder FROM gantt_tasks";
	$statement = $db->prepare($maxOrderQuery);
	$statement->execute();

	$maxOrder = $statement->fetchColumn();
	if(!$maxOrder)
		$maxOrder = 0;

	$task[":sortorder"] = $maxOrder + 1;

	$query="INSERT INTO gantt_tasks(user,text,start_date,duration,progress,parent,sortorder) ".
		"VALUES (:user,:text,:start_date,:duration,:progress,:parent, :sortorder)";
	$logger->debug (var_export (array($query, $task), true));
	$db->prepare($query)->execute($task);

	return $response->withJson([
		"action"=>"inserted",
		"tid"=> $db->lastInsertId()
	]);
}

// update a task
$app->put("/gantt/{name}/data/task/{id}", 'updateTask');
function updateTask($request, $response, $args)
{
	global $logger;

	$sid = $request->getAttribute("id");
	$params = $request->getParsedbody();
	$task = getTask($params);
	$db = getConnection($args['name']);
	$query = "UPDATE gantt_tasks ".
		"SET user = :user, text = :text, start_date = :start_date, duration = :duration,". 
		"progress = :progress, parent = :parent ".
		"WHERE id = :sid";
	//$logger->debug (var_export ($request, true));
	$logger->debug (var_export (array($query, $task), true));

	$db->prepare($query)->execute(array_merge($task, [":sid"=>$sid]));

	if(isset($params["target"]) && $params["target"])
	{
		$target = $params["target"];
		$logger->debug ("update order. $sid, $target");
		updateOrder($sid, $target, $db);
	}

	return $response->withJson([
		"action"=>"updated"
	]);
}

function updateOrder($taskId, $target, $db)
{
	$nextTask = false;
	$targetId = $target;

	if(strpos($target, "next:") === 0){
		$targetId = substr($target, strlen("next:"));
		$nextTask = true;
	}

	if($targetId == "null")
		return;

	$sql = "SELECT sortorder FROM gantt_tasks WHERE id = :id";
	$statement = $db->prepare($sql);
	$statement->execute([":id"=>$targetId]);

	$targetOrder = $statement->fetchColumn();
	if($nextTask)
		$targetOrder++;

	$sql = "UPDATE gantt_tasks SET sortorder = sortorder + 1 ".
		"WHERE sortorder >= :targetOrder";
	$statement = $db->prepare($sql);
	$statement->execute([":targetOrder"=>$targetOrder]);

	$sql = "UPDATE gantt_tasks SET sortorder = :targetOrder WHERE id = :taskId";
	$statement = $db->prepare($sql);
	$statement->execute([
		":targetOrder"=>$targetOrder,
		":taskId"=>$taskId
	]);
}

// delete a task
$app->delete("/gantt/{name}/data/task/{id}", 'deleteTask');
function deleteTask($request, $response, $args)
{
	global $logger;

	$sid = $request->getAttribute("id");
	$db = getConnection($args['name']);
	$query = "DELETE FROM gantt_tasks WHERE id = :sid";
	$logger->debug (var_export (array($query, $sid), true));

	$db->prepare($query)->execute([":sid"=>$sid]);
	return $response->withJson([
		"action"=>"deleted"
	]);
}

// create a new link
$app->post("/gantt/{name}/data/link", 'addLink');
function addLink($request, $response, $args)
{
	global $logger;

	$link = getLink($request->getParsedBody());
	$db = getConnection($args['name']);
	$query = "INSERT INTO gantt_links(source, target, type) ".
		"VALUES (:source,:target,:type)";
	$logger->debug (var_export (array($query, $link), true));

	$db->prepare($query)->execute($link);

	return $response->withJson([
		"action"=>"inserted",
		"tid"=> $db->lastInsertId()
	]);
}

// update a link
$app->put("/gantt/{name}/data/link/{id}", 'updateLink');
function updateLink($request, $response, $args)
{
	global $logger;

	$sid = $request->getAttribute("id");
	$link = getLink($request->getParsedBody());
	$db = getConnection($args['name']);
	$query = "UPDATE gantt_links SET ".
		"source = :source, target = :target, type = :type ".
		"WHERE id = :sid";
	$logger->debug (var_export (array($query, $link), true));

	$db->prepare($query)->execute(array_merge($link, [":sid"=>$sid]));
	return $response->withJson([
		"action"=>"updated"
	]);
}

// delete a link
$app->delete("/gantt/{name}/data/link/{id}", 'deleteLink');
function deleteLink($request, $response, $args)
{
	global $logger;

	$sid = $request->getAttribute("id");
	$db = getConnection($args['name']);
	$query = "DELETE FROM gantt_links WHERE id = :sid";
	$logger->debug (var_export (array($query, $sid), true));

	$db->prepare($query)->execute([":sid"=>$sid]);
	return $response->withJson([
		"action"=>"deleted"
	]);
}
// vim:set sw=2 et:
