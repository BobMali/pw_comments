<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'PwCommentsTeam.' . $_EXTKEY,
	'Pi1',
	array(
		'Comment' => 'index,new,create,upvote,downvote',
	),
	array(
		'Comment' => 'index,new,create,upvote,downvote',
	)
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'PwCommentsTeam.' . $_EXTKEY,
	'Pi2',
	array(
		'Comment' => 'sendAuthorMailWhenCommentHasBeenApproved',
	),
	array(
		'Comment' => 'sendAuthorMailWhenCommentHasBeenApproved',
	)
);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PwComments']['modules'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PwComments']['plugins'];

	// After save hook
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
	'PwCommentsTeam\PwComments\Hooks\ProcessDatamap';