<?php

function stop($msg)
{
    answering_dot_txt();
    die($msg . PHP_EOL);
}

function answering_dot_txt($id=-1)
{
    file_put_contents('answering.txt', $id);
}

function create_answer_dir($id)
{
    $dir = ANSWERS_DIR . $id . DIRECTORY_SEPARATOR;
    if (mkdir($dir, 0777, true))
        return $dir;

    return false;
}

function remove_answer_dir($id)
{
    $dir = ANSWERS_DIR . $id . DIRECTORY_SEPARATOR;

    $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
    $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($ri as $file) {
        if ($file->isDir())
            rmdir($file);
        else
            unlink($file);
    }
    rmdir($dir);
}

function get_stackoverflow_page($url, $answer_dir)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:102.0) Gecko/20100101 Firefox/102.0');
    $page = curl_exec($ch);
    curl_close($ch);
    return $page;
}

function extract_question($page)
{
    $question = [
        'url' => '',
        'title' => '',
        'author' => '',
        'author_url' => '',
        'post_raw' => '',
        'paragraphs' => [],
        'code' => [],
        'tags' => []
    ];

    $doc = new DOMDocument;
    $doc->loadHTML($page, LIBXML_NOERROR);
    $xpath = new DomXPath($doc);

    $question['title'] = $doc->getElementsByTagName('h1')[0]->nodeValue;

    $question['url'] = $xpath->query("//meta[@property='og:url']")[0]->getAttribute('content');
    
    $author = $xpath->query("//div[@id='question']//div[contains(@class, 'user-details')]/a")[0];
    $question['author'] = $author->nodeValue;
    $question['author_url'] = 'https://stackoverflow.com' . $author->getAttribute('href');

    $post = $xpath->query("//div[@id='question']//div[contains(@class, 'js-post-body')]")[0];
    $question['post_raw'] = preg_replace('/(\s+\n){2,}/', '', trim($post->nodeValue));

    $paragraphs = $xpath->query("//div[@id='question']//div[contains(@class, 'js-post-body')]/p");
    foreach ($paragraphs as $paragraph) {
        $question['paragraphs'][] = trim($paragraph->nodeValue);
    }

    $code_blocks = $xpath->query("//div[@id='question']//pre/code");
    foreach ($code_blocks as $code_block) {
        $question['code'][] = trim($code_block->nodeValue);
    }

    $tags = $xpath->query("//div[@id='question']//div[contains(@class, 'post-taglist')]//a");
    foreach ($tags as $tag) {
        $question['tags'][] = trim($tag->nodeValue);
    }

    return $question;
}

function create_answer_files($answer_dir, $page, $question)
{
    $question_html = $answer_dir . '_question.html';
    file_put_contents($question_html, $page);

    $question_json = $answer_dir . '_question.json';
    file_put_contents($question_json, json_encode($question, JSON_PRETTY_PRINT));

    if (!empty($question['code'])) {
        foreach ($question['code'] as $i => $code) {
            $code_file = $answer_dir . 'code' . ($i+1) . '.txt';
            file_put_contents($code_file, $code);
        }
    }

    create_answer_index($answer_dir, $question);
}

function create_answer_index($answer_dir, $question)
{
    $index_file = $answer_dir . 'index.php';
    $template = file_get_contents(INC_DIR . 'template.php');

    $replace = [
        '${AUTHOR}' => $question['author'],
        '${TITLE}' => $question['title'],
        '${URL}' => $question['url'],
        '${TAGS}' => implode(', ', $question['tags']),
        '${SQL}' => create_sql_template($question)
    ];

    $data = str_replace(array_keys($replace), array_values($replace), $template);
    file_put_contents($index_file, $data);
}

function needs_sql_template($tags) {
    foreach ($tags as $tag) {
        if (str_contains(strtolower($tag), 'sql'))
            return true;
    }
    return false;
}

function create_sql_template($question) {
    if (!needs_sql_template($question['tags']))
        return '';

    $id = explode('/', parse_url($question['url'], PHP_URL_PATH))[2];

    $template = <<<SQL
\$mysql = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
\$mysql->query('CREATE TABLE IF NOT EXISTS ${id} (id TEXT, name TEXT);');
\$mysql->query('SELECT * FROM ${id};');
SQL;

    return $template;
}