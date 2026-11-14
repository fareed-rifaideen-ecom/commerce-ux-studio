/**
 * Commerce UX Studio - Frontend Interactive Controller (Flatsome & WooCommerce 11.3+)
 *
 * File Path: assets/js/frontend-ux.js
 * Author:    Fareed M. Rifaideen
 * Website:   https://fareed-rifaideen.netlify.app/
 * LinkedIn:  https://www.linkedin.com/in/fareed03/
 * GitHub:    https://github.com/fareed-rifaideen-ecom
 *
 * Powers:
 * 1. Shop & Category Card Swatch Image Swap + Deep-Link URL Updater
 * 2. Zara-Style Catalog Quick-Add Size Buttons (AJAX Add to Cart + Mini-Cart Refresh)
 * 3. Single Product Swatches (Color Circles, Size Boxes, Rich Spec Radio Cards)
 * 4. Flatsome Flickity Slider Bridge (Zero-JS Initial Load + Dimensional Size Bypass)
 * 5. Omnichannel Delivery Method Switcher & Live Low-Stock / Pickup Status Updates
 * 6. In-Page "Complete Your Ride" Cross-Sell Drawer & AJAX Add to Cart
 * 7. Floating Side-by-Side Product Comparison Bar & Modal
 */
(function ($) {
	'use strict';

	$(function () {
		const i18n = window.cuxFrontendVars || {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: '',
			i18nChooseOpts: 'Choose Options',
			i18nAddToCart: 'Add to Cart',
			i18nOutOfStock: 'Out of Stock',
			i18nAdded: 'Added to Cart',
			i18nLowStock: 'Low Stock',
			i18nInStock: 'In Stock'
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
				const $mainImg = $card.find('.box-image img').first();
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
				const targetUrl = cleanBase + deepLink;
				$card.find('.box-image a, .product-title a, a.woocommerce-LoopProduct-link').attr('href', targetUrl);
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

			$.post(i18n.ajaxUrl, {
				action: 'cux_quick_add_to_cart',
				nonce: i18n.nonce,
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
		   3. SINGLE PRODUCT PAGE (PDP): SWATCHES & FLATSOME FLICKITY BRIDGE
		   ====================================================================== */
		const $jsonNode = $('#cux-pdp-json-data');
		let pdpData = null;
		let activeVisualHash = '';

		if ($jsonNode.length) {
			try {
				pdpData = JSON.parse($jsonNode.text());
				activeVisualHash = pdpData.winner_visual_hash || '';
			} catch (err) {
				pdpData = null;
			}
		}

		function updateGalleryCounter(currentIndex, totalSlides) {
			const $gallery = $('.product-gallery-slider').first();
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

		function computeSelectedVisualHash() {
			if (!pdpData) {
				return '';
			}
			const parts = [];
			$('.cux-swatch-control-wrap').each(function () {
				const role = $(this).data('role');
				if (role === 'visual_primary' || role === 'visual_secondary') {
					const $sel = $(this).find('.cux-swatch-item.is-selected');
					if ($sel.length && $sel.data('slug')) {
						parts.push($sel.data('slug'));
					}
				}
			});
			return parts.join('__');
		}

		function swapFlatsomeGalleryByHash(visualHash, fallbackColorSlug) {
			if (!pdpData || !pdpData.slides_by_hash) {
				return;
			}

			const slides = pdpData.slides_by_hash[visualHash] || pdpData.slides_by_hash[fallbackColorSlug] || null;
			if (!slides || !slides.length) {
				return;
			}

			if (visualHash === activeVisualHash) {
				return;
			}
			activeVisualHash = visualHash;

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

		$(document).on('click', '.cux-swatch-item', function (e) {
			e.preventDefault();
			const $btn = $(this);
			if ($btn.hasClass('is-disabled') || $btn.prop('disabled')) {
				return;
			}

			const $wrap = $btn.closest('.cux-swatch-control-wrap');
			const role = $wrap.data('role');
			const val = $btn.data('value');
			const name = $btn.data('name');
			const slug = $btn.data('slug');

			$wrap.find('.cux-swatch-item').removeClass('is-selected').attr('aria-checked', 'false');
			$btn.addClass('is-selected').attr('aria-checked', 'true');
			$wrap.find('.cux-selected-term-label').text(name);

			const $select = $wrap.find('select').first();
			if ($select.length) {
				$select.val(val).trigger('change');
			}

			if (role === 'visual_primary' || role === 'visual_secondary') {
				const newHash = computeSelectedVisualHash();
				swapFlatsomeGalleryByHash(newHash, slug);
			}
		});

		/* ======================================================================
		   4. OMNICHANNEL DELIVERY SWITCHER & LIVE VARIATION STATUS UPDATES
		   ====================================================================== */
		$(document).on('click', '.cux-delivery-card', function () {
			const $card = $(this);
			const rateId = $card.data('rate-id') || '';

			$('.cux-delivery-card').removeClass('is-active');
			$card.addClass('is-active');
			$card.find('input[type="radio"]').prop('checked', true);

			$('#cux-chosen-rate-id').val(rateId);

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

				const meta = pdpData.availability_matrix[variation.variation_id];
				const $alertBanner = $('#cux-stock-alert-banner');
				const $pickupLine = $('#cux-pickup-status-line');
				const $addBtn = $(this).find('.single_add_to_cart_button');

				if (!meta) {
					return;
				}

				if ($pickupLine.length) {
					const $dot = $pickupLine.find('.cux-status-dot');
					const $txt = $pickupLine.find('.cux-pickup-mode-text');
					const defaultSub = $pickupLine.data('default-subtitle') || 'Available for store pickup';

					if (meta.pickup_status === 'ready_today') {
						$dot.removeClass('is-unavailable').addClass('is-available');
						$txt.html('<strong>Get it today</strong> &bull; In Stock at Showroom');
					} else if (meta.pickup_status === 'unavailable') {
						$dot.removeClass('is-available').addClass('is-unavailable');
						$txt.text('Currently unavailable for In-Store Pickup');
					} else {
						$dot.removeClass('is-unavailable').addClass('is-available');
						$txt.text(defaultSub);
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

		$(document).on('click', '.cux-size-guide-trigger', function (e) {
			e.preventDefault();
			const target = $(this).data('size-guide');
			const $existingLightbox = $('a[href="#' + target + '"], a[href*="' + target + '"]').not(this).first();
			if ($existingLightbox.length) {
				$existingLightbox.trigger('click');
			} else if ($('#' + target).length) {
				$('html, body').animate({ scrollTop: $('#' + target).offset().top - 80 }, 350);
			}
		});

		/* ======================================================================
		   5. IN-PAGE "COMPLETE YOUR RIDE" CROSS-SELL CONFIGURATOR
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
		   6. FLOATING PRODUCT COMPARE BAR & SIDE-BY-SIDE MODAL
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
