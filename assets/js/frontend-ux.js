/*!
 * Commerce UX Studio - Frontend Interactive Controller (v2.3.2 Universal Configurable Edition)
 *
 * File Path: assets/js/frontend-ux.js
 * Version:   2.3.2
 * Author:    Fareed M. Rifaideen
 * Website:   https://fareed-rifaideen.netlify.app/
 * LinkedIn:  https://www.linkedin.com/in/fareed03/
 * GitHub:    https://github.com/fareed-rifaideen-ecom
 *
 * Powers:
 * 1. Shop & Category Card Swatch Image Swap + Hover Back-Image Sync + Deep-Link URL Updater
 * 2. Style C Catalog Quick-Add Size Buttons (AJAX Add to Cart + Mini-Cart Fragment Refresh)
 * 3. Single Product Swatches (Option 4A Checkmark Badge & Option 4B Border + Dot Contrast States)
 * 4. Flatsome Flickity Slider Bridge (Strict Single-Variation Gallery Isolation + Counter Pill)
 * 5. Multi-Layout Delivery Switcher (Option 6A Stacked Rows, 6B Segmented Tabs, 6C 2-Col Cards) + WC Session Sync
 * 6. Smart Size Guide Lightbox Modal ([block id="..."] UX Block Popup)
 * 7. In-Page "Complete Your Ride" Cross-Sell Configurator & Floating Product Comparison Modal
 */
