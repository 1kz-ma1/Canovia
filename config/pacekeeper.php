<?php

// Legacy config alias kept temporarily so an older cached deployment or private
// extension that still reads config('pacekeeper.*') does not break during the
// Canovia domain/brand migration. New application code uses config('canovia.*').
return require __DIR__.'/canovia.php';
