<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;

$bot = new Nutgram('5921672890:AAEheSPsm2nsCuF4qWemK74HaJcBEAXPfro'); // new instance
$bot->setRunningMode(Polling::class);

// ...

$bot->run(); // start to listen to updates, until stopped