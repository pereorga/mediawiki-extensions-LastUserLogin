<?php

namespace MediaWiki\Extension\LastUserLogin;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SpecialPage;
use UserBlockedError;

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

class SpecialLastUserLogin extends SpecialPage {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LastUserLogin', 'lastlogin' );
	}

	/**
	 * Show the special page
	 *
	 * @param mixed $parameter Parameter passed to the page or null
	 */
	public function execute( $parameter ) {
		$user = $this->getUser();
		$request = $this->getRequest();
		$output = $this->getOutput();
		$lang = $this->getLanguage();

		if ( $user->getBlock() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		if ( !$this->userCanExecute( $user ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->setHeaders();

		$fields = [
			'user_name' => 'lastuserlogin-userid',
			'user_touched' => 'lastuserlogin-lastlogin',
		];

		// Get order_by and validate it
		$orderby = $request->getVal( 'order_by', 'user_touched' );
		if ( !isset( $fields[ $orderby ] ) ) {
			$orderby = 'user_touched';
		}

		// Get order_type and validate it
		$ordertype = $request->getVal( 'order_type', 'DESC' );
		if ( $ordertype !== 'ASC' ) {
			$ordertype = 'DESC';
		}

		// Get ALL users, paginated
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$conds = [ 'user_is_temp' => 0 ];
		$conds[] = 'user_name != ' . $dbr->addQuotes( 'Maintenance script' );
		$conds[] = 'user_name != ' . $dbr->addQuotes( 'MediaWiki default' );
		$result = $dbr->select(
			'user', array_keys( $fields ), $conds, __METHOD__, [ 'ORDER BY' => $orderby . ' ' . $ordertype ]
		);
		if ( $result === false ) {
			$output->addHTML( '<p>' . $this->msg( 'lastuserlogin-nousers' )->text() . '</p>' );
			return;
		}

		// Build the table
		$out = '<table class="wikitable sortable">';

		// Build the table header
		$title = $this->getPageTitle();
		$out .= '<tr>';
		// Invert the order.
		$ordertype = ( $ordertype == 'ASC' ) ? 'DESC' : 'ASC';
		$linkRenderer = $this->getLinkRenderer();
		foreach ( $fields as $key => $value ) {
			$attrs = [ 'order_by' => $key, 'order_type' => $ordertype ];
			$link = $linkRenderer->makeLink( $title, $this->msg( $value )->text(), [], $attrs );
			$out .= '<th>' . $link . '</th>';
		}
		$out .= '<th>' . $this->msg( 'lastuserlogin-daysago' )->text() . '</th>';
		$out .= '</tr>';

		// Build the table rows
		foreach ( $result as $row ) {
			$out .= '<tr>';
			foreach ( $fields as $key => $value ) {
				if ( $key === 'user_touched' ) {
					$lastLogin = $lang->timeanddate( wfTimestamp( TS_MW, $row->$key ), true );
					$secondsAgo = time() - wfTimestamp( TS_UNIX, $row->$key );
					if ( $secondsAgo >= 86400 ) {
						$daysAgo = $lang->formatNum( round( $secondsAgo / 86400, 2 ) ) . ' d';
					} elseif ( $secondsAgo >= 3600 ) {
						$hours = (int)floor( $secondsAgo / 3600 );
						$minutes = (int)floor( ( $secondsAgo % 3600 ) / 60 );
						$daysAgo = $lang->formatNum( $hours ) . ' h ' . $lang->formatNum( $minutes ) . ' min';
					} else {
						$minutes = max( 1, (int)floor( $secondsAgo / 60 ) );
						$daysAgo = $lang->formatNum( $minutes ) . ' min';
					}
					$out .= '<td>' . $lastLogin . '</td>';
					$out .= '<td style="text-align: right;">' . $daysAgo . '</td>';
				} elseif ( $key === 'user_name' ) {
					$userPage = Title::makeTitle( NS_USER, $row->$key );
					$userName = $linkRenderer->makeLink( $userPage, $userPage->getText() );
					$out .= '<td>' . $userName . '</td>';
				} else {
					$out .= '<td>' . htmlspecialchars( $row->$key ) . '</td>';
				}
			}
			$out .= '</tr>';
		}

		$out .= '</table>';
		$output->addHTML( $out );
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}
}
