<?php

return (function () {
            /*
             * Local configuration file to provide any overrides to your app.php configuration.
             * Copy and save this file as app_local.php and make changes as required.
             * Note: It is not recommended to commit files with credentials such as app_local.php
             * into source code version control.
             */
            $defaultSettings = [
                'Datasources'          => [
                    'default' => [
                        'host'     => 'localhost',
                        'username' => 'root',
                        'password' => '',
                        'database' => 'test'
                 
                    ]
                ],
            ];
           
            return $defaultSettings;
        })();