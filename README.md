## Step 1: Activating the plugin

* Put the plugin in the correct directory: You will need to put the folder named `nerva` from this repo/unzipped release into the wordpress plugins directory. This can be found at `path/to/wordpress/folder/wp-content/plugins`

* Activate the plugin from the WordPress admin panel: Once you login to the admin panel in WordPress, click on "Installed Plugins" under "Plugins". Then simply click "Activate" where it says "Nerva - WooCommerce Gateway"

## Step 2 Option 1: Use your wallet address and viewkey (Currently for testnet configured,  patial payments can not performed with this method for now)

* Get your Nerva wallet address starting with 'N'
* Get your wallet secret viewkey from your wallet

A note on privacy: When you validate transactions with your private viewkey, your viewkey is sent to (but not stored on) https://testexplorer.getnerva.org/api/ over HTTPS. This could potentally allow an attacker to see your incoming, but not outgoing, transactions if he were to get his hands on your viewkey. Even if this were to happen, your funds would still be safe and it would be impossible for somebody to steal your money. For maximum privacy use your own nerva-wallet-rpc instance.

## Step 2 Option 2 (Recommended): Get a nerva daemon to connect to

### Running a full node

To do this: start the nerva daemon on your server and leave it running in the background. This can be accomplished by running `./nervad` inside your nerva downloads folder. The first time that you start your node, the nerva daemon will download and sync the entire nerva blockchain. This can take several hours and is best done on a machine with at least 4GB of ram, an SSD hard drive (with at least 5GB of free space), and a high speed internet connection.

### Setup your  nerva wallet-rpc

* Setup a nerva wallet using the nerva-wallet-cli tool.

* [Create a view-only wallet from that wallet for security.](https://monero.stackexchange.com/questions/3178/how-to-create-a-view-only-wallet-for-the-gui/4582#4582)

* Start the Wallet RPC and leave it running in the background. This can be accomplished by running `./nerva-wallet-rpc --rpc-bind-port 43929 --disable-rpc-login --log-level 2 --wallet-file /path/viewOnlyWalletFile --password password ` where "/path/viewOnlyWalletFile" is the wallet file for your view-only wallet.

## Step 4: Setup Nerva Gateway in WooCommerce

* Navigate to the "settings" panel in the WooCommerce widget in the WordPress admin panel.

* Click on "Checkout"

* Select "Nerva GateWay"

* Check the box labeled "Enable this payment gateway"

* Check either "Use ViewKey" or "Use nerva-wallet-rpc"

If You chose to use viewkey:

* Enter your nerva wallet address in the box labled "Nerva Address". If you do not know your address, you can run the `address` commmand in your nerva wallet

* Enter your secret viewkey in the box labeled "ViewKey"

If you chose to use nerva-wallet-rpc:

* Enter your nerva wallet address in the box labled "Nerva Address". If you do not know your address, you can run the `address` commmand in your nerva wallet

* Enter the IP address of your server in the box labeled "Nerva wallet rpc Host/IP"

* Enter the port number of the Wallet RPC in the box labeled "Nerva wallet rpc port" (it would be `43929` if you used the above example).

Finally:

* Click on "Save changes"

Original source README.md was for masari, This is the edited version according to Nerva
