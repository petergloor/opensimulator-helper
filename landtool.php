<?php
#
# landtool.php
#---------------------------------------------------------------------------------
# A simple landtool.php script with the sole purpose to enable land buy.
# This scripts requires XMLRPC support and the PDO extension with a mysql driver.
# Other drivers than mysql have nor been tested.
#=================================================================================
#  Copyright (c)Melanie Thielker and Teravus Ovares (http://opensimulator.org/)#
#  Redistribution and use in source and binary forms, with or without
#  modification, are permitted provided that the following conditions are met:
#      * Redistributions of source code must retain the above copyright
#        notice, this list of conditions and the following disclaimer.
#      * Redistributions in binary form must reproduce the above copyright
#        notice, this list of conditions and the following disclaimer in the
#        documentation and/or other materials provided with the distribution.
#      * Neither the name of the OpenSim Project nor the
#        names of its contributors may be used to endorse or promote products
#        derived from this software without specific prior written permission.
#
#  THIS SOFTWARE IS PROVIDED BY THE DEVELOPERS ``AS IS'' AND ANY
#  EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
#  WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
#  DISCLAIMED. IN NO EVENT SHALL THE CONTRIBUTORS BE LIABLE FOR ANY
#  DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
#  (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
#  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
#  ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
#  (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
#  SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
#---------------------------------------------------------------------------------
# updated for Robust installations: BlueWall 2011
# further minor changes by justincc (http://justincc.org)
# updated for PHP 7.0 and higher for use with PDO by Peter Gloor (Pius Noel) 2018.
#=================================================================================
#
# SETTINGS
#
// Modify this according to your configuration.
$dbsys = "mysql";				        // Only mysql has been tested.
$dbhost = "127.0.0.1";			    // Usually the localhost IP address.
$dbname = "opensimmaster";	    // Database name (usually for Robust).
$dbuser = "root";				        // Database user name.
$dbpass = "passw0rd";		        // Database user password.
$sysurl = "http://example.com"; // URL to further explanations returned in case of errors.
// End of modifiable settings.

#
# DATABASE access for user validation.
#
// validate_user returns true if a match has been found, otherwise false.
function validate_user($agent_id, $s_session_id) {
    global $dbsys, $dbhost, $dbuser, $dbpass, $dbname;
	$valid = FALSE;

    try {
        // Connect to database
        $pdo = new PDO(
            "$dbsys:host=$dbhost;dbname=$dbname",
            $dbuser,
            $dbpass,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING)
        );

        // Prepare the query
        $query = $pdo->prepare('Select UserID From Presence 
						Where UserID = ? And SecureSessionID = ?');

        // Execute the query and validate the user if the 
		// query is successful and an entry has been found.
        $res = $query->execute(array($agent_id, $s_session_id));
		$valid = ($res && $query->rowCount() > 0);

    } catch (PDOException $e) {
        die('ERROR: '.$e->getMessage());
    } catch (Exception $e) {
        die('ERROR: '.$e->getMessage());
    }
	// return the result (boolean)
    return $valid;
}

#
# XMLRPC Request Handler Method
#
// Handler function for the preflightBuyLandPrep method.
// Returns the xml response body without the header.
function buy_land_prep($method_name, $params, $app_data) {
    global $sysurl;
    $response_xml = null;

    // Get the data required from the request.
    $req = $params[0];
    $agentid = $req['agentId'];
    $sessionid = $req['secureSessionId'];
    $amount = $req['currencyBuy'];
    $billableArea = $req['billableArea'];

    // Validate the user
    if(validate_user($agentid, $sessionid)) {
        // Prepare the values for the response.
        $membership_levels = array(
            'levels' => array(
                'id' => "00000000-0000-0000-0000-000000000000",
                'description' => "some level"));

        $landUse = array(
            'upgrade' => False,
            'action'  => "Not change your membership");

        $currency = array(
            'estimatedCost' =>  "200.00");     // convert_to_real($amount));

        $membership = array(
            'upgrade' => False,
            'action'  => "Keep your monthly land use fees at US$0.00 per month",
            'levels'  => $membership_levels);

        // TODO: fix currency returns. Values are accessed by key, so
        // it doesn't make sence to return the same currency value twice.
        $response_xml = xmlrpc_encode(array(
            'success'    => True,
            'currency'   => $currency,
            'membership' => $membership,
            'landUse'    => $landUse,
            'currency'   => $currency,  // currency twice? Check and fix this.
            'confirm'    => ""));
    } else {
        // Unable to authenticate; prepare the error response.
        $response_xml = xmlrpc_encode(array(
            'success' => False,
            'errorMessage' => "\n\nUnable to Authenticate\n\nClick URL for more info.",
            'errorURI' => $sysurl));
    }
    
	// Add a header and send the response.
	header('Content-Type: text/xml');
  print $response_xml;
	
  return;
}

#
# MAIN PROCESS FLOW
#
// Create an xmlrpc server and register the corresponding preflightBuyLandPrep method.
$xmlrpc_server = xmlrpc_server_create();
xmlrpc_server_register_method($xmlrpc_server, "preflightBuyLandPrep", "buy_land_prep");

// Get the xml content from the request body.
$request_xml = file_get_contents('php://input');

// Call the xmlrpc method.
xmlrpc_server_call_method($xmlrpc_server, $request_xml, null);

// Done, finally destroy the server.
xmlrpc_server_destroy($xmlrpc_server);

?>
