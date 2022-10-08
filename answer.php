<?php

if (count($argv) === 1 || !filter_var($argv[1], FILTER_VALIDATE_URL))
    die('No URL provided'  . PHP_EOL);

$url = parse_url($argv[1]);

if ($url['host'] !== 'stackoverflow.com'|| count(explode('/', $url['path'])) !== 4)
    die('Not a Stack Overflow question URL' . PHP_EOL);

include 'functions.php';

$id = explode('/', $url['path'])[2];
$dir = __DIR__ . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;

$template_file = __DIR__ . DIRECTORY_SEPARATOR . 'template.php';
$question_file = $dir . '_question.html';
$index_file = $dir . 'index.php';

mkdir($dir);
echo 'get file...';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $argv[1]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:102.0) Gecko/20100101 Firefox/102.0');
$question_page = curl_exec($ch);
curl_close($ch);
file_put_contents($question_file, $question_page);
echo 'done' . PHP_EOL;

$template_data = file_get_contents($template_file);

$doc = new DOMDocument;
$doc->loadHTML($question_page, LIBXML_NOERROR);
$title = $doc->getElementsByTagName('h1')[0]->nodeValue;

$xpath = new DomXPath($doc);
$question = $xpath->query("//*[contains(@class, 'js-post-body')]")[0];
$question_text = preg_replace('/(\s+\n){2,}/', '', trim($question->nodeValue));

$template_data = str_replace(['${TITLE}', '${URL}', '${QUESTION}'], [$title, $argv[1], $question_text], $template_data);
file_put_contents($index_file, $template_data);
echo 'create index.php' . PHP_EOL;

$code_blocks = $xpath->query("//*[contains(@class, 'js-post-body')]//pre/code");

$i = 1;
foreach ($code_blocks as $code_block) {
    $code = trim($code_block->nodeValue);
    file_put_contents($dir . 'code' . $i . '.txt', $code);
    echo 'extracting code ' . $i . PHP_EOL;
    ++$i;
}

//var_dump($question_page);