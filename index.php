<?php

      require './vendor/autoload.php';

       use Kreait\Firebase\Factory;
       use Kreait\Firebase\ServiceAccount;

        $serviceAccount = ServiceAccount::fromJsonFile(__DIR__.'/secret/deployarticle-firebase-adminsdk-308br-6429f7bcd2.json');

         $firebase = (new Factory)
           ->withServiceAccount($serviceAccount)
           ->create();

            $database = $firebase->getDatabase();




