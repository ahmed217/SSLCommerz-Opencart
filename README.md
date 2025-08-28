# SSLCommerz - Opencart

SSLCOMMERZ-Online Payment Gateway For Bangladesh

- This fork is for Opencard Version 4.x.x (development in progress 8/28/2025)

- This Module Work for Opencart Version 3.x.x

### Prerequisite

  - A functioning opencart website
  - A [Sandbox Account](https://developer.sslcommerz.com/registration/ "SSLCommerz Sandbox Registration") or [Live Merchant Account](https://signup.sslcommerz.com/register/ "Merchant Registration")


### Configuration and usage

    - Install the extension via the OpenCart Extension Installer (upload the extension/sslcommerz folder as a zipped OCMOD package or place it directly on the server if developing).

    - Go to Extensions → Extensions → Payments → SSLCommerz → Edit.

    - Set:

        - Store ID and Store Password from your SSLCommerz account.

        - Mode: Sandbox for testing, Live for production.

        - Order statuses: Paid, Pending, Failed.

        - Optional: Geo Zone, Minimum Total, Sort Order.

    - Ensure your store has a valid HTTPS URL accessible by SSLCommerz for IPN callbacks.
	
### Installation Steps:

Please follow these steps to install the SSLCommerz Payment Gateway extension -

- Step 1: Download or clone the project files 

- Step 3: Unzip and upload `catalog` and `admin` with all contents inside to the root of your opencart website.
  
- Step 4: Login to the Open Cart admin section and go to Extensions > Extensions > Payments

- Step 5: Find SSLCommerz extention in the list

- Step 6: Click `Install` and then `Edit` the payment module settings

- Step 7: Add store id, store password and choose Test Mode mode Sandbox.
 
- Step 8: Do UAT by doing some test transactions

- Step 9: If UAT is successful, you can use live store id and password (same process as step 7) and choose Test Mode mode Live.


Notes :
* Initially order status will be `Pending`

* Order Status (Payment Success): Should be `Processing`.

* Order Status (Payment Failed): Should be `Failed`.

* Order Status (Payment Risk): Should be `Canceled`.

* Geo Zone: All Zones

--------------------------------------------
![](configuration-screenshot.png)

- Author : Prabal Mallick
- Team Email: integration@sslcommerz.com (For any query)
- More info: https://www.sslcommerz.com

© 2019-2023 SSLCOMMERZ ALL RIGHTS RESERVED
