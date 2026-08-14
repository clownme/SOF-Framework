/**
 * COAI region mapper.
 * Input:  $state (US state or CA province/territory code OR full name), $country (US/CA/…)
 * Output: Region string (e.g., "Northeast", "Midwest", "South", "West", "Canada", "International") or null
 */
function coai_region_from_location($state, $country) {
    $country = strtoupper(trim((string)$country));
    $state   = strtoupper(trim((string)$state));

    // Normalize punctuation / spacing
    $country = str_replace(['.', ','], '', $country);
    $state   = str_replace(['.', ','], '', $state);
    $country = preg_replace('/\s+/', ' ', $country);
    $state   = preg_replace('/\s+/', ' ', $state);

    // Normalize common country values
    $country_map = [
        'USA' => 'US',
        'UNITED STATES' => 'US',
        'UNITED STATES OF AMERICA' => 'US',
        'US' => 'US',
        'U S' => 'US',

        'CANADA' => 'CA',
        'CA' => 'CA',

        'MEXICO' => 'MX',
        'MX' => 'MX',

        'PUERTO RICO' => 'PR',
    ];
    if (isset($country_map[$country])) {
        $country = $country_map[$country];
    }

// -------------------------------------------------
// Fallback: country stored in state field
// -------------------------------------------------
if ($country === '') {
    $state_as_country = [
        'PR',
        'DOMINICAN REPUBLIC',
        'GERMANY',
        'TAIWAN',
        'TAIWAN ROC',
        'HONG KONG',
        'MEXICO',
        'WEST INDIES',
        'THE BAHAMAS',
        'BAHAMAS',
        'AUSTRALIA',
        'QUEENSLAND',
        'AUSTRALIAN CAPITAL TERRITORY',
        'VICTORIA',
        'VIC',
        'ASIA',
        'CANADA'
    ];

    if (in_array($state, $state_as_country, true)) {
        if ($state === 'CANADA') {
            return 'Canada';
        }
        return 'International';
    }
}

    // Basic normalization for common full names
    static $us_name_to_code = [
        'ALABAMA'=>'AL','ALASKA'=>'AK','ARIZONA'=>'AZ','ARKANSAS'=>'AR','CALIFORNIA'=>'CA',
        'COLORADO'=>'CO','CONNECTICUT'=>'CT','DELAWARE'=>'DE','FLORIDA'=>'FL','GEORGIA'=>'GA',
        'HAWAII'=>'HI','IDAHO'=>'ID','ILLINOIS'=>'IL','INDIANA'=>'IN','IOWA'=>'IA','KANSAS'=>'KS',
        'KENTUCKY'=>'KY','LOUISIANA'=>'LA','MAINE'=>'ME','MARYLAND'=>'MD','MASSACHUSETTS'=>'MA',
        'MICHIGAN'=>'MI','MINNESOTA'=>'MN','MISSISSIPPI'=>'MS','MISSOURI'=>'MO','MONTANA'=>'MT',
        'NEBRASKA'=>'NE','NEVADA'=>'NV','NEW HAMPSHIRE'=>'NH','NEW JERSEY'=>'NJ','NEW MEXICO'=>'NM',
        'NEW YORK'=>'NY','NORTH CAROLINA'=>'NC','NORTH DAKOTA'=>'ND','OHIO'=>'OH','OKLAHOMA'=>'OK',
        'OREGON'=>'OR','PENNSYLVANIA'=>'PA','RHODE ISLAND'=>'RI','SOUTH CAROLINA'=>'SC',
        'SOUTH DAKOTA'=>'SD','TENNESSEE'=>'TN','TEXAS'=>'TX','UTAH'=>'UT','VERMONT'=>'VT',
        'VIRGINIA'=>'VA','WASHINGTON'=>'WA','WEST VIRGINIA'=>'WV','WISCONSIN'=>'WI','WYOMING'=>'WY',
        'DISTRICT OF COLUMBIA'=>'DC','WASHINGTON DC'=>'DC','D C'=>'DC','DC'=>'DC',

        // Territories
        'PUERTO RICO'=>'PR','GUAM'=>'GU','NORTHERN MARIANA ISLANDS'=>'MP',
        'US VIRGIN ISLANDS'=>'VI','U S VIRGIN ISLANDS'=>'VI','VIRGIN ISLANDS'=>'VI','AMERICAN SAMOA'=>'AS'
    ];

    static $ca_name_to_code = [
        'ALBERTA'=>'AB','BRITISH COLUMBIA'=>'BC','MANITOBA'=>'MB','NEW BRUNSWICK'=>'NB',
        'NEWFOUNDLAND AND LABRADOR'=>'NL','NEWFOUNDLAND'=>'NL','NOVA SCOTIA'=>'NS','ONTARIO'=>'ON',
        'PRINCE EDWARD ISLAND'=>'PE','QUEBEC'=>'QC','SASKATCHEWAN'=>'SK',
        'NORTHWEST TERRITORIES'=>'NT','NUNAVUT'=>'NU','YUKON'=>'YT'
    ];

    // If country is blank, infer when possible from state value
    if ($country === '') {
        if (isset($us_name_to_code[$state]) || preg_match('/^(AL|AK|AZ|AR|CA|CO|CT|DE|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY|DC|PR|GU|MP|VI|AS)$/', $state)) {
            $country = 'US';
        } elseif (isset($ca_name_to_code[$state]) || preg_match('/^(AB|BC|MB|NB|NL|NS|ON|PE|QC|SK|NT|NU|YT)$/', $state)) {
            $country = 'CA';
        }
    }

    // Convert full names -> codes if needed
    if ($country === 'US' && isset($us_name_to_code[$state])) {
        $state = $us_name_to_code[$state];
    } elseif ($country === 'CA' && isset($ca_name_to_code[$state])) {
        $state = $ca_name_to_code[$state];
    }

    // Canada bucket
    if ($country === 'CA') {
        return 'Canada';
    }

    // Non US/CA = International
    if ($country && $country !== 'US') {
        return 'International';
    }

    // US regions
    $northeast = ['CT','ME','MA','NH','RI','VT','NJ','NY','PA'];
    $midwest   = ['IL','IN','MI','OH','WI','IA','KS','MN','MO','NE','ND','SD'];
    $south     = ['DE','DC','FL','GA','MD','NC','SC','VA','WV','AL','KY','MS','TN','AR','LA','OK','TX'];
    $west      = ['AZ','CO','ID','MT','NV','NM','UT','WY','AK','CA','HI','OR','WA'];

    if (in_array($state, $northeast, true)) return 'Northeast';
    if (in_array($state, $midwest, true))   return 'Midwest';
    if (in_array($state, $south, true))     return 'South';
    if (in_array($state, $west, true))      return 'West';

    // Territories default
    if (in_array($state, ['PR','GU','MP','VI','AS'], true)) return 'International';

    return null;
}