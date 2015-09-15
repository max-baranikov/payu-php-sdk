<?php

namespace PayU;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PayU\Api\Request\Command;
use PayU\Api\Request\PaymentRequest;
use PayU\Api\Request\QueryRequest;
use PayU\Api\Request\RequestInterface;
use PayU\Api\Response\Builder\BuilderInterface;
use PayU\Api\Response\Builder\ResponseBuilder;
use PayU\Environment\EnvironmentInterface;
use PayU\Environment\Production;
use PayU\Environment\Sandbox;
use PayU\Exception\InvalidBuilderException;
use PayU\Exception\InvalidEnvironmentException;
use PayU\Exception\InvalidLanguageException;
use PayU\Merchant\Credentials;
use PayU\Transaction\Transaction;

class PayU
{

    const ENV_PRODUCTION = 'production';
    const ENV_SANDBOX    = 'sandbox';
    const ENV_DEFAULT    = self::ENV_PRODUCTION;

    const LANGUAGE_ENGLISH    = 'en';
    const LANGUAGE_SPANISH    = 'es';
    const LANGUAGE_PORTUGUESE = 'pt';
    const LANGUAGE_DEFAULT    = self::LANGUAGE_ENGLISH;

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var EnvironmentInterface
     */
    protected $environment;

    /**
     * @var BuilderInterface
     */
    protected $builder;

    /**
     * @var string
     */
    protected $language;

    /**
     * @var string
     */
    protected $merchantId = null;

    /**
     * @var Credentials
     */
    protected $credentials = null;

    /**
     * @var string
     */
    protected $notifyUrl = null;

    /**
     * @var mixed
     */
    protected $partnerId = null;

    public function __construct(EnvironmentInterface $env, BuilderInterface $builder, $language = self::LANGUAGE_DEFAULT, $partnerId = null)
    {
        $this->httpClient = new Client();

        $this->setEnvironment($env);
        $this->setBuilder($builder);
        $this->setLanguage($language);

    }

    private function setLanguage($language = self::LANGUAGE_DEFAULT)
    {
        switch ($language) {
            case null:
                $this->language = self::LANGUAGE_DEFAULT;
                break;
            case self::LANGUAGE_ENGLISH:
                $this->language = self::LANGUAGE_ENGLISH;
                break;
            case self::LANGUAGE_PORTUGUESE:
                $this->language = self::LANGUAGE_PORTUGUESE;
                break;
            case self::LANGUAGE_SPANISH:
                $this->language = self::LANGUAGE_SPANISH;
                break;
            default:
                throw new InvalidLanguageException();
        }
    }

    public function getLanguage()
    {
        return $this->language;
    }

    private function setEnvironment(EnvironmentInterface $env)
    {
        $this->environment = $env;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    private function setBuilder(BuilderInterface $builder)
    {
        $this->builder = $builder;
    }

    public function setMerchantId($merchant)
    {
        $this->merchantId = (string)$merchant;
    }

    public function getMerchantId()
    {
        return $this->merchantId;
    }

    public function setCredentials(Credentials $credentials)
    {
        $this->credentials = $credentials;
    }

    public function getCredentials()
    {
        return $this->credentials;
    }

    public function setNotifyUrl($url)
    {
        $this->notifyUrl = $url;
    }

    public function getNotifyUrl()
    {
        return $this->notifyUrl;
    }

    public function setPartnerId($id)
    {
        $this->partnerId = $id;
    }

    public function getPartnerId()
    {
        return $this->partnerId;
    }

    public function getOrderById($orderId)
    {
        $request = new QueryRequest(Command::QUERY_ORDER_DETAIL);
        $request->setOrderId($orderId);

        return $this->request($request);
    }

    public function getOrderByReference($referenceCode)
    {
        $request = new QueryRequest(Command::QUERY_ORDER_DETAIL_BY_REFERENCE_CODE);
        $request->setReferenceCode($referenceCode);

        return $this->request($request);
    }

    public function getTransactionById($transactionId)
    {
        $request = new QueryRequest(Command::QUERY_TRANSACTION_RESPONSE_DETAIL);
        $request->setTransactionId($transactionId);

        return $this->request($request);
    }

    public function doPayment(Transaction $transaction)
    {
        $request = new PaymentRequest(Command::PAYMENT_SUBMIT_TRANSACTION);
        $request->setTransaction($transaction);

        return $this->request($request);
    }

    public function request(RequestInterface $request)
    {
        try {
            $url = $this->environment->getUrl($request->getContext());

            $body = $request->compile($this);

            $headers = $this->environment->getHeaders();

            $options = array_merge(['body'=>$body],['headers'=>$headers],$this->environment->getOptions());

            $response = $this->httpClient->post($url,$options);

            return $this->builder->build($request,$response);
        } catch (RequestException $e) {
            // catch and threat the errors
        }
    }

    public static function ping(Credentials $credentials, $language = null, $environment = null)
    {
        $instance = self::factory($language,$environment);
        $instance->setCredentials($credentials);

        $request = new QueryRequest(Command::PING);

        return $instance->request($request);
    }

    public static function factory($language = self::LANGUAGE_DEFAULT, $environment = self::ENV_DEFAULT, $builder = null)
    {
        if (is_null($environment)) {
            $environment = self::ENV_DEFAULT;
        }

        if ($environment == self::ENV_PRODUCTION) {
            $environment = new Production();
        } elseif ($environment == self::ENV_SANDBOX) {
            $environment = new Sandbox();
        } elseif (!$environment instanceof EnvironmentInterface) {
            throw new InvalidEnvironmentException();
        }

        if (is_null($builder)) {
            $builder = new ResponseBuilder();
        } elseif (!$builder instanceof BuilderInterface) {
            throw new InvalidBuilderException();
        }

        return new self($environment,$builder,$language);
    }
}