<?php

namespace MediaWiki\Extension\LastUserLogin;

use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\MediaWikiServices;

class Hooks implements BeforeInitializeHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		if ( !$user->isNamed() || $request->wasPosted() ) {
			return;
		}

		$userOptionsManager = MediaWikiServices::getInstance()->get( 'UserOptionsManager' );

		$userOptionsManager->setOption( $user, 'lastuserlogin-lastseen', wfTimestampNow() );
		$userOptionsManager->saveOptions( $user );
	}
}
