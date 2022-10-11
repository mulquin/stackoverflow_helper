#!/bin/bash

php answer.php $1

ID=$(cat answering.txt)
rm answering.txt

if [ ! $ID -eq -1 ]
then
    cd answers/$ID
    codium index.php
    exec bash
fi
