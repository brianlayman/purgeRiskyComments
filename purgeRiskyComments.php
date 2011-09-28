#!/usr/bin/php -q
<?php
/*
Script Name: Purge Risky Comment
Description: This script uses WordPress to iterate comments and delete them if they contain words deemed by advertisers to be high risk.
Version: 0.1
Author: BrianLayman
Author URI: http://webdevstudios.com/team/brian-layman/
Script URI: http://webdevstudios.com/wordpress/vip-services-support/

Notes: 
	This script works for single site WordPress or the main site for the active network in a multisite install. It does not iterate sites or networks.
	This script can be called as a web page or from the CLI. Progress is logged to the screen and the error_log (with the prefix purgeRiskyComments for easy greping)
	From the CLI, if an argument is passed, it will function as the offset for starting the comment search. Non-integers are ignored.
	From the web if you pass the parameter, the same feature can be achieved with the ?offset= parameter

Use: Place this script in the root of your WordPress install and execute from CLI or web navigation

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

// Comment out this line to allow the script to alter the database
// Otherwise it just displays messages and/or records them to the PHP error_log
define( 'DRY_RUN', true );

// Integrate with the WordPress environment
require( dirname(__FILE__) . '/wp-load.php' );

// Create a list of words defined by the advertiser
// This scope is global to all functions herein
$naughtWords = array();
if ( file_exists( 'naughtywords.php' ) ) include( 'naughtywords.php' );

// The files in the naughty words list should match what is supplied by your advertiser.
// Rather than causing Google to associate my name with all 400 words on my list, I'll let you build your own list.
// The list should be formatted like this:
// $naughtWords[] = 'adult film star'; // Phrases are fine
// $naughtWords[] = 'alcohol'; // Partial matches do count and alcohol would also catch alcoholic.
// $naughtWords[] = ' ass '; // Note the spaces before and after. 'ass ' would catch 'class' and ' ass' would catch assistant 'ass' would catch both.
// $naughtWords[] = 'asswipe'; // This must be here because of the spaces in the previous line.

// A version of strpos() that accepts an array for as a needle
function strpos_arr($haystack, $needles) { 
	// Allow the function to be called with a string/int needle as the default strpos allows
    if( !is_array($needles) ) $needles = array($needles); 
    
	// Iterate the array calling repeated strpos, returning upon a hit
	foreach ($needles as $needle) { 
        if ( ( $pos = strpos( $haystack, $needle ) ) !== false ) {
			if ( defined( 'DRY_RUN' ) ) { 
				echo 'Found the word <strong>' . $needle . '</strong>';
			}
			return $pos; 
		}
    } 
	// Default to the false boolean for not found.
    return false; 
} 


// A simple function that returns true if an instance of any naughty word is found
function is_naughty( $commentText ) {
	// Return true if anything other than the boolean value false is returned
	// This prevents position 0 being mistaken for a false.
	global $naughtWords;
	return ( strpos_arr( strtolower( $commentText ), $naughtWords ) !== false );
}

// Iterate all comments for the current blog and delete those that are naughty.
function purge_risky_comments() {
	// Perform this process in batches of 100
    $per_page = 100;
	
	// By default start with comment 0
	// However also look for an offset passed via the CLI or the an url parameter named offset
	$offset = 0;
	global $argv;
	if ( isset( $argv[1] ) ) { $offset = (int) $argv[1]; }
	elseif ( isset( $_GET['offset'] ) ) $offset = (int) $_GET['offset'];

	// Prepare the arguments for the get_comments call
    $args = array(
        'number' => $per_page,
        'offset' => $offset,
    );

    $hits = 0;
	$deletionIDs = array();
	
	// Loop in batches of $per_page till all comments are processed.
    while ( $comments = get_comments( $args ) ) {
        foreach ( $comments as $comment ) {
            if ( is_naughty( $comment->comment_content ) ) {
				$hits++;
				if ( defined( 'DRY_RUN' ) ) {
					echo ' in comment ' . $comment->comment_ID . '<br /><br />' . $comment->comment_content . '<hr />' . PHP_EOL;
					error_log( 'Would have deleted ' . $comment->comment_ID );
				} else {
	                $deletionIDs[] = $comment->comment_ID;
				}
            }
        }
		
		// Move onto the next set
        $args['offset'] += $per_page;
		echo  $args['offset'] . " comments checked so far <br />" . PHP_EOL;
		error_log( "purgeRiskyComments: " . $args['offset'] . " comments checked so far" );        
    }
	
	// The deletions must be performed outside of the while get_comments() loop, otherwise
	// each deletion causes the next comment to be skipped and not checked.
	$deletes = 0;
	// deletionIDs will never be populated in a dry run, but check the constant anyway.
	if ( !defined( 'DRY_RUN' ) ) {	
		foreach ($deletionIDs as $riskyComment) {			
			wp_delete_comment( $riskyComment );
			$deletes++;
		}
	}
	
	// Run complete. Output Stats
	echo  $hits . " risky comments found <br />" . PHP_EOL;
	echo  $deletes . " risky comments deleted <br />" . PHP_EOL;
	error_log( "purgeRiskyComments: " . $hits . " risky comments found" );        
	error_log( "purgeRiskyComments: " . $deletes . " risky comments deleted" );        
}

purge_risky_comments();