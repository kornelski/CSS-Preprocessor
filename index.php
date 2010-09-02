<?php
function d(){}
function warn(){}
require_once "parsecss.php";
require_once "cssoutput.php";

$c = new ParseCSS();

$file = file_get_contents('test.css');

$s = $c->parse($file);
$o = new OutputCSS();
echo $o->outputStylesheet($s);
