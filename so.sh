#!/bin/bash

php answer.php $1

IFS="/"
read -a fragments <<< "$1"
ID=${fragments[4]}
IFS=""

cd $ID

codium index.php

exec bash
