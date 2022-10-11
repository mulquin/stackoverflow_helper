<?php

const DB_HOST = 'webdev.mulquin.net';
const DB_USER = 'root';
const DB_PASS = 'root';
const DB_NAME = 'so';

// https://gist.github.com/simivar/037b13a9bbd53ae5a092d8f6d9828bc3
function unprint_r($input) {
    $lines = preg_split('#\r?\n#', trim($input));
    if (trim($lines[ 0 ]) != 'Array' && trim($lines[ 0 ]) != 'stdClass Object') {
        // bottomed out to something that isn't an array or object
        if ($input === '') {
            return null;
        }
        
        return $input;
    } else {
        // this is an array or object, lets parse it
        $match = array();
        if (preg_match("/(\s{5,})\(/", $lines[ 1 ], $match)) {
            // this is a tested array/recursive call to this function
            // take a set of spaces off the beginning
            $spaces = $match[ 1 ];
            $spaces_length = strlen($spaces);
            $lines_total = count($lines);
            for ($i = 0; $i < $lines_total; $i++) {
                if (substr($lines[ $i ], 0, $spaces_length) == $spaces) {
                    $lines[ $i ] = substr($lines[ $i ], $spaces_length);
                }
            }
        }
        $is_object = trim($lines[ 0 ]) == 'stdClass Object';
        array_shift($lines); // Array
        array_shift($lines); // (
        array_pop($lines); // )
        $input = implode("\n", $lines);
        $matches = array();
        // make sure we only match stuff with 4 preceding spaces (stuff for this array and not a nested one)
        preg_match_all("/^\s{4}\[(.+?)\] \=\> /m", $input, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $pos = array();
        $previous_key = '';
        $in_length = strlen($input);
        // store the following in $pos:
        // array with key = key of the parsed array's item
        // value = array(start position in $in, $end position in $in)
        foreach ($matches as $match) {
            $key = $match[ 1 ][ 0 ];
            $start = $match[ 0 ][ 1 ] + strlen($match[ 0 ][ 0 ]);
            $pos[ $key ] = array($start, $in_length);
            if ($previous_key != '') {
                $pos[ $previous_key ][ 1 ] = $match[ 0 ][ 1 ] - 1;
            }
            $previous_key = $key;
        }
        $ret = array();
        foreach ($pos as $key => $where) {
            // recursively see if the parsed out value is an array too
            $ret[ $key ] = print_r_reverse(substr($input, $where[ 0 ], $where[ 1 ] - $where[ 0 ]));
        }
        
        return $is_object ? (object)$ret : $ret;
    }
}

// https://gist.github.com/rogersguedes/2795072232164d0fe7b994086c0824f6
function unvar_dump($str) {
    if (strpos($str, "\n") === false) {
        //Add new lines:
        $regex = array(
            '#(\\[.*?\\]=>)#',
            '#(string\\(|int\\(|float\\(|array\\(|NULL|object\\(|})#',
        );
        $str = preg_replace($regex, "\n\\1", $str);
        $str = trim($str);
    }
    $regex = array(
        '#^\\040*NULL\\040*$#m',
        '#^\\s*array\\((.*?)\\)\\s*{\\s*$#m',
        '#^\\s*string\\((.*?)\\)\\s*(.*?)$#m',
        '#^\\s*int\\((.*?)\\)\\s*$#m',
        '#^\\s*bool\\(true\\)\\s*$#m',
        '#^\\s*bool\\(false\\)\\s*$#m',
        '#^\\s*float\\((.*?)\\)\\s*$#m',
        '#^\\s*\[(\\d+)\\]\\s*=>\\s*$#m',
        '#\\s*?\\r?\\n\\s*#m',
    );
    $replace = array(
        'N',
        'a:\\1:{',
        's:\\1:\\2',
        'i:\\1',
        'b:1',
        'b:0',
        'd:\\1',
        'i:\\1',
        ';'
    );
    $serialized = preg_replace($regex, $replace, $str);
    $func = create_function(
        '$match',
        'return "s:".strlen($match[1]).":\\"".$match[1]."\\"";'
        );
    $serialized = preg_replace_callback(
        '#\\s*\\["(.*?)"\\]\\s*=>#',
        $func,
        $serialized
        );
    $func = create_function(
        '$match',
        'return "O:".strlen($match[1]).":\\"".$match[1]."\\":".$match[2].":{";'
        );
    $serialized = preg_replace_callback(
        '#object\\((.*?)\\).*?\\((\\d+)\\)\\s*{\\s*;#',
        $func,
        $serialized
        );
    $serialized = preg_replace(
        array('#};#', '#{;#'),
        array('}', '{'),
        $serialized
    );
    $serialized = preg_replace_callback('!s:(\d+):"(.*?)";!s', function ($matches) {
        return 's:'.strlen($matches[2]).':"'.$matches[2].'";';
    }, $serialized);
    return unserialize($serialized);
}

function file_put_json($file, $data)
{
    $json = json_encode($data, JSON_PRETTY_PRINT);
    file_put_contents($file, $json);
}

function file_get_json($file, $as_array=false)
{
    $json = json_decode(file_get_contents($file), $as_array);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = json_last_error_msg();
        throw new \Exception('JSON Error: "' .$error . '"');
    }

    return $json;
}

function file_get_csv($file, $header_row=true)
{
    $handle = fopen($file, 'r');
    
    if ($header_row === true)
        $header = fgetcsv($handle);

    $array = [];
    while ($row = fgetcsv($handle)) {
        if ($header_row === true) {
            $array[] = array_combine($header, array_map('trim', $row));
        } else {
            $array[] = array_map('trim', $row);
        }
    }
    fclose($handle);
    return $array;
}

function file_get_cached($remote, $local=null, $ttl=8400)
{
    if ($local == null) {
        $parse = parse_url($remote);
        $full_remote = $parse['path'];
        if (isset($parse['query']))
            $full_remote .= $parse['query'];
        if (isset($parse['fragment']))
            $full_remote .= $parse['fragment'];

        $local = preg_replace("/\W+/", "", $full_remote);
    }
    
    if (file_exists($local)) {
        $file_age = time() - filemtime($local);
        if ($file_age < $ttl)
            return file_get_contents($local);
    }

    $contents = file_get_contents($remote);
    file_put_contents($local, $contents);

    return $contents;
}