<?php

/**
 * Admin notice model.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Represents a dismissible plugin admin notice.
 */
class Model_Notice
{

	const SEVERITY_CRITICAL = 'critical';
	const SEVERITY_WARNING  = 'warning';
	const SEVERITY_INFO     = 'info';

	/**
	 * Notice identifier.
	 *
	 * @var string
	 */
	private $_id;
	/**
	 * Notice severity.
	 *
	 * @var string
	 */
	private $_severity;
	/**
	 * Notice message markup.
	 *
	 * @var string
	 */
	private $_message_html;
	/**
	 * Notice category.
	 *
	 * @var string
	 */
	private $_category;
	/**
	 * Notice action buttons.
	 *
	 * @var array
	 */
	private $_buttons;

	/**
	 * Creates a notice.
	 *
	 * @param string $id           Notice identifier.
	 * @param string $severity     Notice severity.
	 * @param string $message_html Notice message markup.
	 * @param string $category     Notice category.
	 * @param array  $buttons      Notice action buttons.
	 */
	public function __construct($id, $severity, $message_html, $category, $buttons = array())
	{
		$this->_id           = $id;
		$this->_severity     = $severity;
		$this->_message_html = $message_html;
		$this->_category     = $category;
		$this->_buttons      = $buttons;
	}

	/**
	 * Renders the notice.
	 */
	public function display_notice(): void
	{
		$severity_class = 'notice-info';
		if (self::SEVERITY_CRITICAL === $this->_severity) {
			$severity_class = 'notice-error';
		} elseif (self::SEVERITY_WARNING === $this->_severity) {
			$severity_class = 'notice-warning';
		}

		if (! preg_match('/^<p>/', $this->_message_html)) {
			$this->_message_html = '<p>' . $this->_message_html . '</p>';
		}

		echo '<div class="wfls-notice notice ' . esc_attr($severity_class) . '" data-notice-id="' . esc_attr($this->_id) . '" data-notice-type="' . esc_attr($this->_category) . '">' .
			wp_kses_post($this->_message_html) .
			'<p>' .
			wp_kses_post(implode(
				'',
				array_map(
					function (array $b): string {
						return sprintf('<a class="wfls-btn wfls-btn-default wfls-btn-sm" href="%1$s">%2$s</a>&nbsp;', esc_url($b['href']), esc_html($b['label']));
					},
					$this->_buttons
				)
			)) .
			sprintf('<a class="wfls-btn wfls-btn-default wfls-btn-sm wfls-dismiss-link" href="#" onclick="GWFLS.dismiss_notice(\'%s\'); return false;">' . __('Dismiss', '2fa-login-security') . '</a>', esc_attr($this->_id)) .
			'</p>' .
			'</div>';
	}
}
