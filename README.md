Yo Payments Module for Magento E-Commerce
==================================================
This module can be used in your magento eCommerce site to accept Mobile Money payments through Yo Payments Gateway. It works with Magento 2.x and the base currency supported is only UGX. The payer must have a valid mobile money number with sufficient funds for the transaction to succeed.

Installation
==================================================
1. Install Magento2
2. Obtain YoUgandaLimited_YoPaymentsGateway module from this repository. 
    Assuming your magento root directory is /var/www/html/magento2. Create a directory:
    /var/www/html/magento2/app/code if it does not exist and clone this repository in it like so:
    cd /var/www/html/magento2/app/code/
    git clone https://github.com/YO-Uganda/magento_yopayments_method.git
    This will create a directory magento_yopayments_method so you can have a file system that looks like: 
    /var/www/html/magento2/app/code/magento_yopayments_method
    Rename this to YoUgandaLimtied so that you end up with a file system that looks like below:
    /var/www/html/magento2/app/code/YoUgandaLimtied/

3. Enable the module using magento commandline. See the example command you would run to enable this module:
    php bin/magento module:disable YoUgandaLimited_YoPaymentsGateway
4. Then update your magento database with the following code:
    bin/magento setup:upgrade
5. Now clear the cache with the following command:
    php bin/magento cache:flush


Yo! Payments Configuration
==================================================
1. Login to your store admin site and navigate to STORES > Configuration > SALES > Payment Methods

2. If you had enabled YoUgandaLimited_YoPaymentsGateway module, you should now see Yo Payments Gateway among the list of payment methods.

3. Expand to see configuration fields.

4. Configure the Yo Payments details described below:
Enable: Select Yes.
4. 1. API Username: This is the production Yo Payments API username. Obtain this from Yo Payments portal under API Access details.
4. 2. API Password: This is the production Yo Payments API password. Obtain this from Yo Payments portal under API Access details.
4. 4. Sandbox API Username: This is the sandbox Yo Payments API username if you have any. Obtain this from Yo Payments Sandbox portal under API Access details.
4. 5. Sandbox API Password: This is the sandbox Yo Payments API password if you have any. Obtain this from Yo Payments Sandbox portal under API Access details.
4. 6. Enable Sandbox: If you select Yes, payment requests will be submitted to the Sandbox system instead of the production. This is useful when you are testing/simulating how transactions will be processed while in production mode.
4. 7. Send Payment Request to Pay1: Set this to Yes if you would like production payment request to be sent to the Pay1 system.

5. Save the config and do a real test on your store by selecting items to your cart and on checkout page, select Yo Payments method. In Sandbox mode, you can use any valid phone number to test the payment (e.g 256772221111,256701222333). In production, you will have to approve your payment by entering your mobile money PIN on your phone.

6. If the payment is successful, under transactions, it will indicate as closed. However, the order status shall continue to indicate "Processing" but the transaction status shall indicate "Closed".


Support
===========================================
In case you are stuck, you can find support by sending an email to support@yo.co.ug.

