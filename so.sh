#!/bin/bash

php answer.php $1

ID=$(cat answering.txt)
rm answering.txt

cd answers/$ID

codium index.php

exec bash
