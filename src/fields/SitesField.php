<?php
/**
 * Section Field plugin for Craft 3.0
 * @copyright Copyright East Slope Studio, LLC
 */

namespace eastslopestudio\sitesfield\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\validators\ArrayValidator;

use yii\base\InvalidConfigException;
use yii\db\Schema;

/**
 * This field allows a selection from a configured set of sites.
 */
class SitesField extends Field implements PreviewableFieldInterface
{

	/**
	 * @var bool Whether or not the field allows multiple selections.
	 */
	public $allowMultiple = false;

	/**
	 * @var array What sites have been whitelisted as selectable for this field.
	 */
	public $whitelistedSites = [];

	/**
	 * @inheritdoc
	 * @see craft\base\ComponentInterface
	 */
	public static function displayName(): string
	{
		return \Craft::t('sites-field', 'Sites');
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public static function hasContentColumn(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function __construct($config = [])
	{
		if (!array_key_exists('whitelistedSites', $config)
			|| ($config['whitelistedSites'] != '*' && !is_array($config['whitelistedSites']))) {
			$config['whitelistedSites'] = [];
		}

		parent::__construct($config);
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public function getContentColumnType(): string
	{
		return Schema::TYPE_STRING;
	}

	/**
	 * @inheritdoc
	 * @see craft\base\SavableComponentInterface
	 */
	public function getSettingsHtml(): string
	{
		return Craft::$app->getView()->renderTemplate(
			'sites-field/_settings',
			[
				'field' => $this,
				'sites' => $this->getSites()
			]
		);
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public function rules(): array
	{
		$rules = parent::rules();
		$rules[] = [['whitelistedSites'], 'validateSitesWhitelist', 'skipOnEmpty' => false];

		return $rules;
	}

	/**
	 * Ensures the site IDs selected for the whitelist are for valid sites.
	 * @param string $attribute The name of the attribute being validated.
	 * @return void
	 */
	public function validateSitesWhitelist(string $attribute)
	{
		if ($this->whitelistedSites == '*') {
			return;
		}

		$av = new ArrayValidator(['min' => 1, 'skipOnEmpty' => false]);
		if (!$av->validate($this->whitelistedSites, $error)) {
			$this->addError($attribute, Craft::t('sites-field', $error));
		}

		$sites = $this->getSites();

		foreach ($this->whitelistedSites as $site) {
			if (!isset($sites[$site])) {
				$this->addError($attribute, Craft::t('sites-field', 'Invalid site selected.'));
			}
		}
	}

	/**
	 * @inheritdoc
	 * @throws InvalidConfigException
	 * @see craft\base\Field
	 */
	public function getInputHtml($value, ElementInterface $element = null): string
	{
		if (empty($this->whitelistedSites)) {
			throw new InvalidConfigException('At least one whitelisted site is required.');
		}

		$sites = $this->getSites(); // Get all sites available to the current user.
		if ($this->whitelistedSites == '*') {
			$whitelist = array_keys($sites);
		} else {
			$whitelist = $this->whitelistedSites; // Get all whitelisted sites.
		}
		$whitelist[''] = ''; // Add a blank entry in, in case the field's options allow a 'None' selection.
		$whitelist = array_flip($whitelist);
		if (!$this->allowMultiple && !$this->required) { // Add a 'None' option specifically for optional, single value fields.
			$sites = ['' => Craft::t('app', 'None')] + $sites;
		}
		$whitelist = array_intersect_key($sites, $whitelist); // Discard any sites not available within the whitelist.

		return Craft::$app->getView()->renderTemplate(
			'sites-field/_input', [
				'field' => $this,
				'value' => $value,
				'sites' => $whitelist,
			]
		);
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public function getElementValidationRules(): array
	{
		return [
			['validateSites'],
		];
	}

	/**
	 * Ensures the site IDs selected are available to the current user.
	 * @param ElementInterface $element The element with the value being validated.
	 * @return void
	 */
	public function validateSites(ElementInterface $element)
	{
		$value = $element->getFieldValue($this->handle);
		$sites = $this->getSites();

		if (is_array($value)) {
			foreach ($value as $id) {
				if (!isset($sites[$id])) {
					$element->addError($this->handle, Craft::t('sites-field', 'Invalid site selected.'));
				}
			}
		} else {
			if (!isset($sites[$value])) {
				$element->addError($this->handle, Craft::t('sites-field', 'Invalid site selected.'));
			}
		}

	}

	/**
	 * Retrieves all sites in an id, name pair, suitable for the underlying options display.
	 */
	private function getSites()
	{
		$sites = [];
		foreach (Craft::$app->getSites()->getAllSites() as $site) {
			$sites[$site->uid] = Craft::t('site', $site->name);
		}
		return $sites;
	}

	/**
	 * @inheritdoc
	 */
	public function normalizeValue($value, ElementInterface $element = null)
	{
        if($this->allowMultiple && is_string($value)) {
            $value = json_decode($value);
        }
        return $value;
	}

}
