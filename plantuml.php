<?php

use Slim\Http\Request;
use Slim\Http\Response;

// config will provide $myid, $mypass
if(is_file(__DIR__.'config.php'))
  include 'config.php';
else
{
  $myid="";
  $mypass="";
}

$repo_list = array(
  'scripts'=>"http://$myid:$mypass@mod.lge.com/hub/hyonwoo.park/plantuml.git",
  'docs'=>"http://$myid:$mypass@mod.lge.com/hub/hyonwoo.park/docs.git",
);

$plantuml_tail  = "see <a href=\"http://plantuml.com/\">plantuml</a> for examples.<br />";
$plantuml_tail .= "histories will be recorded on <a href=\"http://mod.lge.com/hub/hyonwoo.park/plantuml/tree/master\">git</a>.<br />";
$plantuml_tail .= "get <a href=\"https://github.com/parkhw00/slimapp.src\">source code</a>.";

function get_repo_name($name)
{
  global $myid, $mypass;
  global $repo_list;

  $pos = strpos($name, "/");
  if ($pos !== FALSE)
  {
    $org = $name;
    $name = substr($org, $pos+1);
    $repo = substr($org, 0, $pos);

    if (!array_key_exists($repo, $repo_list))
    {
      $repo = 'scripts';
      $name = $org;
    }
  }
  else
    $repo = 'scripts';
  $pushurl = $repo_list[$repo];

  return (object)[
    'pushurl'=>$pushurl,
    'repo'=>$repo,
    'name'=>$name,
  ];
}

function plantuml_link($do, $name, $args = array())
{
  $r = "/plantuml/$do/$name?";
  foreach ($args as $n => $v)
    $r .= "&$n=$v";

  return $r;
}

function plantuml_img_link($name)
{
  return "/plantuml_out/work/$name.png";
}

function plantuml_img($name)
{
  return __DIR__."/../public/plantuml_out/work/$name.png";
}

function plantuml_txt($name)
{
  return __DIR__."/plantuml/work/$name.txt";
}

function scan_dir($dir, &$names)
{
  global $logger;

  $logger->debug ("search dir : $dir");
  $files = scandir (__DIR__."/plantuml/work/$dir");
  foreach ($files as $file)
  {
    //$logger->debug ("file : $file");
    if ($file[0] == ".")
      continue;

    $fullpath = __DIR__."/plantuml/work/$dir/$file";
    if (is_dir($fullpath))
    {
      scan_dir("$dir/$file", $names);
      continue;
    }

    if (is_file($fullpath))
    {
      $ext = strrchr ($file, ".");
      if ($ext == ".txt")
      {
        //$logger->debug ("push : $dir/$file");
        array_push ($names, substr ("$dir/$file", 0, -4));
      }
    }
  }
}

$app->get('/plantuml', function (Request $request, Response $response, array $args) {
    global $logger;
    global $plantuml_tail;

    $script_names = array ();
    foreach (array(
      'scripts',
      'docs/audio_module/plantuml',
    ) as $prefix)
    {
      scan_dir ($prefix, $script_names);
    }

    $args['tail_message'] = $plantuml_tail;
    $args['script_names'] = $script_names;
    $args['noimg'] = $request->getParam("noimg", false);

    $this->renderer->setTemplatePath(__DIR__.'/plantuml');
    return $this->renderer->render($response, "list.phtml", $args);
});

function get_edit_args($name, $request, $args)
{
  global $logger;
  global $plantuml_tail;

  $filename = plantuml_txt($name);
  $logger->debug ("filename : $filename");
  $script_text = file_get_contents ($filename);
  //$script_text = "aa";
  if ($script_text != "")
  {
    $rows = substr_count ($script_text, "\n");
    $rows += 3;
  }
  else
  {
    $script_text = "
@startuml
Alice -> Baaob: \"empty script..\"
@enduml";
    $rows = 40;
  }

  {
    $repo_name = get_repo_name($name);

    $logger->debug ("org name $name");
    $logger->debug ("name $repo_name->name");
    $logger->debug (
      "sh -c 'cd ".__DIR__."/plantuml/work/$repo_name->repo/ "
      ."&& git status --porcelain \"$repo_name->name.txt\" "
      ."' 2>&1");
    exec (
      "sh -c 'cd ".__DIR__."/plantuml/work/$repo_name->repo/ "
      ."&& git status --porcelain \"$repo_name->name.txt\" "
      ."' 2>&1", $out, $ret);
    $file_status = "";
    foreach ($out as $line)
      $file_status .= "$line<br />";
  }

  $args['name'] = $name;
  $args['script_text'] = $script_text;
  $args['rows'] = $rows;
  $args['noimg'] = $request->getParam("noimg", false);
  $args['file_status'] = $file_status;
  $args['tail_message'] = $plantuml_tail;

  return $args;
}

$app->get('/plantuml/edit/{name:.*}', function (Request $request, Response $response, array $args) {
    global $logger;
    global $plantuml_tail;

    $name = $args['name'];
    $args = get_edit_args ($name, $request, $args);

    $this->renderer->setTemplatePath(__DIR__.'/plantuml');
    return $this->renderer->render($response, "edit.phtml", $args);
});

