<?php
/**
 * Functions for checking the last copy state
 */

// Include configuration data (exception thrown if file doesn't exist)
include_once $_SERVER['DOCUMENT_ROOT'] . '/lastCopyStateChecker/config/config.php';
// TODO: make variable names consistent with config file (or config keys consistent with variables)
// State abbreviation
$abb = CONFIG['state'];
// Library name
$libraryName = CONFIG['institution'];
// WorldCat API key
$apikey = CONFIG['wskey'];


/* Constants */

// Array mapping state abbreviations to their full names
define('STATES', array("AL" => "Alabama", "AK" => "Alaska", "AZ" => "Arizona", "AR" => "Arkansas",
    "CA" => "California", "CO" => "Colorado", "CT" => "Connecticut", "DE" => "Delaware", "FL" => "Florida",
    "GA" => "Georgia", "HI" => "Hawaii", "ID" => "Idaho", "IL" => "Illinois", "IN" => "Indiana", "IA" => "Iowa",
    "KS" => "Kansas", "KY" => "Kentucky", "LA" => "Louisiana", "ME" => "Maine", "MD" => "Maryland", "MA" => "Massachusetts",
    "MI" => "Michigan", "MN" => "Minnesota", "MS" => "Mississippi", "MO" => "Missouri", "MT" => "Montana", "NE" => "Nebraska",
    "NV" => "Nevada", "NH" => "New Hampshire", "NJ" => "New Jersey", "NM" => "New Mexico", "NY" => "New York", "NC" => "North Carolina",
    "ND" => "North Dakota", "OH" => "Ohio", "OK" => "Oklahoma", "OR" => "Oregon", "PA" => "Pennsylvania", "RI" => "Rhode Island",
    "SC" => "South Carolina", "SD" => "South Dakota", "TN" => "Tennessee", "TX" => "Texas", "UT" => "Utah", "VT" => "Vermont",
    "VA" => "Virginia", "WA" => "Washington", "WV" => "West Virginia", "WI" => "Wisconsin", "WY" => "Wyoming"));

// Constant for page/app title. Placeholder is filled based on selected state
define('TITLE_FORMAT_STRING', 'Last Copy in %s Checker');
// Default state name to display
define('DEFAULT_STATE', 'State');


/* Functions */

/**
 * Remove any non-numeric characters from the string to ensure the OCLC number format is valid
 * @param string $oclc The OCLC number to fix
 * @return string The fixed OCLC number
 */
function fix_oclc($oclc) {
    // Remove all non-numeric characters (e.g. whitespace, letters, punctuation, etc)
    return preg_replace("/[^0-9]/", "", $oclc);
}


/**
 * Formats the WorldCat Search API URL
 * @param string|int $oclc The OCLC number of the record
 * @return string Formatted WorldCat Search API URL (includes the API key)
 */
function format_api_url($oclc) {
    global $api_key, $abb;

    $base_url = "http://www.worldcat.org/webservices/catalog/content/libraries/$oclc";
    $get_params = "servicelevel=full&format=json&location=$abb&frbrGrouping=off&maximumLibraries=100&startLibrary=1&wskey=$api_key";
    return "$base_url?$get_params";
}


/**
 * Retrieves JSON data for library locations query using curl
 * @param string|int $oclc The OCLC number
 * @return mixed Associative array of libraries or false if query failed
 */
function get_library_locations($oclc) {
    $url = format_api_url($oclc);

    // Retrieve JSON data
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    $json = curl_exec($ch);

    // Get results as associative array
    $results = json_decode($json, true);
    // If $results is an array and has the key 'library', retrieve $results['library']
    $library_locations =
        (is_array($results) && array_key_exists('library', $results)) ?
        $results['library'] : false;
    // TODO: if above is false, get error message

    return $library_locations;
}


/**
 * Determine if item is at $library and/or elsewhere in the state
 * @param array $library_locations Results of get_library_locations()
 * @return array Results of check where
 * ['at-library'] = true if item is at this institution
 * ['in-state'] = true if item is elsewhere in the state
 * ['url'] = the URL for this institution's catalog entry or null if not found
 *
 */
function flag($library_locations) {
    global $library;

    // Initialize results array
    $results = [
        'at-library' => false,
        'in-state' => false,
        'url' => null
    ];

    // Iterate through library locations
    foreach ($library_locations as $library) {
        // TODO: handle case where $library doesn't have key 'institutionName'?
        // If it's at this library and we haven't marked it as such already
        if ($library['institutionName'] === $library && !$results['at-library']) {
            // Set $results['at-library'] to true and ['url'] to URL for item in institution's local catalog
            $results['at-library'] = true;
            $results['url'] = $library['opacUrl'];
        }
        // Else if it's at another institution in the state and we haven't marked it as such already
        else if (!$results['in-state']) {
            // Set 'in-state' flag to true
            $results['in-state'] = true;
        }

        return $results;
    }
}