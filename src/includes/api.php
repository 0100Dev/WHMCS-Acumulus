<?php

class api
{

    private $url;

    private $contractId;
    private $username;
    private $password;

    private $debug = false;
    private $debugEmail = '';

    private $params = array();
    private $action;
    private $category;

    private $errors;
    private $warnings;
    private $response;

    function __construct($contractId, $username, $password, $url = 'https://api.sielsystems.nl/acumulus/stable/')
    {
        $this->url = $url;

        $this->contractId = $contractId;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Executes the API request and stores the response.
     * @return int status : status response of API
     */
    public function execute()
    {
        $this->setParams(array(
            'format' => 'json',
            'contract' => array(
                'contractcode' => $this->contractId,
                'username' => $this->username,
                'password' => $this->password
            )
        ));

        if ($this->debug) {
            $this->setParams(array(
                'contract' => array(
                    'emailonerror' => $this->debugEmail,
                    'emailonwarning' => $this->debugEmail
                )
            ));
        }

        $xml = new SimpleXMLElement('<myxml/>');
        $this->_arrayToXml($xml, $this->params);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url . $this->category . '/' . $this->action . '.php?format=json');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'xmlstring=' . urlencode($xml->asXML()));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response = json_decode(curl_exec($ch), true);

        curl_close($ch);

        if (is_null($response)) {
            $this->errors['error'] = array(
                'code' => '999',
                'codetag' => 'AA999INT',
                'message' => 'Error - Response - Could not parse response from Acumulus API.'
            );
            return 1;
        }

        unset($response['errors']['count_errors']);
        $this->errors = $response['errors'];
        unset($response['errors']);

        unset($response['warnings']['count_errors']);
        $this->warnings = $response['warnings'];
        unset($response['warnings']);

        $this->response = $response;
        unset($this->response['status']);

        return $response['status'];
    }

    /**
     * Returns the response array
     * @return array $response : array of responses
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Checks if there are any errors
     * @return bool $hasErrors : if there are errors
     */
    public function hasErrors()
    {
        return ((count($this->errors) == 0) ? false : true);
    }

    /**
     * Returns the array with errors, when there are errors.
     * @return array $errors : array of errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Checks if there are any warnings
     * @return bool $hasWarnings : if there are warnings
     */
    public function hasWarnings()
    {
        return ((count($this->warnings) == 0) ? false : true);
    }

    /**
     * Returns the array with warnings, when there are warnings.
     * @return array : array of warnings
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Set a single paramter for the API call
     * @param string $key : In format "customer/type", slashes is new child.
     * @param string $value : Value of param
     * @return self $this : for stack
     */
    public function setParam($key, $value)
    {
        $key = explode('/', $key);
        $param = array();

        foreach (array_reverse($key) as $child) {
            $tmp = array();
            $tmp[$child] = ((count($param) == 0) ? $value : $param);
            $param = $tmp;
        }

        $this->params = array_replace_recursive($this->params, $param);

        return $this;
    }

    /**
     * Sets or merges a array of parameters
     * @param array $params : parameters for api call
     * @param bool $merge : if it's being merged with the existing params
     * @return self $this : for stack
     */
    public function setParams($params, $merge = true)
    {
        if ($merge) {
            $this->params = array_replace_recursive($this->params, $params);
        } else {
            $this->params = $params;
        }

        return $this;
    }

    /**
     * Sets the action for the API call
     * @param $action :
     * @return self $this : for stack
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Returns the action which will being used in the API call
     * @return string $action
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Sets the category for the API call
     * @param $category :
     * @return self $this : for stack
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Returns the category which will being used in the API call
     * @return string $category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Returns the parameters which will being used in the API call so far
     * @return array $params
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Enable debug function for API call
     * @param string $email : E-mail for debug e-mails.
     * @return self $this : for stack
     */
    public function enableDebug($email)
    {
        $this->debug = true;
        $this->debugEmail = $email;

        return $this;
    }

    /**
     * Sets a specific contract ID for API call
     * @param string $contractId : Contract ID to set
     * @return self $this : for stack
     */
    public function setContractId($contractId)
    {
        $this->contractId = $contractId;

        return $this;
    }

    /**
     * Sets a specific username for API call
     * @param string $username : Username to set
     * @return self $this : for stack
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Sets a specific password for API call
     * @param string $password : Password to set
     * @return self $this : for stack
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Sets the URL where the API call will being made to
     * @param string $url : URL to set
     * @return self $this : for stack
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Converts an array to valid XML for the API call.
     * @param SimpleXMLElement $object
     * @param array $data
     */
    private function _arrayToXml(SimpleXMLElement $object, array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $explode = explode('_', $key);
                $new_object = $object->addChild($explode[0]);
                $this->_arrayToXml($new_object, $value);
            } else {
                $object->addChild($key, $value);
            }
        }
    }

}