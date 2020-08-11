<?php

namespace Schema31\GCloudMonitoringSDK;

class gCloud_Monitoring {

    /**
     * Istanza della classe GELFMessage
     * @access public
     */
    public $message;

    /**
     * @var string protocol tipologia di protocollo da utilizzare per l'invio del messaggio (GELF / REST)
     * @access public
     */
    public $protocol = 'GELF';

    /**
     * Current Library Version
     *
     * @var string
     * @access public
     */
    const LIBRARY_VERSION = "gCloud_Monitoring 1.0.5 [Composer]";

    /**
     * @var string GELF Endpoint (normally hardcoded)
     * @access public
     */
    const GELF_SERVER = "gelf.gcloud.schema31.it";

    /**
     * @var string REST Endpoint (normally hardcoded)
     * @access public
     */
    const REST_ADAPTOR = "https://adaptor.monitoring.gcloud.schema31.it";

    /**
     * Inizializza tutte le proprietà di base del log
     */
    public function __construct($streamName = '', $authentication = '') {

        /**
         * Recuperiamo l'hostname
         */
        if (isset($_SERVER) && isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            list($host) = explode(',', str_replace(' ', '', $_SERVER['HTTP_X_FORWARDED_HOST']));
        } elseif (isset($_SERVER) && isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            $host = php_uname('n');
        }

        /**
         * Inizializziamo l'oggetto della classe 'GELFMessage'
         */
        $this->message = new GELFMessage();

        /**
         * Utilizziamo lo 'streamName' in sostituzione del nome host
         */
        if (strlen($streamName) > 0) {
            $this->message->setHost($streamName);
        } elseif (defined('STREAMNAME')) {
            $this->message->setHost(STREAMNAME);
        } else {
            $this->message->setHost($host);
        }

        /**
         * Settiamo la chiave di autenticazione
         */
        if (strlen($authentication) > 0) {
            $this->message->setAuthentication($authentication);
        } elseif (defined('AUTHENTICATION')) {
            $this->message->setAuthentication(AUTHENTICATION);
        }

        /**
         * Settiamo il numero di versione dell'applicativo chiamante (se indicato)
         */
        if (defined('VERSION')) {
            $this->message->setAdditional("Release", VERSION);
        }

        /**
         * Settiamo i parametri di defualt per la chiamata
         */
        $this->message->setLevel(GELFMessage::INFO);
        $this->message->setAdditional("Hostname", php_uname('n'));
        $this->message->setAdditional("LibraryVersion", $this->getLibraryVersion());

        if (isset($_SERVER) && isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->message->setAdditional("Client Browser", $_SERVER['HTTP_USER_AGENT']);
        }

        if (isset($_SERVER) && isset($_SERVER['REMOTE_ADDR'])) {
            $this->message->setAdditional("Client IP", $_SERVER['REMOTE_ADDR']);
        }

        if (isset($_SERVER) && isset($_SERVER['HTTP_X_FORWARDED_FOR']) && isset($_SERVER["REMOTE_ADDR"])) {
            $hostname = $_SERVER['HTTP_X_FORWARDED_FOR'] . ' via ' . $_SERVER["REMOTE_ADDR"];
            $this->message->setAdditional("Real Client IP", $hostname);
        }
    }

    /**
     * Effettua l'invio del log
     * @return boolean
     */
    public function publish() {

        try {
            /**
             * Inviamo il log
             */
            if ($this->protocol == 'REST') {
                return self::writeREST($this->message);
            } else {
                return self::writeGELF($this->message);
            }
        } catch (Exception $e) {
            return FALSE;
        }
    }

    /**
     * Effettua l'invio del log GELF
     * @param GELFLog $message messaggio da inviare
     * @return boolean
     */
    private static function writeGELF(GELFMessage $message) {

        /**
         * Hostname del servizio
         */
        $server = defined('GELFSERVER') ? GELFSERVER : self::GELF_SERVER;

        /**
         * Inizializziamo la classe per l'invio del messaggio
         */
        $publisher = new GELFMessagePublisher($server, 12201);

        /**
         * Attiviamo la soppressione degli errori, perchè non vogliamo ritornare 
         * warning in caso di errori di connessione. 
         * NOTA!!!! La libreria PEAR usa 'stream_socket_client()'
         */
        return @$publisher->publish($message);
    }

    /**
     * Effettua l'invio del log REST
     * @param GELFLog $message messaggio da inviare
     * @return boolean
     */
    private static function writeREST(GELFMessage $message) {

        /**
         * Hostname del servizio
         */
        $server = defined('RESTADAPTOR') ? RESTADAPTOR : self::REST_ADAPTOR;

        /**
         * Formattiamo i parametri da inviare in POST
         */
        $postFields = $message->toArray();
        $postFieldsStr = '';
        foreach ($postFields as $key => $value) {
            $postFieldsStr .= $key . '=' . $value . '&';
        }
        rtrim($postFieldsStr, '&');

        /**
         * Predisponiamo la richiesta
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, {$server}."/Adaptor/listener/REST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, count($postFields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFieldsStr);
        curl_setopt($ch, CURLOPT_USERAGENT, self::LIBRARY_VERSION));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        /**
         * Eseguiamo la richiesta e verifichiamone lo stato
         */
        @curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200 ? FALSE : TRUE;

        curl_close($ch);

        return $status;
    }

    protected function getLibraryVersion() {
        return static::LIBRARY_VERSION;
    }

}
