/**
 * Groups pinned to the bottom of the screen (PinToBottom in PHP): keeps the page's bottom
 * padding equal to the bar's height while it is fixed, so the last fields stay reachable above
 * it, and on touch screens hides the bar while a field outside it has the keyboard up.
 */
(function () {
    // PinToBottom::PINNED_CLASS.
    var BAR = '.yaycheckout-pinned';
    var HAS_PINNED = 'yaycheckout-has-pinned';
    var HEIGHT_VAR = '--yaycheckout-pinned-height';
    var KEYBOARD_HIDDEN = 'is-keyboard-hidden';
    // The width below which a Mobile only bar is fixed; matches checkout-base.css.
    var MOBILE = '(max-width: 781px)';
    var NOT_TYPED = ['checkbox', 'radio', 'hidden', 'submit', 'button', 'range', 'reset', 'image', 'file', 'color'];

    function bars() {
        return Array.prototype.slice.call(document.querySelectorAll(BAR));
    }

    // Visibility doesn't change offsetHeight, so a keyboard-hidden bar keeps its room.
    function update() {
        var height = 0;

        bars().forEach(function (bar) {
            if (window.getComputedStyle(bar).position === 'fixed') {
                height = Math.max(height, bar.offsetHeight);
            }
        });

        var root = document.documentElement;
        root.style.setProperty(HEIGHT_VAR, height + 'px');
        root.classList.toggle(HAS_PINNED, height > 0);
    }

    // A field the touch keyboard opens for, outside every bar.
    function isTypedFieldOutside(el) {
        if (!el || !el.tagName || (el.closest && el.closest(BAR))) {
            return false;
        }

        var tag = el.tagName.toLowerCase();

        if (tag === 'textarea' || tag === 'select') {
            return true;
        }

        return tag === 'input' && NOT_TYPED.indexOf((el.getAttribute('type') || 'text').toLowerCase()) === -1;
    }

    function setKeyboardHidden(hidden) {
        bars().forEach(function (bar) {
            bar.classList.toggle(KEYBOARD_HIDDEN, hidden);
        });
    }

    function init() {
        if (bars().length === 0) {
            return;
        }

        update();

        if (window.ResizeObserver) {
            var observer = new ResizeObserver(update);
            bars().forEach(function (bar) {
                observer.observe(bar);
            });
        }

        var mobile = window.matchMedia(MOBILE);

        if (mobile.addEventListener) {
            mobile.addEventListener('change', update);
        } else if (mobile.addListener) {
            mobile.addListener(update);
        }

        window.addEventListener('resize', update);

        if (!window.matchMedia('(pointer: coarse)').matches) {
            return;
        }

        document.addEventListener('focusin', function (event) {
            if (isTypedFieldOutside(event.target)) {
                setKeyboardHidden(true);
            }
        });

        // Focus moving from one field to the next fires focusout first, so wait for it to land.
        document.addEventListener('focusout', function () {
            window.setTimeout(function () {
                if (!isTypedFieldOutside(document.activeElement)) {
                    setKeyboardHidden(false);
                }
            }, 0);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
