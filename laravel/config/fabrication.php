<?php

return [
    /*
    | Every programming step ("Program", "Programmed", "CNC Programming"... any step name containing
    | "program") is assigned to this fab user (matched by name) when it is created.
    */
    'default_programmer' => env('FD_DEFAULT_PROGRAMMER', 'Jon Kwekel'),
];
