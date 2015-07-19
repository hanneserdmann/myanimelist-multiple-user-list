<?php

error_reporting(E_ALL);

# include class
require_once 'lib/UserListGenerator.php';

# directory paths
$currentDirectory   = '/home/thextor/html/rmanimelist/';
$tempDirectory      = $currentDirectory . 'temp/';
$outputDirectory    = $currentDirectory . 'public/';
$templateFile       = $currentDirectory . 'template.html';

# included users
$userList = [   'Areko',        'belarion',     'Chiar',        'chup1n',           'CwybuG',
                'hanny',        'DorTeX',       'rmDuha',       'fizik',            'GiZmO0',
                'gr1zzlY',      'heidih',       'IllDepence',   'jaNSENN',          'jaysemilia',
                'Jun',          'lawlai',       'LUNTE',        'luq89',            'M159852',
                'MeisterEDE',   'nerinerineri', 'myNipah',      'noxxx',            'Prktschz',
                'quiKKK',       'cekjaja',      'rm_Stallion',  'slixxer',          'thextor',
                'zephsorizor',  'xcllnt',       'Fabbo-',       'colorandi_causa',  'Nippy',
                '_BP123'
            ];

# generate list
try{
    $listGenerator = new MALMultipleUserListGenerator\UserListGenerator($userList, $tempDirectory, $outputDirectory, $templateFile);
    $listGenerator->generateAnimeList();
    $listGenerator->generateMangaList();
}
catch(Exception $e){
    print   "\nERROR"   .
            "\n====="   .
            "\n\n"      .
            $e->getMessage() . "\n\n";
}
