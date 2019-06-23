<?php
class Hs_Customcartdiscount_Model_Observer {
    
    const XML_PATH_MODULE_ENABLE    = 'customcartdiscount/customcartdiscount_group/enable';
    const XML_PATH_DISCOUNT_AMOUNT  = 'customcartdiscount/customcartdiscount_group/discount';
    
    public function setDiscount($observer) {
        if(Mage::getStoreConfig(self::XML_PATH_MODULE_ENABLE)){
            $quote = $observer->getEvent()->getQuote();
            $quoteid = $quote->getId();
            //$discountAmount = Mage::getStoreConfig(self::XML_PATH_DISCOUNT_AMOUNT);
			if(Mage::getSingleton('customer/session')->isLoggedIn()){
			 $customerData = Mage::getSingleton('customer/session')->getCustomer();
			 $reward_pt = intval($customerData->getreward_point());
			 $total_reward_value = $reward_pt * intval(Mage::getStoreConfig(self::XML_PATH_DISCOUNT_AMOUNT));
			 if($total_reward_value > floatval($quote->getGrandTotal())){
				$discountAmount = floatval($quote->getGrandTotal());
				$total_remaining = ($total_reward_value - intval($quote->getGrandTotal()))/intval(Mage::getStoreConfig(self::XML_PATH_DISCOUNT_AMOUNT));
				Mage::getSingleton('core/session')->unsRemainingReward();
				Mage::getSingleton('core/session')->setRemainingReward($total_remaining); 
				
			 }else{
				$discountAmount = $total_reward_value;
				Mage::getSingleton('core/session')->unsRemainingReward();
				Mage::getSingleton('core/session')->setRemainingReward(0);
			 }
			}else{
				$discountAmount=0.0;
			}		
			//$discountAmount = 29.5;
            if ($quoteid) {
                if ($discountAmount > 0) {
                    $total = $quote->getBaseSubtotal();
                    $quote->setSubtotal(0);
                    $quote->setBaseSubtotal(0);

                    $quote->setSubtotalWithDiscount(0);
                    $quote->setBaseSubtotalWithDiscount(0);

                    $quote->setGrandTotal(0);
                    $quote->setBaseGrandTotal(0);


                    $canAddItems = $quote->isVirtual() ? ('billing') : ('shipping');
                    foreach ($quote->getAllAddresses() as $address) {

                        $address->setSubtotal(0);
                        $address->setBaseSubtotal(0);

                        $address->setGrandTotal(0);
                        $address->setBaseGrandTotal(0);

                        $address->collectTotals();

                        $quote->setSubtotal((float) $quote->getSubtotal() + $address->getSubtotal());
                        $quote->setBaseSubtotal((float) $quote->getBaseSubtotal() + $address->getBaseSubtotal());

                        $quote->setSubtotalWithDiscount(
                                (float) $quote->getSubtotalWithDiscount() + $address->getSubtotalWithDiscount()
                        );
                        $quote->setBaseSubtotalWithDiscount(
                                (float) $quote->getBaseSubtotalWithDiscount() + $address->getBaseSubtotalWithDiscount()
                        );

                        $quote->setGrandTotal((float) $quote->getGrandTotal() + $address->getGrandTotal());
                        $quote->setBaseGrandTotal((float) $quote->getBaseGrandTotal() + $address->getBaseGrandTotal());

                        $quote->save();

                        $quote->setGrandTotal($quote->getBaseSubtotal() - $discountAmount)
                                ->setBaseGrandTotal($quote->getBaseSubtotal() - $discountAmount)
                                ->setSubtotalWithDiscount($quote->getBaseSubtotal() - $discountAmount)
                                ->setBaseSubtotalWithDiscount($quote->getBaseSubtotal() - $discountAmount)
                                ->save();
                        
                        if ($address->getAddressType() == $canAddItems) {
                            //echo $address->setDiscountAmount; exit;
                            $address->setSubtotalWithDiscount((float) $address->getSubtotalWithDiscount() - $discountAmount);
                            $address->setGrandTotal((float) $address->getGrandTotal() - $discountAmount);
                            $address->setBaseSubtotalWithDiscount((float) $address->getBaseSubtotalWithDiscount() - $discountAmount);
                            $address->setBaseGrandTotal((float) $address->getBaseGrandTotal() - $discountAmount);
                            if ($address->getDiscountDescription()) {
                                $address->setDiscountAmount(-($address->getDiscountAmount() - $discountAmount));
                                $address->setDiscountDescription($address->getDiscountDescription() . ', From Available Budget');
                                $address->setBaseDiscountAmount(-($address->getBaseDiscountAmount() - $discountAmount));
                            } else {
                                $address->setDiscountAmount(-($discountAmount));
                                $address->setDiscountDescription('From Available Budget');
                                $address->setBaseDiscountAmount(-($discountAmount));
                            }
                            $address->save();
                            
                        }//end: if
                    } //end: foreach
                    //echo $quote->getGrandTotal();
                  
                    foreach ($quote->getAllItems() as $item) {
                        //We apply discount amount based on the ratio between the GrandTotal and the RowTotal
                        $rat = $item->getPriceIncustomcartdiscountclTax() / $total;
                        $ratdisc = $discountAmount * $rat;
                        $item->setDiscountAmount(($item->getDiscountAmount() + $ratdisc) * $item->getQty());
                        $item->setBaseDiscountAmount(($item->getBaseDiscountAmount() + $ratdisc) * $item->getQty())->save();
                    }
                }
            }
        }
    }
	
	
	public function setRemaining(){
		if(Mage::getSingleton('customer/session')->isLoggedIn()){
			$total_remaining = intval(Mage::getSingleton('core/session')->getRemainingReward());
			Mage::getSingleton('core/session')->unsRemainingReward();
			$customerData = Mage::getSingleton('customer/session')->getCustomer();
			$email = $customerData->getEmail();
			
			$customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getWebsite()->getId())->loadByEmail($email);
			$customer->setReward_point($total_remaining);
			try {
				$customer->save();				
			} catch (Exception $ex) {

			}
		}
	}

}
