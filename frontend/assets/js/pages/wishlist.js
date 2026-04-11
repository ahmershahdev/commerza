    if (typeof applySiteSettings === "function") {
      applySiteSettings();
    }

    $(function() {
      let csrfToken = ($('#wishlist-container').data('csrfToken') || '').toString();

      function refreshAlertFade() {
        $("#serverAlert, #successAlert").each(function() {
          const element = $(this);
          setTimeout(function() {
            element.fadeOut(400);
          }, 3500);
        });
      }

      function ensureAlert(id, cssClass) {
        let alertElement = $('#' + id);
        if (alertElement.length) {
          return alertElement;
        }

        alertElement = $('<div/>', {
          id,
          class: cssClass,
          role: 'alert'
        }).hide();

        $('body').append(alertElement);
        return alertElement;
      }

      function flashAlert(type, message) {
        if (!message) {
          return;
        }

        const isError = type === 'error';
        const id = isError ? 'serverAlert' : 'successAlert';
        const cssClass = isError ? 'alert alert-danger text-center' : 'alert alert-success text-center';

        const element = ensureAlert(id, cssClass);
        element.stop(true, true).text(message).fadeIn(160).delay(2600).fadeOut(320);
      }

      function syncCsrf(nextToken) {
        const token = (nextToken || '').toString().trim();
        if (!token) {
          return;
        }

        csrfToken = token;
        $('input[name="csrf_token"]').val(token);
        $('#wishlist-container').attr('data-csrf-token', token);
      }

      function updateWishlistCount(count) {
        const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
        $('#wishlist-count').text(safeCount);
        $('#wishlist-count-mobile').text(safeCount);
      }

      function updateCartCount(count) {
        const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
        $('#cart-count').text(safeCount);
        $('#cart-count-mobile').text(safeCount);
      }

      function renderEmptyStateIfNeeded() {
        const cards = $('#wishlist-container .wishlist-item-card');
        if (cards.length > 0) {
          return;
        }

        $('#wishlist-container').html(`
          <div class="text-center py-5 wishlist-empty-state">
            <i class="bi bi-heart" style="font-size: 3rem; color: #ff6600;"></i>
            <h3 class="text-white mt-3">Your wishlist is empty</h3>
            <p class="text-secondary">Start saving your favorite watches.</p>
            <a href="index.php" class="btn product-btn-buy mt-3">Browse Products</a>
          </div>
        `);

        updateWishlistCount(0);
        $('#wishlistClearForm').addClass('d-none');
      }

      async function postWishlistAction(action, payload) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', csrfToken || '');

        Object.entries(payload || {}).forEach(([key, value]) => {
          body.set(key, String(value));
        });

        const response = await fetch('backend/wishlist_api.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: body.toString()
        });

        const data = await response.json();
        syncCsrf(data?.csrf_token || '');

        if (!response.ok || !data?.ok) {
          throw new Error((data?.message || 'Unable to update wishlist.').toString());
        }

        if (typeof data?.count !== 'undefined') {
          updateWishlistCount(data.count);
        }

        return data;
      }

      function readCardIdentity(card) {
        const productId = parseInt(card.data('productId'), 10);
        const productName = (card.data('productName') || '').toString().trim();
        const productCode = (card.data('productCode') || '').toString().trim();

        return {
          productId,
          productName,
          productCode
        };
      }

      function installWishlistCardTracking() {
        if (
          window.matchMedia &&
          !window.matchMedia('(hover: hover) and (pointer: fine)').matches
        ) {
          return;
        }

        const setCardMotion = (card, pointerX, pointerY) => {
          if (!card || typeof card.getBoundingClientRect !== 'function') {
            return;
          }

          const rect = card.getBoundingClientRect();
          if (rect.width <= 0 || rect.height <= 0) {
            return;
          }

          const relativeX = (pointerX - rect.left) / rect.width;
          const relativeY = (pointerY - rect.top) / rect.height;
          const rotateY = (relativeX - 0.5) * 10;
          const rotateX = (0.5 - relativeY) * 8;
          const shiftX = (relativeX - 0.5) * 8;
          const shiftY = (relativeY - 0.5) * 2;
          const pointerPercentX = Math.min(100, Math.max(0, relativeX * 100));
          const pointerPercentY = Math.min(100, Math.max(0, relativeY * 100));

          card.style.setProperty('--pc-rotate-x', `${rotateX.toFixed(2)}deg`);
          card.style.setProperty('--pc-rotate-y', `${rotateY.toFixed(2)}deg`);
          card.style.setProperty('--pc-shift-x', `${shiftX.toFixed(2)}px`);
          card.style.setProperty('--pc-shift-y', `${shiftY.toFixed(2)}px`);
          card.style.setProperty('--pc-pointer-x', `${pointerPercentX.toFixed(2)}%`);
          card.style.setProperty('--pc-pointer-y', `${pointerPercentY.toFixed(2)}%`);
        };

        const resetCardMotion = (card) => {
          if (!card || !card.style) {
            return;
          }

          card.style.setProperty('--pc-rotate-x', '0deg');
          card.style.setProperty('--pc-rotate-y', '0deg');
          card.style.setProperty('--pc-shift-x', '0px');
          card.style.setProperty('--pc-shift-y', '0px');
          card.style.setProperty('--pc-pointer-x', '50%');
          card.style.setProperty('--pc-pointer-y', '50%');
        };

        $(document)
          .off('mousemove.wishlistCardTracking', '.wishlist-item-card')
          .on('mousemove.wishlistCardTracking', '.wishlist-item-card', function(event) {
            setCardMotion(this, event.clientX, event.clientY);
          });

        $(document)
          .off('mouseleave.wishlistCardTracking', '.wishlist-item-card')
          .on('mouseleave.wishlistCardTracking', '.wishlist-item-card', function() {
            resetCardMotion(this);
          });

        $(window)
          .off('blur.wishlistCardTracking')
          .on('blur.wishlistCardTracking', function() {
            document.querySelectorAll('.wishlist-item-card').forEach((card) => {
              resetCardMotion(card);
            });
          });
      }

      async function addToCart(identity, retry = 0) {
        const productId = Number(identity?.productId || 0);
        const body = new URLSearchParams();
        body.set('action', 'add');
        body.set('product_id', String(productId));
        body.set('quantity', '1');
        if (identity?.productName) {
          body.set('product_name', String(identity.productName));
        }
        if (identity?.productCode) {
          body.set('product_code', String(identity.productCode));
        }
        body.set('csrf_token', csrfToken || '');

        const response = await fetch('backend/cart_api.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: body.toString()
        });

        const data = await response.json();
        syncCsrf(data?.csrf_token || '');

        if ((response.status === 403 || response.status === 409) && data?.csrf_token && retry < 1) {
          return addToCart(identity, retry + 1);
        }

        if (!response.ok || !data?.ok) {
          throw new Error((data?.message || 'Unable to add item to cart.').toString());
        }

        if (typeof data?.count !== 'undefined') {
          updateCartCount(data.count);
        }

        return data;
      }

      $(document).on('submit', '.wishlist-remove-form', async function(event) {
        event.preventDefault();
        const form = $(this);
        const button = form.find('.wishlist-remove-btn');
        const card = form.closest('.wishlist-item-card');
        const identity = readCardIdentity(card);

        if (!Number.isInteger(identity.productId) || identity.productId <= 0) {
          flashAlert('error', 'Invalid wishlist item.');
          return;
        }

        if (identity.productName === '' || identity.productCode === '') {
          flashAlert('error', 'Product verification data is missing. Refresh and try again.');
          return;
        }

        button.prop('disabled', true);

        try {
          await postWishlistAction('remove', {
            product_id: identity.productId,
            product_name: identity.productName,
            product_code: identity.productCode
          });

          card.addClass('is-removing');
          window.setTimeout(() => {
            card.remove();
            renderEmptyStateIfNeeded();
          }, 210);

          flashAlert('success', 'Product removed from wishlist.');
        } catch (error) {
          flashAlert('error', error?.message || 'Unable to remove product right now.');
          button.prop('disabled', false);
        }
      });

      $(document).on('click', '.wishlist-move-cart-btn', async function() {
        const button = $(this);
        const card = button.closest('.wishlist-item-card');
        const identity = readCardIdentity(card);

        if (!Number.isInteger(identity.productId) || identity.productId <= 0) {
          flashAlert('error', 'Invalid wishlist item.');
          return;
        }

        if (identity.productName === '' || identity.productCode === '') {
          flashAlert('error', 'Product verification data is missing. Refresh and try again.');
          return;
        }

        button.prop('disabled', true).text('Moving...');

        try {
          await addToCart(identity);
          await postWishlistAction('remove', {
            product_id: identity.productId,
            product_name: identity.productName,
            product_code: identity.productCode
          });

          card.addClass('is-removing');
          window.setTimeout(() => {
            card.remove();
            renderEmptyStateIfNeeded();
          }, 210);

          flashAlert('success', 'Moved to cart successfully.');
        } catch (error) {
          flashAlert('error', error?.message || 'Unable to move item to cart.');
          button.prop('disabled', false).text('Move to Cart');
        }
      });

      $(document).on('submit', '#wishlistClearForm', async function(event) {
        event.preventDefault();
        const form = $(this);
        const button = $('#wishlistClearBtn');

        button.prop('disabled', true).text('Clearing...');

        try {
          await postWishlistAction('clear', {});
          $('#wishlist-container .wishlist-item-card').remove();
          renderEmptyStateIfNeeded();
          flashAlert('success', 'Wishlist cleared successfully.');
        } catch (error) {
          flashAlert('error', error?.message || 'Unable to clear wishlist right now.');
          button.prop('disabled', false).text('Clear');
          return;
        }

        form.addClass('d-none');
      });

      installWishlistCardTracking();
      refreshAlertFade();
    });
