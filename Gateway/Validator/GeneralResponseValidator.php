<?php
namespace Sequra\Core\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class GeneralResponseValidator extends AbstractValidator
{
    /**
     * Constructor
     *
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $response = $validationSubject['response'];
        
        $isValid = true;
        $errorMessages = [];
        $errorCodes = [];

        // if(!isset($response['Ds_MerchantParameters'])){
        //     $isValid = false;
        //     $errorMessages[] = "Error en la devolución online: ".$response['errorCode']??'';
        //     $errorCodes[] = $response['errorCode']??'';
        // } else {
        //     $params = json_decode(base64_decode($response['Ds_MerchantParameters']),true);
        //     if($params['Ds_Response']!='0000' && $params['Ds_Response']!='0900'){
        //         $isValid = false;
        //         $errorMessages[] = "Error en la devolución online: ".$params['Ds_Response'];
        //         $errorCodes[] = $params['Ds_Response'];
        //     }
        // }

        return $this->createResult($isValid, $errorMessages, $errorCodes);
    }
}
