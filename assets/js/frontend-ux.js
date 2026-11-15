/*!
 * Commerce UX Studio - Frontend Interactive Controller (v2.3.0)
 *
 * File Path: assets/js/frontend-ux.js
 * Author:    Fareed M. Rifaideen
 * Website:   https://fareed-rifaideen.netlify.app/
 * LinkedIn:  https://www.linkedin.com/in/fareed03/
 * GitHub:    https://github.com/fareed-rifaideen-ecom
 */
(function ($) {
	'use strict';

	$(function () {
		const rawCfg = window.cuxFrontendConfig || window.cuxFrontendVars || {};
		const nestedI18n = rawCfg.i18n || {};

		const cfg = {
			ajaxUrl: rawCfg.ajaxUrl || '/wp-admin/admin-ajax.php',
			nonce: rawCfg.nonce || '',
			crossAttrOosMode: rawCfg.crossAttrOosMode || 'smart_switch',
			pdpGalleryIsolation: rawCfg.pdpGalleryIsolation || 'strict_variation',
			i18nChooseOpts: nestedI18n.chooseOptions || 'Choose Options',
			i18nAddToCart: nestedI18n.addToCart || 'Add to Cart',
			i18nOutOfStock: nestedI18n.outOfStock || 'Out of Stock',
			i18nAdded: nestedI18n.added || 'Added to Cart',
			i18nLowStock: nestedI18n.lowStock || 'Low Stock',
			i18nUnavailable: nestedI18n.unavailableIn || 'Unavailable in selected option'
		};

		/* ======================================================================
		   1. SHOP & CATEGORY CARD SWATCH CLICK -> SWAP IMAGE & DEEP LINK
		   ====================================================================== */
		$(document).on('click', '.cux-card-swatch-dot', function (e) {
			e.preventDefault();
			e.stopPropagation();

			const $dot = $(this);
			const $card = $dot.closest('.product-small, .product');
			const imgSrc = $dot.data('img-src');
			const hoverSrc = $dot.data('hover-src');
			const deepLink = $dot.data('deep-link');

			$dot.siblings('.cux-card-swatch-dot').removeClass('is-active');
			$dot.addClass('is-active');

			if (imgSrc) {
				const $mainImg = $card.find('.box-image img').not('.back-image').first();
				if ($mainImg.length) {
					$mainImg.attr('src', imgSrc).removeAttr('srcset').removeAttr('sizes');
				}
			}

			if (hoverSrc) {
				const $hoverImg = $card.find('.box-image .show-on-hover img, .box-image img.back-image').first();
				if ($hoverImg.length) {
					$hoverImg.attr('src', hoverSrc).removeAttr('srcset').removeAttr('sizes');
				}
			}

			const baseUrl = $card.find('[data-base-url]').first().data('base-url');
			if (baseUrl && deepLink) {
				const cleanBase = String(baseUrl).split('?')[0];
				$card.find('.box-image a, .product-title a, a.woocommerce-LoopProduct-link').attr('href', cleanBase + deepLink);
			}
		});

		/* ======================================================================
		   2. ZARA-STYLE CATALOG QUICK-ADD SIZE PILLS
		   ====================================================================== */
		$(document).on('click', '.cux-zara-size-pill', function (e) {
			e.preventDefault();
			e.stopPropagation();

			const $btn = $(this);
			const productId = $btn.data('product-id');
			const variationId = $btn.data('variation-id');
			const origText = $btn.text();

			if (!productId || !variationId || $btn.prop('disabled')) {
				return;
			}

			$btn.prop('disabled', true).text('...');

			$.post(cfg.ajaxUrl, {
				action: 'cux_quick_add_to_cart',
				nonce: cfg.nonce,
				product_id: productId,
				variation_id: variationId,
				quantity: 1
			}).done(function (res) {
				$btn.text('✓');
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
				}, 1400);
			}).fail(function () {
				$btn.prop('disabled', false).text(origText);
			});
		});

		/* ======================================================================
		   3. SINGLE PRODUCT SWATCHES, CROSS-ATTRIBUTE AVAILABILITY & STRICT GALLERY
		   ====================================================================== */
		const $jsonNode = $('#cux-pdp-json-data, #cux-pdp-data').first();
		let pdpData = null;
		let activeSlideKey = '';

		if ($jsonNode.length) {
			try {
				pdpData = JSON.parse($jsonNode.text());
			} catch (err) {
				pdpData = null;
			}
		}

		function getCurrentSelections() {
			const selections = {};
			$('.cux-attr-block').each(function () {
				const attrKey = $(this).data('attribute');
				const $sel = $(this).find('.cux-swatch-item.is-selected').first();
				if (attrKey && $sel.length) {
					selections[attrKey] = String($sel.data('value'));
				}
			});
			return selections;
		}

		function isOptionAvailableWithSelections(targetAttrKey, candidateVal, currentSelections) {
			if (!pdpData || !Array.isArray(pdpData.variations_matrix) || !pdpData.variations_matrix.length) {
				return true;
			}

			for (let i = 0; i < pdpData.variations_matrix.length; i++) {
				const row = pdpData.variations_matrix[i];
				if (!row.is_in_stock) {
					continue;
				}
				const vAttrs = row.attributes || {};
				const rowTargetVal = vAttrs[targetAttrKey];
				if (rowTargetVal !== '' && rowTargetVal !== undefined && String(rowTargetVal) !== String(candidateVal)) {
					continue;
				}

				let matchesOtherSelected = true;
				for (const otherKey in currentSelections) {
					if (otherKey === targetAttrKey) {
						continue;
					}
					const chosenOtherVal = currentSelections[otherKey];
					if (!chosenOtherVal) {
						continue;
					}
					const rowOtherVal = vAttrs[otherKey];
					if (rowOtherVal !== '' && rowOtherVal !== undefined && String(rowOtherVal) !== String(chosenOtherVal)) {
						matchesOtherSelected = false;
						break;
					}
				}

				if (matchesOtherSelected) {
					return true;
				}
			}

			return false;
		}

		function findFirstInStockVariationForOption(attrKey, optionVal) {
			if (!pdpData || !Array.isArray(pdpData.variations_matrix)) {
				return null;
			}
			for (let i = 0; i < pdpData.variations_matrix.length; i++) {
				const row = pdpData.variations_matrix[i];
				if (!row.is_in_stock) {
					continue;
				}
				const vAttrs = row.attributes || {};
				if (vAttrs[attrKey] === '' || String(vAttrs[attrKey]) === String(optionVal)) {
					return row;
				}
			}
			return null;
		}

		function evaluateCrossAttributeAvailability() {
			const currentSelections = getCurrentSelections();

			$('.cux-attr-block').each(function () {
				const $block = $(this);
				const attrKey = $block.data('attribute');
				const hasSel = $block.find('.cux-swatch-item.is-selected').length > 0;

				$block.toggleClass('has-selection', hasSel);
				$block.find('.cux-inline-reset-btn').toggle(hasSel);

				$block.find('.cux-swatch-item').each(function () {
					const $btn = $(this);
					const val = String($btn.data('value'));
					const baseLabel = $btn.data('label') || val;
					const isSelected = $btn.hasClass('is-selected');

					$btn.toggleClass('is-deselected', hasSel && !isSelected);

					const available = isOptionAvailableWithSelections(attrKey, val, currentSelections);
					if (!available) {
						$btn.addClass('is-oos-in-combo').attr('title', baseLabel + ' — ' + cfg.i18nUnavailable);
						if (cfg.crossAttrOosMode === 'strict_lock') {
							$btn.prop('disabled', true);
						}
					} else {
						$btn.removeClass('is-oos-in-combo').attr('title', baseLabel).prop('disabled', false);
					}
				});
			});
		}

		function replaceFlatsomeGallerySlides(slides, slideKey) {
			if (!slides || !slides.length || slideKey === activeSlideKey) {
				return;
			}
			activeSlideKey = slideKey;

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
					'<div data-thumb="' + slide.thumb_url + '" class="woocommerce-product-gallery__image slide ' + (idx === 0 ? 'first' : '') + '">' +
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
				if (slides.length <= 1) {
					$thumbs.addClass('cux-single-image-hidden');
				} else {
					$thumbs.removeClass('cux-single-image-hidden');
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
			}
		}

		function syncGalleryForSelection(variationId, fallbackColorSlug) {
			if (!pdpData) {
				return;
			}

			const isolation = pdpData.gallery_isolation || cfg.pdpGalleryIsolation || 'strict_variation';

			if (isolation === 'strict_variation' && variationId && pdpData.slides_by_variation && pdpData.slides_by_variation[variationId]) {
				replaceFlatsomeGallerySlides(pdpData.slides_by_variation[variationId], 'var_' + variationId);
				return;
			}

			if (fallbackColorSlug && pdpData.slides_by_hash && pdpData.slides_by_hash[fallbackColorSlug]) {
				replaceFlatsomeGallerySlides(pdpData.slides_by_hash[fallbackColorSlug], 'hash_' + fallbackColorSlug);
			} else if (variationId && pdpData.slides_by_variation && pdpData.slides_by_variation[variationId]) {
				replaceFlatsomeGallerySlides(pdpData.slides_by_variation[variationId], 'var_' + variationId);
			}
		}

		// Initial cross-attribute evaluation on page load.
		evaluateCrossAttributeAvailability();
		const initialSlidesCount = $('.product-gallery-slider .woocommerce-product-gallery__image, .product-gallery-slider .slide').length;
		if (initialSlidesCount <= 1) {
			$('.product-thumbnails').addClass('cux-single-image-hidden');
		}

		$(document).on('click', '.cux-swatch-item', function (e) {
			e.preventDefault();
			const $btn = $(this);
			if ($btn.prop('disabled')) {
				return;
			}

			const $wrap = $btn.closest('.cux-attr-block');
			const attrKey = $wrap.data('attribute');
			const role = $wrap.data('role');
			const val = String($btn.data('value'));
			const label = $btn.data('label') || val;
			const slug = $btn.data('slug') || val;

			// Smart-Switch: if user clicked a crossed-out option in Smart Switch mode, auto-switch the other attribute(s) to the first available match.
			if ($btn.hasClass('is-oos-in-combo') && cfg.crossAttrOosMode === 'smart_switch') {
				const fallbackRow = findFirstInStockVariationForOption(attrKey, val);
				if (fallbackRow && fallbackRow.attributes) {
					$.each(fallbackRow.attributes, function (otherKey, otherVal) {
						if (otherKey === attrKey || !otherVal) {
							return;
						}
						const $otherBlock = $('.cux-attr-block[data-attribute="' + otherKey + '"]');
						const $targetBtn = $otherBlock.find('.cux-swatch-item').filter(function () {
							return String($(this).data('value')) === String(otherVal);
						}).first();

						if ($targetBtn.length) {
							$otherBlock.find('.cux-swatch-item').removeClass('is-selected').attr('aria-checked', 'false');
							$targetBtn.addClass('is-selected').attr('aria-checked', 'true');
							$otherBlock.find('.cux-selected-term-label').text($targetBtn.data('label') || otherVal);
							const $otherSelect = $otherBlock.prev('.cux-hidden-select').find('select').first();
							if ($otherSelect.length) {
								$otherSelect.val(otherVal);
							}
						}
					});
				}
			}

			$wrap.find('.cux-swatch-item').removeClass('is-selected').attr('aria-checked', 'false');
			$btn.addClass('is-selected').attr('aria-checked', 'true');
			$wrap.find('.cux-selected-term-label').text(label);

			const $select = $wrap.prev('.cux-hidden-select').find('select').first();
			if ($select.length) {
				$select.val(val).trigger('change');
			}

			evaluateCrossAttributeAvailability();

			if (role === 'primary_visual' || role === 'secondary_visual') {
				syncGalleryForSelection(0, slug);
			}
		});

		$(document).on('click', '.cux-inline-reset-btn', function (e) {
			e.preventDefault();
			$('.cux-attr-block').each(function () {
				$(this).removeClass('has-selection').find('.cux-swatch-item').removeClass('is-selected is-deselected is-oos-in-combo').prop('disabled', false);
				$(this).find('.cux-selected-term-label').text('');
				$(this).find('.cux-inline-reset-btn').hide();
				$(this).prev('.cux-hidden-select').find('select').val('').trigger('change');
			});
			$('form.variations_form').trigger('reset_data');
		});

		/* ======================================================================
		   4. DELIVERY SWITCHER (OPTIONS 6A, 6B, 6C) & VARIATION EVENTS
		   ====================================================================== */
		$(document).on('click keydown', '.cux-delivery-card', function (e) {
			if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
				return;
			}
			if (e.type === 'keydown') {
				e.preventDefault();
			}

			const $card = $(this);
			const rateId = $card.data('rate-id') || '';

			$('.cux-delivery-card').removeClass('is-selected is-active');
			$card.addClass('is-selected is-active');
			$('#cux-chosen-rate-id').val(rateId);

			// Update Option 6B summary bar if active.
			const $sumBar = $('#cux-delivery-tab-summary');
			if ($sumBar.length && $card.hasClass('cux-delivery-tab-btn')) {
				$sumBar.find('.cux-tab-sum-price').text($card.data('price') || '');
				$sumBar.find('.cux-tab-sum-eta').text($card.data('eta') ? '• ' + $card.data('eta') : '');
				$sumBar.find('.cux-tab-sum-sub').text($card.data('subtitle') ? '• ' + $card.data('subtitle') : '');
			}

			if (rateId && cfg.ajaxUrl && cfg.nonce) {
				$.post(cfg.ajaxUrl, {
					action: 'cux_set_shipping_method',
					nonce: cfg.nonce,
					rate_id: rateId
				});
			}
		});

		$('form.variations_form')
			.on('found_variation', function (event, variation) {
				if (!variation || !variation.variation_id) {
					return;
				}

				syncGalleryForSelection(variation.variation_id, '');

				if (!pdpData || !pdpData.availability_matrix) {
					return;
				}

				const meta = pdpData.availability_matrix[variation.variation_id];
				const $alertBanner = $('#cux-stock-alert-banner');
				const $addBtn = $(this).find('.single_add_to_cart_button');

				if (!meta) {
					return;
				}

				if (!meta.is_in_stock) {
					const etaMsg = meta.restock_eta
						? cfg.i18nOutOfStock + ' • Restock expected: ' + meta.restock_eta
						: cfg.i18nOutOfStock;
					$alertBanner.show().find('.cux-stock-alert-text').text(etaMsg);
					$addBtn.text(cfg.i18nOutOfStock).addClass('disabled');
				} else if (meta.is_low_stock) {
					$alertBanner.show().find('.cux-stock-alert-text').text(
						cfg.i18nLowStock + (meta.stock_qty > 0 ? ' — Only ' + meta.stock_qty + ' left' : '')
					);
					$addBtn.text(cfg.i18nAddToCart).removeClass('disabled');
				} else {
					$alertBanner.hide();
					$addBtn.text(cfg.i18nAddToCart).removeClass('disabled');
				}
			})
			.on('reset_data', function () {
				$('#cux-stock-alert-banner').hide();
				evaluateCrossAttributeAvailability();
			});

		/* ======================================================================
		   5. SIZE GUIDE LIGHTBOX MODAL & COMPLETE YOUR RIDE DRAWER
		   ====================================================================== */
		$(document).on('click', '.cux-size-guide-trigger', function (e) {
			e.preventDefault();
			const $modal = $('#cux-size-guide-modal');
			if ($modal.length) {
				$modal.fadeIn(180).attr('aria-hidden', 'false');
			}
		});

		$(document).on('click', '#cux-size-guide-modal .cux-modal-close, #cux-size-guide-modal .cux-modal-backdrop', function () {
			$('#cux-size-guide-modal').fadeOut(150).attr('aria-hidden', 'true');
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') {
				$('#cux-size-guide-modal, #cux-compare-modal').fadeOut(150);
			}
		});

		$(document).on('click', '.cux-cr-header', function () {
			$(this).closest('.cux-complete-ride-box').find('.cux-cr-body').slideToggle(200);
		});

		$(document).on('click', '.cux-cr-direct-add-btn', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const productId = $btn.data('product-id');
			const origText = $btn.text();

			if (!productId || $btn.prop('disabled')) {
				return;
			}

			$btn.prop('disabled', true).text('...');

			$.post(cfg.ajaxUrl, {
				action: 'cux_quick_add_to_cart',
				nonce: cfg.nonce,
				product_id: productId,
				variation_id: 0,
				quantity: 1
			}).done(function (res) {
				$btn.text(cfg.i18nAdded);
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
				}, 1500);
			}).fail(function () {
				$btn.prop('disabled', false).text(origText);
			});
		});

		/* ======================================================================
		   6. FLOATING PRODUCT COMPARE BAR & MODAL
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