(function ($) {
	'use strict';

	$(function () {
		const rawCfg = window.cuxFrontendVars || window.cuxFrontendConfig || {};
		const nestedI18n = rawCfg.i18n || {};

		const i18n = {
			ajaxUrl: rawCfg.ajaxUrl || '/wp-admin/admin-ajax.php',
			nonce: rawCfg.nonce || '',
			i18nChooseOpts: rawCfg.i18nChooseOpts || nestedI18n.chooseOptions || 'Choose Options',
			i18nAddToCart: rawCfg.i18nAddToCart || nestedI18n.addToCart || 'Add to Cart',
			i18nOutOfStock: rawCfg.i18nOutOfStock || nestedI18n.outOfStock || 'Out of Stock',
			i18nAdded: rawCfg.i18nAdded || nestedI18n.added || 'Added to Cart',
			i18nLowStock: rawCfg.i18nLowStock || nestedI18n.lowStock || 'Low Stock',
			i18nInStock: rawCfg.i18nInStock || nestedI18n.inStock || 'In Stock',
			i18nUnavailable: rawCfg.i18nUnavailable || 'Unavailable in selected option'
		};

		/* ======================================================================
		   1. SHOP & CATEGORY CARD SWATCH CLICK -> SWAP IMAGE, HOVER & DEEP LINK
		   ====================================================================== */
		$(document).on('click', '.cux-card-swatch-dot', function (e) {
			e.preventDefault();
			e.stopPropagation();

			const $dot = $(this);
			const $card = $dot.closest('.product-small, .product');
			const imgSrc = $dot.data('img-src');
			const hoverSrc = $dot.data('hover-src');
			const deepLink = $dot.data('deep-link');
			const colorSlug = $dot.data('color-slug');

			$dot.closest('.cux-card-swatches').find('.cux-card-swatch-dot').removeClass('is-active');
			$dot.addClass('is-active');

			if (imgSrc) {
				const $mainImg = $card.find('.box-image img:not(.back-image)').first();
				if ($mainImg.length) {
					$mainImg.attr('src', imgSrc).removeAttr('srcset').removeAttr('sizes');
				}
			}

			if (hoverSrc) {
				const $hoverImg = $card.find('.box-image img.back-image, .box-image .show-on-hover img').first();
				if ($hoverImg.length) {
					$hoverImg.attr('src', hoverSrc).removeAttr('srcset').removeAttr('sizes');
				}
			}

			const baseUrl = $card.find('[data-base-url]').first().data('base-url');
			if (baseUrl && deepLink) {
				const cleanBase = String(baseUrl).split('?')[0];
				const targetUrl = cleanBase + deepLink;
				$card.find('.box-image a, .product-title a, a.woocommerce-LoopProduct-link').attr('href', targetUrl);
			}

			// Filter Style C Quick-Add size pills to match the clicked color if present.
			const $sizePills = $card.find('.cux-zara-size-pill');
			if ($sizePills.length && colorSlug) {
				$sizePills.each(function () {
					const pillColor = $(this).data('color-slug');
					if (!pillColor || pillColor === colorSlug) {
						$(this).show();
					} else {
						$(this).hide();
					}
				});
			}
		});

		/* ======================================================================
		   2. STYLE C CATALOG QUICK-ADD SIZE PILL CLICK -> AJAX ADD TO CART
		   ====================================================================== */
		$(document).on('click', '.cux-zara-size-pill', function (e) {
			e.preventDefault();
			e.stopPropagation();

			const $pill = $(this);
			const productId = $pill.data('product-id');
			const variationId = $pill.data('variation-id');
			const origLabel = $pill.text();

			if (!productId || !variationId || $pill.prop('disabled')) {
				return;
			}

			$pill.prop('disabled', true).text('...');

			$.post(i18n.ajaxUrl, {
				action: 'cux_quick_add_to_cart',
				nonce: i18n.nonce,
				product_id: productId,
				variation_id: variationId,
				quantity: 1
			}).done(function (res) {
				$pill.text('✓');
				if (res && res.fragments) {
					$.each(res.fragments, function (key, value) {
						$(key).replaceWith(value);
					});
					$(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, $pill]);
				} else {
					$(document.body).trigger('wc_fragment_refresh');
				}
				setTimeout(function () {
					$pill.prop('disabled', false).text(origLabel);
				}, 1400);
			}).fail(function () {
				$pill.prop('disabled', false).text(origLabel);
			});
		});

		/* ======================================================================
		   3. SINGLE PRODUCT PAGE (PDP): SWATCHES, CROSS-ATTRIBUTE AVAILABILITY
		      & STRICT VARIATION GALLERY ISOLATION
		   ====================================================================== */
		const $jsonNode = $('#cux-pdp-json-data, #cux-pdp-data').first();
		let pdpData = null;
		let activeGallerySignature = '';

		if ($jsonNode.length) {
			try {
				pdpData = JSON.parse($jsonNode.text());
			} catch (err) {
				pdpData = null;
			}
		}

		function updateGalleryCounter(currentIndex, totalSlides) {
			const $gallery = $('.product-gallery-slider').first();
			const $thumbs = $('.product-thumbnails').first();

			if ($thumbs.length) {
				if (totalSlides <= 1) {
					$thumbs.addClass('cux-single-image-hidden');
				} else {
					$thumbs.removeClass('cux-single-image-hidden');
				}
			}

			if (!$gallery.length || totalSlides <= 1) {
				$('.cux-gallery-counter-pill').remove();
				return;
			}
			let $counter = $gallery.parent().find('.cux-gallery-counter-pill');
			if (!$counter.length) {
				$counter = $('<div class="cux-gallery-counter-pill"></div>');
				$gallery.parent().css('position', 'relative').append($counter);
			}
			$counter.text((currentIndex + 1) + ' / ' + totalSlides);
		}

		const $mainSlider = $('.product-gallery-slider').first();
		if ($mainSlider.length) {
			const totalInitial = $mainSlider.find('.woocommerce-product-gallery__image, .slide').length;
			updateGalleryCounter(0, totalInitial);
			$mainSlider.on('change.flickity', function (event, index) {
				const count = $mainSlider.find('.woocommerce-product-gallery__image, .slide').length;
				updateGalleryCounter(index, count);
			});
		}

		/**
		 * Return map of currently selected normalized attribute slugs:
		 * e.g. { attribute_pa_size: 's2', attribute_pa_colour: 'black-liquid-metal' }
		 */
		function getCurrentSelectedAttributes() {
			const selectedMap = {};
			$('.cux-swatch-control-wrap').each(function () {
				const attrKey = String($(this).data('attribute') || '').toLowerCase();
				const $sel = $(this).find('.cux-swatch-item.is-selected').first();
				if (attrKey && $sel.length) {
					const slug = String($sel.data('slug') || $sel.data('value') || '');
					if (slug !== '') {
						selectedMap[attrKey] = slug;
					}
				}
			});
			return selectedMap;
		}

		/**
		 * Check if a variation record matches a set of attribute key => slug pairs.
		 */
		function variationMatchesAttributes(varData, testAttrs) {
			if (!varData) {
				return false;
			}
			const norm = varData.norm_attrs || {};
			const raw = varData.attributes || {};

			for (const reqKey in testAttrs) {
				if (!Object.prototype.hasOwnProperty.call(testAttrs, reqKey)) {
					continue;
				}
				const reqSlug = String(testAttrs[reqKey] || '');
				if (!reqSlug) {
					continue;
				}

				const reqNoAttr = reqKey.replace(/^attribute_/, '');
				const reqNoPa = reqNoAttr.replace(/^pa_/, '');
				let matchedKey = false;
				let varValSlug = '';

				if (Object.prototype.hasOwnProperty.call(norm, reqKey)) {
					matchedKey = true;
					varValSlug = String(norm[reqKey] || '');
				} else {
					for (const k in raw) {
						if (!Object.prototype.hasOwnProperty.call(raw, k)) {
							continue;
						}
						const kLow = String(k).toLowerCase().replace(/^attribute_/, '');
						const kNoPa = kLow.replace(/^pa_/, '');
						if (kLow === reqNoAttr || kNoPa === reqNoPa) {
							matchedKey = true;
							varValSlug = String(raw[k] || '').toLowerCase();
							break;
						}
					}
				}

				if (!matchedKey) {
					return false;
				}
				// Empty string '' on a WooCommerce variation attribute means "Any Size / Any Colour" wildcard.
				if (varValSlug !== '' && varValSlug !== reqSlug) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Evaluate every attribute button against currently selected attributes in OTHER rows
		 * so selecting a Size immediately crosses out unavailable Colours (and vice versa).
		 */
		function evaluateCrossAttributeAvailability() {
			if (!pdpData || !pdpData.availability_matrix) {
				return;
			}

			const matrix = pdpData.availability_matrix;
			const currentSelections = getCurrentSelectedAttributes();

			$('.cux-swatch-control-wrap').each(function () {
				const $wrap = $(this);
				const targetAttrKey = String($wrap.data('attribute') || '').toLowerCase();
				const crossMode = $wrap.data('cross-oos-mode') || pdpData.cross_attr_oos_mode || 'smart_switch';

				// Build filter from all OTHER selected attributes.
				const otherSelections = {};
				for (const k in currentSelections) {
					if (Object.prototype.hasOwnProperty.call(currentSelections, k) && k !== targetAttrKey) {
						otherSelections[k] = currentSelections[k];
					}
				}

				const hasOtherSelections = Object.keys(otherSelections).length > 0;

				$wrap.find('.cux-swatch-item').each(function () {
					const $btn = $(this);
					const candidateSlug = String($btn.data('slug') || $btn.data('value') || '');
					const baseName = String($btn.data('name') || candidateSlug);

					if (!hasOtherSelections) {
						$btn.removeClass('is-oos-in-combo is-cross-locked').attr('title', baseName);
						return;
					}

					const comboTest = Object.assign({}, otherSelections);
					comboTest[targetAttrKey] = candidateSlug;

					let hasStockInCombo = false;
					for (const varId in matrix) {
						if (!Object.prototype.hasOwnProperty.call(matrix, varId)) {
							continue;
						}
						const vData = matrix[varId];
						if (variationMatchesAttributes(vData, comboTest) && vData.is_in_stock) {
							hasStockInCombo = true;
							break;
						}
					}

					if (!hasStockInCombo) {
						$btn.addClass('is-oos-in-combo').attr('title', baseName + ' (' + i18n.i18nUnavailable + ')');
						if (crossMode === 'strict_lock') {
							$btn.addClass('is-cross-locked');
						} else {
							$btn.removeClass('is-cross-locked');
						}
					} else {
						$btn.removeClass('is-oos-in-combo is-cross-locked').attr('title', baseName);
					}
				});
			});
		}

		/**
		 * Find the first in-stock variation ID that matches the given attribute key => slug map.
		 */
		function findFirstMatchingVariation(testAttrs, requireInStock) {
			if (!pdpData || !pdpData.availability_matrix) {
				return null;
			}
			const matrix = pdpData.availability_matrix;
			for (const varId in matrix) {
				if (!Object.prototype.hasOwnProperty.call(matrix, varId)) {
					continue;
				}
				const vData = matrix[varId];
				if (requireInStock && !vData.is_in_stock) {
					continue;
				}
				if (variationMatchesAttributes(vData, testAttrs)) {
					return vData;
				}
			}
			return null;
		}

		function computeSelectedVisualHash() {
			if (!pdpData) {
				return '';
			}
			const parts = [];
			$('.cux-swatch-control-wrap').each(function () {
				const role = $(this).data('role');
				if (role === 'visual_primary' || role === 'visual_secondary') {
					const $sel = $(this).find('.cux-swatch-item.is-selected');
					const slug = $sel.data('slug') || $sel.data('value');
					if ($sel.length && slug) {
						parts.push(slug);
					}
				}
			});
			return parts.join('__');
		}

		/**
		 * Replace the Flatsome Flickity main slider & thumbnail rail with the given slide list.
		 * In Strict Variation Mode, shows ONLY the selected variation's image(s).
		 */
		function renderFlatsomeGallerySlides(slides) {
			if (!slides || !slides.length) {
				return;
			}

			const signature = slides.map(function (s) {
				return s.id;
			}).join(',');

			if (signature === activeGallerySignature) {
				updateGalleryCounter(0, slides.length);
				return;
			}
			activeGallerySignature = signature;

			const $slider = $('.product-gallery-slider').first();
			const $thumbs = $('.product-thumbnails').first();
			if (!$slider.length) {
				return;
			}

			const flktyMain = $slider.data('flickity');
			const flktyThumbs = $thumbs.length ? $thumbs.data('flickity') : null;

			const newMainEls = [];
			const newThumbEls = [];

			slides.forEach(function (slide, idx) {
				const slideHtml =
					'<div data-thumb="' + slide.full_url + '" class="woocommerce-product-gallery__image slide ' + (idx === 0 ? 'first' : '') + '">' +
					'<a href="' + slide.full_url + '" data-rel="prettyPhoto[product-gallery]">' +
					slide.main_img +
					'</a>' +
					'</div>';
				newMainEls.push($(slideHtml)[0]);

				const thumbHtml =
					'<div class="col ' + (idx === 0 ? 'is-nav-selected first' : '') + '">' +
					'<a>' + (slide.thumb || slide.main_img) + '</a>' +
					'</div>';
				newThumbEls.push($(thumbHtml)[0]);
			});

			if (flktyMain && typeof flktyMain.remove === 'function') {
				const existingCells = flktyMain.getCellElements();
				if (existingCells && existingCells.length) {
					flktyMain.remove(existingCells);
				}
				flktyMain.insert(newMainEls, 0);
				flktyMain.select(0, false, true);
				flktyMain.resize();
			} else {
				$slider.empty().append(newMainEls);
			}

			if ($thumbs.length) {
				if (flktyThumbs && typeof flktyThumbs.remove === 'function') {
					const existingThumbs = flktyThumbs.getCellElements();
					if (existingThumbs && existingThumbs.length) {
						flktyThumbs.remove(existingThumbs);
					}
					flktyThumbs.insert(newThumbEls, 0);
					flktyThumbs.select(0, false, true);
					flktyThumbs.resize();
				} else {
					$thumbs.empty().append(newThumbEls);
				}
			}

			updateGalleryCounter(0, slides.length);
		}

		/**
		 * Resolve and swap the gallery for the currently selected variation or visual hash.
		 */
		function syncGalleryForSelection(explicitVarId, fallbackColorSlug) {
			if (!pdpData) {
				return;
			}

			const isolationMode = pdpData.gallery_isolation_mode || 'strict_variation';
			let targetVarId = explicitVarId || 0;

			if (!targetVarId) {
				const currentSelections = getCurrentSelectedAttributes();
				const matchedVar = findFirstMatchingVariation(currentSelections, true) || findFirstMatchingVariation(currentSelections, false);
				if (matchedVar && matchedVar.var_id) {
					targetVarId = matchedVar.var_id;
				}
			}

			// 1. Strict Variation Gallery Isolation: show ONLY the selected variation's own slide(s).
			if (isolationMode === 'strict_variation' && targetVarId && pdpData.slides_by_variation && pdpData.slides_by_variation[targetVarId]) {
				renderFlatsomeGallerySlides(pdpData.slides_by_variation[targetVarId]);
				return;
			}

			// 2. Fallback / Colorway Group gallery by visual hash.
			const visualHash = computeSelectedVisualHash();
			if (pdpData.slides_by_hash) {
				const hashSlides = pdpData.slides_by_hash[visualHash] || pdpData.slides_by_hash[fallbackColorSlug] || null;
				if (hashSlides && hashSlides.length) {
					renderFlatsomeGallerySlides(hashSlides);
					return;
				}
			}

			if (targetVarId && pdpData.slides_by_variation && pdpData.slides_by_variation[targetVarId]) {
				renderFlatsomeGallerySlides(pdpData.slides_by_variation[targetVarId]);
			}
		}

		// Run initial cross-attribute availability check on page load.
		evaluateCrossAttributeAvailability();

		// Handle Swatch Selection, Cross-Attribute Smart-Switch & Active Contrast Toggle.
		$(document).on('click', '.cux-swatch-item', function (e) {
			e.preventDefault();
			const $btn = $(this);
			if ($btn.hasClass('is-disabled') || $btn.hasClass('is-cross-locked') || $btn.prop('disabled')) {
				return;
			}

			const $wrap = $btn.closest('.cux-swatch-control-wrap');
			const $itemsContainer = $wrap.find('.cux-swatch-items');
			const attrKey = String($wrap.data('attribute') || '').toLowerCase();
			const crossMode = $wrap.data('cross-oos-mode') || (pdpData && pdpData.cross_attr_oos_mode) || 'smart_switch';
			const val = $btn.data('value');
			const name = $btn.data('name') || $btn.data('label') || val;
			const slug = String($btn.data('slug') || val);
			const wasOosInCombo = $btn.hasClass('is-oos-in-combo');

			$itemsContainer.addClass('has-selection');
			$wrap.find('.cux-swatch-item')
				.removeClass('is-selected')
				.addClass('is-deselected')
				.attr('aria-checked', 'false');

			$btn
				.addClass('is-selected')
				.removeClass('is-deselected')
				.attr('aria-checked', 'true');

			$wrap.find('.cux-selected-term-label').text(name);

			const $select = $wrap.find('select').first();
			if ($select.length) {
				$select.val(val);
			}

			// Smart-Switch: if the user clicked an option that was crossed out for the OTHER attribute,
			// automatically switch the other attribute to the first in-stock combination for this clicked option.
			if (wasOosInCombo && crossMode === 'smart_switch' && attrKey) {
				const singleReq = {};
				singleReq[attrKey] = slug;
				const bestMatch = findFirstMatchingVariation(singleReq, true) || findFirstMatchingVariation(singleReq, false);

				if (bestMatch && bestMatch.norm_attrs) {
					$('.cux-swatch-control-wrap').not($wrap).each(function () {
						const $otherWrap = $(this);
						const otherKey = String($otherWrap.data('attribute') || '').toLowerCase();
						const targetSlug = bestMatch.norm_attrs[otherKey];

						if (targetSlug) {
							const $targetBtn = $otherWrap.find('.cux-swatch-item').filter(function () {
								return String($(this).data('slug') || $(this).data('value')) === String(targetSlug);
							}).first();

							if ($targetBtn.length) {
								$otherWrap.find('.cux-swatch-items').addClass('has-selection');
								$otherWrap.find('.cux-swatch-item')
									.removeClass('is-selected')
									.addClass('is-deselected')
									.attr('aria-checked', 'false');
								$targetBtn
									.addClass('is-selected')
									.removeClass('is-deselected')
									.attr('aria-checked', 'true');
								$otherWrap.find('.cux-selected-term-label').text($targetBtn.data('name') || $targetBtn.data('value'));
								$otherWrap.find('select').first().val($targetBtn.data('value'));
							}
						}
					});
				}
			}

			if ($select.length) {
				$select.trigger('change');
			}

			evaluateCrossAttributeAvailability();
			syncGalleryForSelection(0, slug);
		});

		// Handle WooCommerce "Clear" link click to reset active/deselected & cross-attribute states cleanly.
		$(document).on('click', '.reset_variations', function () {
			$('.cux-swatch-control-wrap').each(function () {
				const $wrap = $(this);
				$wrap.find('.cux-swatch-items').removeClass('has-selection');
				$wrap.find('.cux-swatch-item')
					.removeClass('is-selected is-deselected is-oos-in-combo is-cross-locked')
					.attr('aria-checked', 'false');
				$wrap.find('.cux-selected-term-label').text(i18n.i18nChooseOpts);
			});
		});

		/* ======================================================================
		   4. MULTI-LAYOUT DELIVERY SWITCHER (6A ROWS / 6B TABS / 6C CARDS)
		   ====================================================================== */
		$(document).on('click', '.cux-delivery-card', function () {
			const $card = $(this);
			const rateId = $card.data('rate-id') || '';
			const subHtml = $card.data('sub-html') || '';

			$('.cux-delivery-card').removeClass('is-active');
			$card.addClass('is-active');
			$card.find('input[type="radio"]').prop('checked', true);

			$('#cux-chosen-rate-id').val(rateId);

			// Update live summary bar when Option 6B (Segmented 2-Tab Pill) is active.
			const $tabSummary = $('#cux-delivery-tab-summary');
			if ($tabSummary.length && subHtml) {
				$tabSummary.find('.cux-tab-summary-text').html(subHtml);
			}

			if (rateId && i18n.ajaxUrl) {
				$.post(i18n.ajaxUrl, {
					action: 'cux_set_shipping_method',
					nonce: i18n.nonce,
					rate_id: rateId
				});
			}
		});

		$('form.variations_form')
			.on('found_variation', function (event, variation) {
				if (!pdpData || !pdpData.availability_matrix || !variation || !variation.variation_id) {
					return;
				}

				// Ensure gallery strictly matches the found variation ID.
				syncGalleryForSelection(variation.variation_id, '');
				evaluateCrossAttributeAvailability();

				const meta = pdpData.availability_matrix[variation.variation_id];
				const $alertBanner = $('#cux-stock-alert-banner');
				const $pickupLine = $('#cux-pickup-status-line');
				const $addBtn = $(this).find('.single_add_to_cart_button');

				if (!meta) {
					return;
				}

				if ($pickupLine.length) {
					const $dot = $pickupLine.find('.cux-status-dot');
					const defaultSub = $pickupLine.data('default-subtitle') || 'Available for store pickup';

					if (meta.pickup_status === 'ready_today') {
						$dot.removeClass('is-unavailable').addClass('is-available');
						$pickupLine.html('<span class="cux-status-dot is-available"></span> <strong>Get it today</strong> &bull; In Stock at Showroom');
					} else if (meta.pickup_status === 'unavailable') {
						$dot.removeClass('is-available').addClass('is-unavailable');
						$pickupLine.html('<span class="cux-status-dot is-unavailable"></span> Currently unavailable for In-Store Pickup');
					} else {
						$pickupLine.html('<span class="cux-status-dot is-available"></span> ' + defaultSub);
					}
				}

				if (!meta.is_in_stock) {
					const etaMsg = meta.restock_eta
						? i18n.i18nOutOfStock + ' • Restock expected: ' + meta.restock_eta
						: i18n.i18nOutOfStock;
					$alertBanner.show().find('.cux-stock-alert-text').text(etaMsg);
					$addBtn.text(i18n.i18nOutOfStock).addClass('disabled');
				} else if (meta.is_low_stock) {
					$alertBanner.show().find('.cux-stock-alert-text').text(
						i18n.i18nLowStock + (meta.stock_qty > 0 ? ' — Only ' + meta.stock_qty + ' left' : '')
					);
					$addBtn.text(i18n.i18nAddToCart).removeClass('disabled');
				} else {
					$alertBanner.hide();
					$addBtn.text(i18n.i18nAddToCart).removeClass('disabled');
				}
			})
			.on('reset_data', function () {
				$('#cux-stock-alert-banner').hide();
				$(this).find('.single_add_to_cart_button').text(i18n.i18nChooseOpts);
			});

		$(document).on('click', '.single_add_to_cart_button.disabled', function (e) {
			const $unselected = $('.cux-swatch-control-wrap').filter(function () {
				return $(this).find('.cux-swatch-item.is-selected').length === 0;
			}).first();

			if ($unselected.length) {
				e.preventDefault();
				$('html, body').animate({
					scrollTop: $unselected.offset().top - 120
				}, 350);
			}
		});

		/* ======================================================================
		   5. SMART SIZE GUIDE LIGHTBOX MODAL ([block id="..."] UX BLOCK POPUP)
		   ====================================================================== */
		$(document).on('click', '.cux-size-guide-trigger', function (e) {
			e.preventDefault();
			const $modal = $('#cux-size-guide-modal');
			if ($modal.length) {
				$modal.fadeIn(180).attr('aria-hidden', 'false');
				return;
			}

			const target = $(this).data('size-guide') || $(this).data('guide');
			const $existingLightbox = $('a[href="#' + target + '"], a[href*="' + target + '"]').not(this).first();
			if ($existingLightbox.length) {
				$existingLightbox.trigger('click');
			} else if (target && $('#' + target).length) {
				$('html, body').animate({ scrollTop: $('#' + target).offset().top - 80 }, 350);
			}
		});

		$(document).on('click', '.cux-modal-close, .cux-modal-backdrop', function (e) {
			e.preventDefault();
			$('#cux-size-guide-modal').fadeOut(150).attr('aria-hidden', 'true');
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') {
				$('#cux-size-guide-modal').fadeOut(150).attr('aria-hidden', 'true');
				$('#cux-compare-modal').fadeOut(150);
			}
		});

		/* ======================================================================
		   6. IN-PAGE "COMPLETE YOUR RIDE" CROSS-SELL CONFIGURATOR
		   ====================================================================== */
		$(document).on('click', '.cux-cr-header', function () {
			const $box = $(this).closest('.cux-complete-ride-box');
			$box.find('.cux-cr-body').slideToggle(200);
		});

		$(document).on('click', '.cux-cr-dot', function (e) {
			e.preventDefault();
			const imgUrl = $(this).data('img');
			if (imgUrl) {
				$(this).closest('.cux-cr-item').find('.cux-cr-thumb img, .cux-cr-drawer-img img').attr('src', imgUrl);
			}
		});

		$(document).on('click', '.cux-cr-choose-btn', function (e) {
			e.preventDefault();
			const $item = $(this).closest('.cux-cr-item');
			$item.find('.cux-cr-summary-row').hide();
			$item.find('.cux-cr-drawer').fadeIn(180);
		});

		$(document).on('click', '.cux-cr-back-btn', function (e) {
			e.preventDefault();
			const $item = $(this).closest('.cux-cr-item');
			$item.find('.cux-cr-drawer').hide();
			$item.find('.cux-cr-summary-row').fadeIn(150);
		});

		$(document).on('click', '.cux-cr-prev-btn, .cux-cr-next-btn', function (e) {
			e.preventDefault();
			const isNext = $(this).hasClass('cux-cr-next-btn');
			const $current = $(this).closest('.cux-cr-item');
			const $target = isNext ? $current.next('.cux-cr-item') : $current.prev('.cux-cr-item');

			if ($target.length) {
				$current.find('.cux-cr-drawer').hide();
				$current.find('.cux-cr-summary-row').show();
				if ($target.find('.cux-cr-drawer').length) {
					$target.find('.cux-cr-summary-row').hide();
					$target.find('.cux-cr-drawer').fadeIn(180);
				}
			}
		});

		$(document).on('click', '.cux-cr-var-pill', function (e) {
			e.preventDefault();
			const $pill = $(this);
			const $drawer = $pill.closest('.cux-cr-drawer');
			const varId = $pill.data('variation-id');
			const imgUrl = $pill.data('img');

			$drawer.find('.cux-cr-var-pill').removeClass('is-selected');
			$pill.addClass('is-selected');

			if (imgUrl) {
				$drawer.find('.cux-cr-drawer-img img').attr('src', imgUrl);
			}

			$drawer.find('.cux-cr-confirm-add-btn')
				.prop('disabled', false)
				.attr('data-variation-id', varId);
		});

		$(document).on('click', '.cux-cr-direct-add-btn, .cux-cr-confirm-add-btn', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const productId = $btn.data('product-id');
			const variationId = $btn.attr('data-variation-id') || 0;
			const origText = $btn.text();

			if (!productId || $btn.prop('disabled')) {
				return;
			}

			$btn.prop('disabled', true).text('...');

			$.post(i18n.ajaxUrl, {
				action: 'cux_quick_add_to_cart',
				nonce: i18n.nonce,
				product_id: productId,
				variation_id: variationId,
				quantity: 1
			}).done(function (res) {
				$btn.text(i18n.i18nAdded);
				if (res && res.fragments) {
					$.each(res.fragments, function (key, value) {
						$(key).replaceWith(value);
					});
					$(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, $btn]);
				} else {
					$(document.body).trigger('wc_fragment_refresh');
				}
				setTimeout(function () {
					$btn.prop('disabled', false).text(origText);
				}, 1600);
			}).fail(function () {
				$btn.prop('disabled', false).text(origText);
			});
		});

		/* ======================================================================
		   7. FLOATING PRODUCT COMPARE BAR & SIDE-BY-SIDE MODAL
		   ====================================================================== */
		let compareList = [];

		function renderCompareBar() {
			const $bar = $('#cux-compare-bar');
			const $list = $('#cux-compare-items-list');
			const $count = $('#cux-compare-count');

			if (!compareList.length) {
				$bar.fadeOut(150);
				return;
			}

			$list.empty();
			compareList.forEach(function (item) {
				$list.append(
					'<div class="cux-compare-thumb-pill" title="' + item.title + '">' +
					(item.img ? '<img src="' + item.img + '" alt="" />' : '') +
					'</div>'
				);
			});

			$count.text(compareList.length);
			$bar.fadeIn(180);
		}

		$(document).on('change', '.cux-compare-checkbox', function () {
			const $cb = $(this);
			const id = String($cb.val());

			if ($cb.is(':checked')) {
				if (compareList.length >= 4) {
					$cb.prop('checked', false);
					return;
				}
				compareList.push({
					id: id,
					title: $cb.data('title') || '',
					price: $cb.data('price') || '',
					subtitle: $cb.data('subtitle') || '—',
					img: $cb.data('img') || '',
					url: $cb.data('url') || '#'
				});
			} else {
				compareList = compareList.filter(function (item) {
					return item.id !== id;
				});
			}

			renderCompareBar();
		});

		$(document).on('click', '#cux-compare-clear-btn', function () {
			compareList = [];
			$('.cux-compare-checkbox').prop('checked', false);
			renderCompareBar();
		});

		$(document).on('click', '#cux-compare-launch-btn', function () {
			const $grid = $('#cux-compare-modal-grid');
			$grid.empty();

			compareList.forEach(function (item) {
				$grid.append(
					'<div style="border:1px solid #e2e8f0;border-radius:8px;padding:16px;text-align:center;">' +
					(item.img ? '<img src="' + item.img + '" style="max-height:140px;width:auto;margin:0 auto 12px;display:block;" />' : '') +
					'<h4 style="font-size:15px;margin:0 0 6px;"><a href="' + item.url + '">' + item.title + '</a></h4>' +
					'<p style="font-size:12.5px;color:#64748b;margin:0 0 8px;">' + item.subtitle + '</p>' +
					'<div style="font-weight:700;font-size:14px;margin-bottom:12px;">' + item.price + '</div>' +
					'<a href="' + item.url + '" class="button primary is-small">View Product</a>' +
					'</div>'
				);
			});

			$('#cux-compare-modal').fadeIn(180);
		});

		$(document).on('click', '.cux-compare-modal-close, .cux-compare-modal-backdrop', function () {
			$('#cux-compare-modal').fadeOut(150);
		});
	});
})(jQuery);
