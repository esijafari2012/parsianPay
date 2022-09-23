<?php


namespace Esijafari2012\ParsianPay;


use Esijafari2012\ParsianPay\Entities\PeyResult;
use Esijafari2012\ParsianPay\Entities\PinPayment;


/**
 * Class Pay
 * @package Esijafari2012\ParsianPay
 */
class Pay extends ParsianIPG
{

    /**
     * @var PeyResult|null
     */
    private $pr;


    /**
     * Pay constructor.
     * @param string $pin
     */
    public function __construct(string $pin="")
    {
        parent::__construct($pin);
        $this->pr=null;
    }


    /**
     * @param PinPayment $req
     * @return array
     * @throws ParsianErrorException
     */
    protected function sendPayRequest(PinPayment $req){

        $parameters = [
            'LoginAccount' => $req->getPin(),
            'Amount' => $req->getAmount(),
            'OrderId' => $req->getOrderId(),
            'CallBackUrl' => $req->getCallbackUrl(),
            'AdditionalData'=> $req->getAdditionalData()
        ];

        $result = $this->sendRequest($this->sale_url,'SalePaymentRequest',$parameters);

        $Token = $result['SalePaymentRequestResult']['Token'];
        $Status = $result['SalePaymentRequestResult']['Status'];
        $Message = $result['SalePaymentRequestResult']['Message'];

        if(($Status==0)&&($Token>0)){
            // update database
            return array(
                'Status' => $Status,
                'Token' => $Token,
                'Message' => $Message
            );
        }else{
            throw new ParsianErrorException( $Status,$Message);
        }

    }


    /**
     * @param $orderId
     * @param $amount
     * @param string $callbackUrl
     * @param string $additionalData
     * @return PeyResult|null
     */
    public function payment( $orderId, $amount,string $callbackUrl,string $additionalData="") {
        $req = new PinPayment();
        $req->setAmount($amount);
        $req->setOrderId($orderId);
        $req->setPin($this->pin);
        $req->setCallbackUrl($callbackUrl);
        $req->setAdditionalData($additionalData);

        $this->plog->writeInfo($this->getRequestMessage($req));

        $this->pr=null;

        try {
            $res=$this->sendPayRequest($req);
            $this->pr=new PeyResult( $res);
            $this->plog->writeInfo($this->getResultMessage($this->pr));
        } catch (ParsianErrorException $e) {
            $this->pr=new PeyResult( array(
                'Status' => $e->getCode(),
                'Token' => 0,
                'Message' => $e->getMessage()
            ));
            $this->plog->writeError($this->getResultMessage($this->pr));
        } catch (\Exception $e) {
            $this->pr=new PeyResult(array(
                'Status' => $e->getCode(),
                'Token' => 0,
                'Message' => $e->getMessage()
            ));
            $this->plog->writeError($this->getResultMessage($this->pr));
        }


        return  $this->pr;
    }

    /**
     * @return false
     */
    public function redirect() {
        if( $this->pr instanceof PeyResult){
            if(($this->pr->getStatus()==0)&&( $this->pr->getToken()>0)){
                $Token =  $this->pr->getToken();
                if(!empty($Token)){
                    header('LOCATION: '.$this->gate_url . $Token);
                    exit;
                }
            }
        }
        return false;
    }
}