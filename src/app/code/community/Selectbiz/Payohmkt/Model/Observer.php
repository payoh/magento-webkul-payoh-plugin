<?php
class Selectbiz_Payohmkt_Model_Observer{
    
    public function createWallet($observer){
        /* @var $userprofile Webkul_Marketplace_Model_Userprofile */
        $userprofile = $observer->getObject();

        if(!($userprofile instanceof Webkul_Marketplace_Model_Userprofile))
            return $this;
        
            if(!Mage::getStoreConfigFlag('selectbiz_payoh/payohmkt/create_wallet'))
                return $this;
        
        //Check if customer already have a wallet
        $wallet = Mage::getModel('selectbiz_payoh/wallet')->load($userprofile->getMageuserid(),'customer_id');
        if($wallet->getId())
            return $this;
        
        if((int)$userprofile->getWantpartner() == 1)
        {
            $customer = Mage::getModel('customer/customer')->load($userprofile->getMageuserid());
            
            if($customer->getId()){
                try {
                    
                    $this->getHelper()->registerWallet($customer, $userprofile);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }
    }
    
    public function sendPaymentToWallet($observer) {
        
        /* @var $saleslist Webkul_Marketplace_Model_Saleslist */
        $saleslist = $observer->getObject();
        
        if(!($saleslist instanceof Webkul_Marketplace_Model_Saleslist))
            return $this;
        
        if((int)$saleslist->getData('paidstatus') == 0)
            return $this;
            
        
        //Check if the new state is paid
        if((int)$saleslist->getOrigData('paidstatus') == 0 && (int)$saleslist->getData('paidstatus') == 1 &&
                (int)$saleslist->getOrigData('transid') == 0 && (int)$saleslist->getData('transid') > 0)
        {
            /* @var $order Mage_Sales_Model_Order */
            $order = Mage::getModel('sales/order')->load((int)$saleslist->getMageorderid());
            if(!$order->getId())
                return $this;
            
            
            if($order->getPayment()->getMethod() != 'payoh_webkit')
                return $this;
            
            //Check if customer already have a wallet
            $wallet = Mage::getModel('selectbiz_payoh/wallet')->load($saleslist->getMageproownerid(),'customer_id');
            if (!$wallet->getId()) {
                return $this;
            }
            
            $amount = $saleslist->getActualparterprocost();
            if (Mage::helper('marketplace')->getConfigTaxMange()) {
                $amount += $saleslist->getTotaltax();
            }

            $params = array(
                    "debitWallet"   => Mage::getSingleton('selectbiz_payoh/config')->getWalletMerchantId(),
                    "creditWallet"  => $wallet->getWalletId(),
                    "amount"        => number_format((float)$amount, 2, '.', ''),
                    "message"       => Mage::helper('payohmkt')->__('%s - Send payment for product %s in order %s', Mage::app()->getStore()->getName(), $saleslist->getMageproname(), $order->getIncrementId()),
                    //"scheduledDate" => "",
                    //"privateData" => "",
            );
        
            //Init APi kit
            /* @var $kit Selectbiz_Payoh_Model_Apikit_Kit */
            $kit = Mage::getSingleton('selectbiz_payoh/apikit_kit');
        
            try {
                $res = $kit->SendPayment($params);
                
                if(isset($res->lwError))
                {
                    throw new Exception($res->lwError->getMessage(), (int)$res->lwError->getCode(),null);
                }
                
                if(count($res->operations))
                {
                    /* @var $op Selectbiz_Payoh_Model_Apikit_Apimodels_Operation */
                    $op = $res->operations[0];
                
                    if($op->getHpayId())
                    {
                        //change transaction informations;
                        $transaction = Mage::getModel('marketplace/sellertransaction')->load($saleslist->getTransid(),'transid');
                        
                        if($transaction->getTransid())
                        {
                            $transaction->setType('Manual');
                            $transaction->setMethod('Payoh');
                            $transaction->save();
                            
                            /*$params = array(
                                    "debitWallet"   => Mage::getSingleton('selectbiz_payoh/config')->getWalletMerchantId(),
                                    "creditWallet"  => "SC",
                                    "amount"        => number_format((float)$saleslist->getTotalcommision(), 2, '.', ''),
                                    "message"       => Mage::helper('payohmkt')->__('%s - Send payment commision for order %s', Mage::app()->getStore()->getName(), $order->getIncrementId()),
                            );
                            
                            $res = $kit->SendPayment($params);
                            
                            if(isset($res->lwError))
                            {
                                throw new Exception($res->lwError->getMessage(), (int)$res->lwError->getCode(),null);
                            }*/
                        }     
                    }
                    else {
                        Mage::throwException($this->__("An error occurred. Please contact support."));
                    }
                }
                 
                } catch (Exception $e) {
                    Mage::logException($e);
                }   
        }
    }
    
    /**
     * 
     * @return Selectbiz_Payohmkt_Helper_Data
     */
    protected function getHelper(){
        return Mage::helper('payohmkt');
    }
}