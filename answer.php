<?php

const ROOT_DIR = __DIR__ . DIRECTORY_SEPARATOR;
const INC_DIR = ROOT_DIR . 'inc' . DIRECTORY_SEPARATOR;
const ANSWERS_DIR = ROOT_DIR . 'answers' . DIRECTORY_SEPARATOR;

include INC_DIR . 'functions.php';

if (count($argv) === 1 || !filter_var($argv[1], FILTER_VALIDATE_URL))
    die('Fatal: No URL provided'  . PHP_EOL);

$url = parse_url($argv[1]);

if ($url['host'] !== 'stackoverflow.com'|| count(explode('/', $url['path'])) !== 4)
    die('Fatal: Not a Stack Overflow question URL' . PHP_EOL);

$id = explode('/', $url['path'])[2];

echo 'create answer dir' . PHP_EOL;
$answer_dir = create_answer_dir($id);
if ($answer_dir === false)
    die('Fatal: Could not create answer directory' . PHP_EOL);

echo 'get stackoverflow page' . PHP_EOL;
$page = get_stackoverflow_page($argv[1], $answer_dir);

echo 'extract question' . PHP_EOL;
$question = extract_question($page);

echo 'create answer files' . PHP_EOL;
$create_files = create_answer_files($answer_dir, $page, $question);
if ($create_files === false)
    die('Fatal: Could not create answer files' . PHP_EOL);

echo 'create answering.txt' . PHP_EOL;
file_put_contents('answering.txt', $id);