<?php
namespace PwCommentsTeam\PwComments\Domain\Validator;

/***************************************************************
*  Copyright notice
*
*  (c) 2011-2014 Armin Ruediger Vieweg <armin@v.ieweg.de>
*
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * This class is a domain validator of comment model for attribute
 * comprehensive validation. It checks that at least one of the required fields
 * has been filled.
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class CommentValidator extends \TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator  {
	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 */
	protected $configurationManager = NULL;

	/**
	 * @var \PwCommentsTeam\PwComments\Utility\Settings
	 */
	protected $settingsUtility = NULL;

	/**
	 * @var array Settings defined in typoscript of pw_comments
	 */
	protected $settings = array();

	protected $validMailPattern = '
				/
					^[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*
					@
					(?:
						(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[a-z]{2}|aero|asia|biz|cat|com|edu|coop|gov|info|int|invalid|jobs|localdomain|mil|mobi|museum|name|net|org|pro|tel|travel)|
						localhost|
						(?:(?:\d{1,2}|1\d{1,2}|2[0-5][0-5])\.){3}(?:(?:\d{1,2}|1\d{1,2}|2[0-5][0-5]))
					)
					\b
				/ix';

	/**
	 * Injects the configurationManager
	 *
	 * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
	 * @return void
	 */
	public function injectConfigurationManager(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager) {
		$this->configurationManager = $configurationManager;
	}

	/**
	 * Injects the settings utility
	 *
	 * @param \PwCommentsTeam\PwComments\Utility\Settings $utility
	 * @return void
	 */
	public function injectSettingsUtility(\PwCommentsTeam\PwComments\Utility\Settings $utility) {
		$this->settingsUtility = $utility;
	}

	/**
	 * Initial function to validate
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Comment $comment Comment model to validate
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	public function isValid($comment) {
		$this->settings = $this->getExtensionSettings();

		$errorNumber = NULL;
		$errorArguments = NULL;

		if (!$this->anyPropertyIsSet($comment)) {
			$errorNumber = 1299628038;
		} elseif (!$this->mailIsValid($comment)) {
			$errorNumber = 1299628371;
		} elseif (!$this->messageIsSet($comment)) {
			$errorNumber = 1299628099;
			$errorArguments = array($this->settings['secondsBetweenTwoComments']);
		} elseif ($this->settings['useBadWordsList'] && !$this->checkTextForBadWords($comment->getMessage())) {
			$errorNumber = 1315608355;
		} elseif ($this->settings['useBadWordsListOnUsername'] && !$this->checkTextForBadWords($comment->getAuthorName())) {
			$errorNumber = 1406644911;
		} elseif ($this->settings['useBadWordsListOnMailAddress'] && !$this->checkTextForBadWords($comment->getAuthorMail())) {
			$errorNumber = 1406644912;
		} elseif (!$this->lastCommentRespectsTimer($comment)) {
			$errorNumber = 1300280476;
			$errorArguments = array($this->settings['secondsBetweenTwoComments']);
		}

		if ($errorNumber !== NULL) {
			$errorMessage = \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate(
				'tx_pwcomments.validation_error.' . $errorNumber, 'PwComments', $errorArguments
			);
			$this->addError($errorMessage, $errorNumber);
		}
		return ($errorNumber === NULL);
	}

	/**
	 * Validator to check that any property has been set in comment
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Comment $comment Comment model to validate
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	protected function anyPropertyIsSet(\PwCommentsTeam\PwComments\Domain\Model\Comment $comment) {
		return ($GLOBALS['TSFE']->fe_user->user['uid'])	|| ($comment->getAuthorName() !== '' && $comment->getAuthorMail() !== '');
	}

	/**
	 * Validator to check that mail is valid
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Comment $comment Comment model to validate
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	protected function mailIsValid(\PwCommentsTeam\PwComments\Domain\Model\Comment $comment) {
		if ($GLOBALS['TSFE']->fe_user->user['uid']) {
			return TRUE;
		}

		if (is_string($comment->getAuthorMail()) && preg_match($this->validMailPattern, $comment->getAuthorMail())) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Validator to check that message has been set
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Comment $comment Comment model to validate
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	protected function messageIsSet(\PwCommentsTeam\PwComments\Domain\Model\Comment $comment) {
		return (trim($comment->getMessage()));
	}

	/**
	 * Check the time between last two comments of current user (using its session)
	 *
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	protected function lastCommentRespectsTimer() {
		if (!$GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_lastComment')) {
			return TRUE;
		}

		$difference = intval(time() - $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_lastComment'));

		if ($difference > $this->settings['secondsBetweenTwoComments']) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Check for badwords in comment message
	 *
	 * @param string $textToCheck text to check for
	 * @return boolean Returns TRUE if message has no badwords. Otherwise returns FALSE.
	 */
	protected function checkTextForBadWords($textToCheck) {
		if (empty($textToCheck)) {
			return TRUE;
		}

		$badWordsListPath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($this->settings['badWordsList']);

		if (!file_exists($badWordsListPath)) {
			// Skip this validation, if bad word list is missing
			return TRUE;
		}

		$badWordsRegExp = '';
		foreach (file($badWordsListPath) as $badWord) {
			$badWordsRegExp .= trim($badWord) . '|';
		}
		$badWordsRegExp = '/' . substr($badWordsRegExp, 0, -1) . '/i';

		$commentMessage = '-> ' . $textToCheck . ' <-';
		return (boolean)!preg_match($badWordsRegExp, $commentMessage);
	}


	/**
	 * Returns the rendered settings of this extension
	 *
	 * @return array rendered typoscript settings
	 */
	protected function getExtensionSettings() {
		$fullTyposcript = $this->configurationManager->getConfiguration(
			\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
		);
		$extensionTyposcript = $fullTyposcript['plugin.']['tx_pwcomments.']['settings.'];
		return $this->settingsUtility->renderConfigurationArray($extensionTyposcript);
	}
}