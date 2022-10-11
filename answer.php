<?php

const ROOT_DIR = __DIR__ . DIRECTORY_SEPARATOR;
const INC_DIR = ROOT_DIR . 'inc' . DIRECTORY_SEPARATOR;
const ANSWERS_DIR = ROOT_DIR . 'answers' . DIRECTORY_SEPARATOR;

include INC_DIR . 'functions.php';

if (count($argv) === 1 || !filter_var($argv[1], FILTER_VALIDATE_URL))
    stop('Fatal: No URL provided');

$url = parse_url($argv[1]);

if ($url['host'] !== 'stackoverflow.com'|| count(explode('/', $url['path'])) !== 4)
    stop('Fatal: Not a Stack Overflow question URL');

$id = explode('/', $url['path'])[2];

echo 'create answer dir' . PHP_EOL;
$answer_dir = create_answer_dir($id);
if ($answer_dir === false) {
    stop('Fatal: Could not create answer directory');
}

echo 'get stackoverflow page' . PHP_EOL;
$page = get_stackoverflow_page($argv[1], $answer_dir);
if (empty($page)) {
    remove_answer_dir($id);
    stop('Fatal: Could not fetch page.. May be duplicate?');
}

echo 'extract question' . PHP_EOL;
$question = extract_question($page);
if (empty($question)) {
    remove_answer_dir($id);
    stop('Fatal: Could not extract question... Design change?');  
}

echo 'create answer files' . PHP_EOL;
$create_files = create_answer_files($answer_dir, $page, $question);
if ($create_files === false) {
    remove_answer_dir($id);
    stop('Fatal: Could not create answer files');
}

echo 'create answering.txt' . PHP_EOL;
answering_dot_txt($id);