/**
 * Generic handler for collapsible bbcode boxes.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2019 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @module	WoltLabSuite/Core/Bbcode/Collapsible
 */
define([], function() {
	"use strict";
	
	var _containers = elByClass('jsCollapsibleBbcode');
	
	/**
	 * @exports	WoltLabSuite/Core/Bbcode/Collapsible
	 */
	return {
		observe: function() {
			var container, toggleButtons;
			while (_containers.length) {
				container = _containers[0];
				
				// find the matching toggle button
				toggleButtons = [];
				elBySelAll('.toggleButton:not(.jsToggleButtonEnabled)', container, function (button) {
					//noinspection JSReferencingMutableVariableFromClosure
					if (button.closest('.jsCollapsibleBbcode') === container) {
						toggleButtons.push(button);
					}
				});
				
				if (toggleButtons.length > 0) {
					(function (container, toggleButtons) {
						var toggle = function (event) {
							if (container.classList.toggle('collapsed')) {
								toggleButtons.forEach(function (toggleButton) {
									toggleButton.textContent = elData(toggleButton, 'title-expand');
								});
								
								if (event instanceof Event) {
									// negative top value means the upper boundary is not within the viewport
									var top = container.getBoundingClientRect().top;
									if (top < 0) {
										var y = window.pageYOffset + (top - 100);
										if (y < 0) y = 0;
										window.scrollTo(window.pageXOffset, y);
									}
								}
							}
							else {
								toggleButtons.forEach(function (toggleButton) {
									toggleButton.textContent = elData(toggleButton, 'title-collapse');
								});
							}
						};
						
						toggleButtons.forEach(function (toggleButton) {
							toggleButton.classList.add('jsToggleButtonEnabled');
							toggleButton.addEventListener(WCF_CLICK_EVENT, toggle);
						});
						
						// expand boxes that are initially scrolled
						if (container.scrollTop !== 0) {
							toggle();
						}
						container.addEventListener('scroll', function () {
							if (container.classList.contains('collapsed')) {
								toggle();
							}
						});
					})(container, toggleButtons);
				}
				
				container.classList.remove('jsCollapsibleBbcode');
			}
		}
	};
});
