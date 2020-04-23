<?php

namespace Matthv\PayboxGateway\Requests;

use Carbon\Carbon;
use Matthv\PayboxGateway\HttpClient\GuzzleHttpClient;
use Matthv\PayboxGateway\Responses\Capture as CaptureResponse;
use Matthv\PayboxGateway\Services\Amount;
use Matthv\PayboxGateway\Services\ServerSelector;
use Exception;
use Illuminate\Contracts\Config\Repository as Config;

class Capture extends Request
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'paybox_direct';

    /**
     * @var GuzzleHttpClient
     */
    protected $client;

    /**
     * {@inheritdoc}
     */
    protected $amountFill = true;

    /**
     * Number of request in current day.
     *
     * @var int|null
     */
    protected $numRequest = null;

    /**
     * Call number provided by Paybox in IPN after authorization request.
     *
     * @var string|null
     */
    protected $payboxCallNumber = null;

    /**
     * Transaction number provided by Paybox in IPN after authorization request.
     *
     * @var string|null
     */
    protected $payboxTransactionNumber = null;

    /**
     * Capture constructor.
     *
     * @param ServerSelector $serverSelector
     * @param Config $config
     * @param Amount $amountService
     * @param GuzzleHttpClient $client
     */
    public function __construct(
        ServerSelector $serverSelector,
        Config $config,
        Amount $amountService,
        GuzzleHttpClient $client
    ) {
        parent::__construct($serverSelector, $config, $amountService);
        $this->client = $client;
    }

    /**
     * Set call number provided by Paybox.
     *
     * @param string $payboxCallNumber
     *
     * @return $this
     */
    public function setPayboxCallNumber($payboxCallNumber)
    {
        $this->payboxCallNumber = $payboxCallNumber;

        return $this;
    }

    /**
     * Set transaction number provided by Paybox.
     *
     * @param string $payboxTransactionNumber
     *
     * @return $this
     */
    public function setPayboxTransactionNumber($payboxTransactionNumber)
    {
        $this->payboxTransactionNumber = $payboxTransactionNumber;

        return $this;
    }

    /**
     * Set url for capture based on authorization url. If other is set to false, it will find
     * matching Paybox Direct server, otherwise it will try to find other Paybox Direct url.
     *
     * @param string $authorizationUrl
     * @param bool $other
     *
     * @return $this
     */
    public function setUrlFrom($authorizationUrl, $other = false)
    {
        $this->url = $this->serverSelector->findFrom('paybox', $this->type, $authorizationUrl,
            $other);

        return $this;
    }

    /**
     * Send Paybox Direct capture request and return response.
     *
     * @param array $parameters
     *
     * @return CaptureResponse
     */
    public function send(array $parameters = [])
    {
        $parameters = $parameters ?: $this->getParameters();

        return new CaptureResponse($this->client->request($this->getUrl(), $parameters));
    }

    /**
     * Get parameters that will be send to Paybox Direct.
     *
     * @return array
     */
    public function getParameters()
    {
        return [
            'VERSION' => '00104',
            'TYPE' => '00002',
            'SITE' => $this->config->get('paybox.site'),
            'RANG' => $this->config->get('paybox.rank'),
            'CLE' => $this->config->get('paybox.back_office_password'),
            'NUMQUESTION' => $this->numRequest,
            'MONTANT' => $this->amount,
            'DEVISE' => $this->currencyCode,
            'REFERENCE' => $this->paymentNumber,
            'NUMAPPEL' => $this->payboxCallNumber,
            'NUMTRANS' => $this->payboxTransactionNumber,
            'DATEQ' => $this->getFormattedDate($this->time ?: Carbon::now()),
        ];
    }

    /**
     * Set request number in current day.
     *
     * @param $dayRequestNumber
     *
     * @return $this
     * @throws Exception
     */
    public function setDayRequestNumber($dayRequestNumber)
    {
        if (! is_int($dayRequestNumber)) {
            throw new Exception('Number of request should be integer');
        }

        if ($dayRequestNumber < 1 || $dayRequestNumber > 2147483647) {
            throw new Exception(('Number of request should in range <1,2147483647>'));
        }

        $this->numRequest = str_pad($dayRequestNumber, 10, '0', STR_PAD_LEFT);

        return $this;
    }

    /**
     * Get formatted date in format required by Paybox Direct.
     *
     * @param Carbon $date
     *
     * @return string
     */
    protected function getFormattedDate(Carbon $date)
    {
        return $date->format('dmYHis');
    }
}