$app->post('/plantuml/update/{name:.*}', function (Request $request, Response $response, array $args) {
    global $logger;
    global $plantuml_tail;

    $name = $args['name'];
    $r = get_repo_name($name);
    $name = "$r->repo/$r->name";
    $script = $request->getParam("script", "unknown");
    $message = $request->getParam("message", "");

    $srcdir = dirname(plantuml_txt($name));
    $logger->debug ("srcdir : $srcdir");
    if (!is_dir($srcdir))
      mkdir($srcdir, 0777, true);

    $ret = file_put_contents (plantuml_txt($name), $script);
    if ($ret)
    {
      $src = plantuml_txt($name);
      $outfile = plantuml_img($name);
      $outdir = dirname($outfile);
      $outfile_temp = tempnam($outdir, "__temp.");
      $logger->debug ("outdir : $outdir");
      if (!is_dir($outdir))
        mkdir($outdir, 0777, true);
      $exec_cmd = "java -jar ".__DIR__."/plantuml/plantuml.jar -p < \"$src\" > \"$outfile_temp\"";
      $logger->debug ("exec_cmd : $exec_cmd");
      exec ($exec_cmd);
      rename($outfile_temp, $outfile);

      if ($message != "")
      {
        $logger->debug ("committing...");

        $tempname = tempnam ("/tmp", "plantuml_commit_message");
        $logger->debug ("commit message file: $tempname");
        $handle = fopen ($tempname, "w");
        fwrite ($handle, $message);
        fclose ($handle);

        $repo_name = get_repo_name($name);

        $exec_script = 
          "sh -c 'cd ".__DIR__."/plantuml/work/$repo_name->repo/ "
          ."&& git add \"$repo_name->name.txt\" "
          ."&& git commit -F $tempname "
          ."&& git push $repo_name->pushurl HEAD:master"
          ."' 2>&1";
        $logger->debug ("exec_script : $exec_script");
        exec ($exec_script , $out, $ret);
        unlink ($tempname);

        if ($ret != 0)
        {
          $logger->error ("ret : $ret");
          foreach ($out as $o)
          {
            $o = str_replace ("$myid:$mypass@", "$myid@", $o);
            $logger->error ("out : $o");
          }
        }
      }
      else
        $logger->debug ("skip commit");
    }
    else
      $logger->error ("cannot save $name.txt");

    $noimg = $request->getParam("noimg", false);
    return $response->withRedirect(plantuml_link("edit", $name, ['noimg'=>$noimg]));
});

$app->get('/plantuml/fetch_repo/{name:.*}', function (Request $request, Response $response, array $args) {
    global $logger;
    global $plantuml_tail;

    $name = $args['name'];
    $repo_name = get_repo_name($name);

    $exec_script = "sh -c 'cd ".__DIR__."/plantuml/work/$repo_name->repo/ "
      ."&& git fetch origin "
      ."&& git checkout origin/master "
      ."' 2>&1";
    $logger->debug ("exec_script : $exec_script");
    exec ($exec_script , $out, $ret);
    foreach ($out as $line)
      $logger->debug ($line);

    $noimg = $request->getParam("noimg", false);
    return $response->withRedirect(plantuml_link("edit", $name, ['noimg'=>$noimg]));
});

$app->get('/plantuml/watch/{name:.*}', function (Request $request, Response $response, array $args) {
    global $logger;

    $name = $args['name'];
    $args['mtime'] = filemtime(plantuml_img($name));

    $this->renderer->setTemplatePath(__DIR__.'/plantuml');
    return $this->renderer->render($response, "watch.phtml", $args);
});

$app->get('/plantuml/wait_changes/{name:.*}', function (Request $request, Response $response, array $args) {
    global $logger;

    $result = [
      'return' => true,
      'mtime' => 0,
      'message' => 'none',
      ];

    $name = $args['name'];
    $current_mtime = $request->getParam('mtime', 0);

    $fd = inotify_init();
    if ($fd !== FALSE)
    {
      $file = plantuml_img($name);

      $in_events =
        IN_MODIFY |
        IN_MOVE |
        IN_MOVE_SELF |
        IN_DELETE |
        IN_DELETE_SELF;
      $watch_descriptors = inotify_add_watch($fd, $file, $in_events);

      $mtime = filemtime($file);
      if ($mtime == $current_mtime)
      {
        $read = array($fd);
        $write = array();
        $except = array();
        $logger->debug("wait.. $name current $mtime");
        $num = stream_select($read, $write, $except, 60);
        $logger->debug("wait.. $name done.");
        if($num === false)
        {
          $result['message'] = 'error';
        }
        else if($num === 0)
        {
          $result['message'] = 'timeout';
        }
        else
        {
          $events = inotify_read($fd);

          $message = [];
          foreach($events as $event => $evdetails)
          {
            $logger->debug("event ".var_export($evdetails,true));
            switch (true)
            {
            case ($evdetails['mask'] & IN_MODIFY):
              array_push($message, "modify");
              break;
            case ($evdetails['mask'] & IN_MOVE):
              array_push($message, "move");
              break;
            case ($evdetails['mask'] & IN_MOVE_SELF):
              array_push($message, "move_self");
              break;
            case ($evdetails['mask'] & IN_DELETE):
              array_push($message, "delete");
              break;
            case ($evdetails['mask'] & IN_DELETE_SELF):
              array_push($message, "delete_self");
              break;
            }

            break;
          }
          $result['message'] = join(",", $message);
        }

        $result['mtime'] = filemtime($file);
      }

      inotify_rm_watch ($fd, $watch_descriptors);
      fclose ($fd);
    }
    else
    {
      $logger->error ("inotify_init failed");
      $result['return'] = false;
    }
    $logger->debug ("result : " . var_export($result, true));

    return $response->withJson($result);
});

/* vim:set sw=2 et: */
