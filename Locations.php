<?php 
// defined('BASEPATH') OR exit('No direct script access allowed');

error_reporting(-1);
ini_set('display_errors', 1);

defined('FEDEX_KEY')  OR define('FEDEX_KEY', '');
defined('FEDEX_PASSWORD')  OR define('FEDEX_PASSWORD', '');
defined('FEDEX_ACCOUNT_NUMBER')  OR define('FEDEX_ACCOUNT_NUMBER', '');
defined('FEDEX_METER_NUMBER')  OR define('FEDEX_METER_NUMBER', '');

require_once 'vendor/autoload.php';

use FedEx\LocationsService\Request;
use FedEx\LocationsService\ComplexType;
use FedEx\LocationsService\SimpleType;

/**
 * Location
 */
class Locations {

	public function fedex($zip_code = 10001, $radius = 10){
        $searchLocationsRequest = new ComplexType\SearchLocationsRequest();

        // Authentication & client details.
        $searchLocationsRequest->WebAuthenticationDetail->UserCredential->Key = FEDEX_KEY;
        $searchLocationsRequest->WebAuthenticationDetail->UserCredential->Password = FEDEX_PASSWORD;
        $searchLocationsRequest->ClientDetail->AccountNumber = FEDEX_ACCOUNT_NUMBER;
        $searchLocationsRequest->ClientDetail->MeterNumber = FEDEX_METER_NUMBER;

        $searchLocationsRequest->TransactionDetail->CustomerTransactionId = 'test locations service request';

        // Version.
        $searchLocationsRequest->Version->ServiceId = 'locs';
        $searchLocationsRequest->Version->Major = 11;
        $searchLocationsRequest->Version->Intermediate = 0;
        $searchLocationsRequest->Version->Minor = 0;

        // Locations search criterion.
        $searchLocationsRequest->LocationsSearchCriterion = SimpleType\LocationsSearchCriteriaType::_ADDRESS;

        // Address
        // 10001 South 1st Street, Austin, TX, USA
        $searchLocationsRequest->Address->StreetLines = ['Manhattan'];
        $searchLocationsRequest->Address->City = 'New York';
        $searchLocationsRequest->Address->StateOrProvinceCode = 'NY';
        $searchLocationsRequest->Address->PostalCode = $zip_code;
        $searchLocationsRequest->Address->CountryCode = 'US';

        // Multiple matches action.
        $searchLocationsRequest->MultipleMatchesAction = SimpleType\MultipleMatchesActionType::_RETURN_ALL;

        // Get Search Locations reply.
        $locationServiceRequest = new Request();
        $searchLocationsReply = $locationServiceRequest->getSearchLocationsReply($searchLocationsRequest);

        if (empty($searchLocationsReply->AddressToLocationRelationships[0]->DistanceAndLocationDetails)) {
            return;
        }

        $dom     = new DOMDocument( "1.0" );
        $node    = $dom->createElement( "markers" );
        $parnode = $dom->appendChild( $node );


        foreach ($searchLocationsReply->AddressToLocationRelationships[0]->DistanceAndLocationDetails as $value) {

            if ( is_array( $value ) || is_object( $value ) ) {

                $hours = $value->LocationDetail->NormalHours;

                $distance = round( $value->Distance->Value, 2 );
                $units    = strtolower( $value->Distance->Units );

                $company_name = $value->LocationDetail->LocationContactAndAddress->Contact->CompanyName;
                $phone_number = $value->LocationDetail->LocationContactAndAddress->Contact->PhoneNumber;

                $street      = $value->LocationDetail->LocationContactAndAddress->Address->StreetLines;
                $city        = $value->LocationDetail->LocationContactAndAddress->Address->City;
                $state       = $value->LocationDetail->LocationContactAndAddress->Address->StateOrProvinceCode;
                $postal_code = $value->LocationDetail->LocationContactAndAddress->Address->PostalCode;

                //$geo_codes = $value->LocationDetail->GeographicCoordinates;
                $map_url = $value->LocationDetail->MapUrl;

                $coords = $this->get_coordinates( $map_url );

                $store_closes = $value->LocationDetail->NormalHours[0]->OperationalHours; //OPEN_ALL_DAY

                if ( $store_closes == 'OPEN_ALL_DAY' ) {
                    $store_closes = 'Closed';
                } else {
                    $store_closes = @$value->LocationDetail->NormalHours[0]->Hours[0]->Ends;
                }

                if ( is_array( $value->LocationDetail->CarrierDetails ) ) {
                    @$last_pickup_orange = $value->LocationDetail->CarrierDetails[0]->EffectiveLatestDropOffDetails->Time;
                    @$last_pickup_green  = $value->LocationDetail->CarrierDetails[2]->EffectiveLatestDropOffDetails->Time;
                } elseif ( is_object( $value->LocationDetail->CarrierDetails ) ) {
                    @$last_pickup_orange = $value->LocationDetail->CarrierDetails->EffectiveLatestDropOffDetails->Time;
                    @$last_pickup_green  = $last_pickup_orange;
                }

                if ( !empty( $street ) ) {
                    $info[] = [
                        'name'     => $company_name,
                        'phone'    => $phone_number,
                        'street'   => $street,
                        'city'     => $city,
                        'state'    => $state,
                        'dist'     => $distance,
                        'time_gr'  => 'Last Pickup ' . $this->get12hoursTime( $last_pickup_green ),
                        'time_ai'  => 'Last Pickup ' . $this->get12hoursTime( $last_pickup_orange ),
                        'zip_code' => $postal_code,
                        'store_closes' => $this->get12hoursTime( $store_closes )
                    ];
                }

                $node    = $dom->createElement( "marker" );
                $newnode = $parnode->appendChild( $node );
                $newnode->setAttribute( "name", $company_name );
                $newnode->setAttribute( "type", "fedex" );
                $newnode->setAttribute( "address", $street . ', ' . $city . ', ' . $state . ' ' . $postal_code );
                $newnode->setAttribute( "phone", $phone_number );
                $newnode->setAttribute( "lat", $coords[0] );
                $newnode->setAttribute( "lng", $coords[1] );
                $newnode->setAttribute( "distance", $distance . ' ' . $units );
                $newnode->setAttribute( "last_pickup_orange", $this->get12hoursTime( $last_pickup_orange ) );
                $newnode->setAttribute( "last_pickup_green", $this->get12hoursTime( $last_pickup_green ) );
                $newnode->setAttribute( "store_closes", $this->get12hoursTime( $store_closes ) );
                $newnode->setAttribute( "dist", $distance );
            }
        }

        ?>

        <table width="100%" border="1px">
            <thead>
                <tr>
                    <?php if($info[0]){ foreach ($info[0] as $key => $value) {
                        echo "<th>$value</th>";
                    }} ?>
                </tr>
            </thead>
            <tbody>
                <?php if($info){ foreach ($info as $key => $value) {
                        echo '<tr>';
                        if($value){ foreach ($value as $k => $val) {
                            echo "<td>$val</td>";
                        }}
                        echo '</tr>';
                    }} ?>
            </tbody>
        </table>

        <?php 

        return array('xml' => $dom->saveXML(), 'info' => $info);
	}

    private function get_coordinates($url) {
        parse_str($url, $var);
        $coords = @end(explode('|',$var['markers']));
        if(!empty($coords)) {
            $lat_longs = explode(',', $coords);
            return $lat_longs;
        }
    }

    private function get12hoursTime($time) {
        return date('h:ia', strtotime($time));
    }
}

$location = New Locations();

$location->fedex();
