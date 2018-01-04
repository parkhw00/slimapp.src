<?php

use Slim\Http\Request;
use Slim\Http\Response;

// config will provide $myid, $mypass
include 'config.php';

$repo_list = array(
  'scripts'=>"http://$myid:$mypass@mod.lge.com/hub/hyonwoo.park/plantuml.git",
  'docs'=>"http://$myid:$mypass@mod.lge.com/hub/hyonwoo.park/docs.git",
);

$plantuml_tail  = "see <a href=\"http://plantuml.com/\">plantuml</a> for examples.<br />";
$plantuml_tail .= "histories will be recorded on <a href=\"http://mod.lge.com/hub/hyonwoo.park/plantuml/tree/master\">git</a>.<br />";
$plantuml_tail .= "get <a href=\"?do=get_source\">source code</a>.";

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

$app->get('/plantuml', function (Request $request, Response $response, array $args) {
    global $logger;
    global $plantuml_tail;

    $script_names = array ();
    foreach (array(
      'scripts',
      'docs/audio_module/plantuml',
    ) as $prefix)
    {
      $search_dir = "/plantuml/work/$prefix";
      $logger->debug ("search dir : $search_dir");
      $files = scandir (__DIR__.$search_dir);
      foreach ($files as $file)
      {
        //$logger->debug ("file : $file");
        $ext = strrchr ($file, ".");
        if ($ext == ".txt")
          array_push ($script_names, substr ("$prefix/$file", 0, -4));
      }
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

  $filename = "/plantuml/work/$name.txt";
  $logger->debug ("filename : $filename");
  $script_text = file_get_contents (__DIR__ . $filename);
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
    $script = $request->getParam("script", "unknown");
    $message = $request->getParam("message", "unknown");

    $ret = file_put_contents (__DIR__ ."/plantuml/work/$name.txt", $script);
    if ($ret)
    {
      $src = __DIR__."/plantuml/work/$name.txt";
      $outdir = __DIR__.dirname("/../public/plantuml_out/work/$name.txt");
      $logger->debug ("outdir : $outdir");
      mkdir($outdir, 0777, true);
      exec ("java -jar ".__DIR__."/plantuml/plantuml.jar \"$src\" -o \"$outdir\"");
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

/* vim:set sw=2 et: */
