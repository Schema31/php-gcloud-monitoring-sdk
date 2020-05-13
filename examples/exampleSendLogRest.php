<?php

error_reporting(E_ALL);

require __DIR__."/../vendor/autoload.php";

use Schema31\GCloudMonitoringSDK\gCloud_Monitoring;

/*
 * Es. invio log 'semplice'
 * 
 * php -f exampleSendLogRest.php STREAMNAME AUTHKEY FACILITY SHORTMESSAGE
 * 
 * Es. Invio log con parametri aggiuntivi
 * 
 * php -f exampleSendLogRest.php STREAMNAME AUTHKEY FACILITY SHORTMESSAGE FULLMESSAGE 6 KEY1 VAL1 KEY2 VAL2
 */
try {

    if ($argc < 5) {
        throw new Exception("\n\nAttenzione!\n\nNon hai specificato abbastanza parametri: \n" . $argv[0] . " <StreamName> <Authentication> <Facility> <ShortMsg> [<FullMsg>] [<LogLevel>]\n\n");
    }

    $streamName = $argv[1];
    $authentication = $argv[2];
    $facility = (array_key_exists(3, $argv) ? $argv[3] : 'GELFLog');
    $shortMsg = (array_key_exists(4, $argv) ? $argv[4] : 'Default short message'); // VARCHAR(255)
    $fullMsg = (array_key_exists(5, $argv) ? $argv[5] : 'Default full message'); // LONGTEXT
    $logLevel = (array_key_exists(6, $argv) && is_int($argv[6]) ? $argv[6] : 6); // livello 6 = INFO
    $additionalData = array();

    /**
     * Gestiamo gli eventuali parametri addizionali
     */
    if ($argc > 7) {

        $additionals = array_slice($argv, 7);
        $additionalsLength = count($additionals);

        if ($additionalsLength % 2 != 0) {
            throw new Exception("\n\nAttenzione!\n\nNon hai specificato una sequenza corretta di parametri addizionali: indicali come <chiave> <valore>\n\n");
        }

        /**
         * Estrae i parametri 2 alla volta (chiave -> valore)
         */
        for ($index = 0; $index < $additionalsLength; $index = ($index + 2)) {
            $key = $additionals[$index];
            $value = $additionals[$index + 1];
            $additionalData[$key] = $value;
        }
    }

    $logger = new gCloud_Monitoring($streamName, $authentication);
    $logger->protocol = 'REST';
    $logger->message->setFacility($facility);
    $logger->message->setFile(__FILE__);

    /**
     * Se lo short message supera i 255 caratteri, replica la stringa all'interno del full message per evitare la perdita dei dati
     */
    if (strlen($shortMsg) > 255) {
        $fullMsg = $shortMsg . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . $fullMsg;
    }

    $logger->message->setShortMessage($shortMsg);
    $logger->message->setFullMessage($fullMsg);
    $logger->message->setLevel($logLevel);

    foreach ($additionalData as $key => $value) {
        $logger->message->setAdditional($key, $value);
    }


    $response = $logger->publish();

    echo ($response ? "\n\nOK\n\n" : "\n\nErrore invio\n\n");
} catch (Exception $exc) {
    echo $exc->getMessage();
}