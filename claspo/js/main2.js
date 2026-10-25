/**
 * Admin behaviour for the Claspo screens.
 *
 * Two jobs only: tell the user something is happening when a form is submitted,
 * and switch tabs without a round trip to the server.
 *
 * The listener is attached immediately when the document has already finished
 * parsing. Waiting for DOMContentLoaded unconditionally is the usual shape for
 * this code and it is wrong here: hosts that defer, concatenate or late-inject
 * admin scripts can run this file after the event has fired, and then nothing
 * is ever wired up. That is exactly what happened on WordPress.com, where the
 * submit button showed no progress and tab clicks fell back to a full reload.
 */
(function () {
    function init() {
        var page = document.querySelector('.claspo-page');

        if (!page) {
            return;
        }

        wireBusyState(page);
        wireTabs(page);
        wireToggles(page);
        wireCopy(page);
        wireConnectWatch(page);
    }

    /*
     * Connecting calls Claspo's API and saving writes an option; both take long
     * enough that a form which looks idle invites a second and third click.
     *
     * The button keeps its colour and gains Claspo's striped loading fill. It is
     * deliberately not `disabled`: WordPress styles disabled buttons pale grey
     * with !important, which would swallow the whole effect. A submitting flag on
     * the form blocks the second click instead.
     */
    function wireBusyState(page) {
        page.addEventListener('submit', function (event) {
            var form = event.target;

            if (!form || 'FORM' !== form.tagName) {
                return;
            }

            if (form.dataset.claspoSubmitting) {
                event.preventDefault();
                return;
            }

            var button = form.querySelector('button[type="submit"], input[type="submit"]');

            if (!button) {
                return;
            }

            form.dataset.claspoSubmitting = '1';
            form.setAttribute('aria-busy', 'true');

            var busy = button.getAttribute('data-busy');

            if (busy) {
                if ('INPUT' === button.tagName) {
                    button.setAttribute('data-claspo-label', button.value);
                    button.value = busy;
                } else {
                    button.setAttribute('data-claspo-label', button.textContent);
                    button.textContent = busy;
                }
            }

            button.classList.add('is-busy');

            // Text fields only. Checkboxes ignore readOnly anyway, and reaching
            // for them invites styling the hidden control behind each toggle.
            var typed = 'input[type="text"], input[type="search"], input[type="url"], input[type="email"], textarea';

            Array.prototype.forEach.call(form.querySelectorAll(typed), function (field) {
                if (!field.readOnly) {
                    field.readOnly = true;
                    field.setAttribute('data-claspo-locked', '1');
                }
            });
        });

        // The back/forward cache restores the page exactly as it was left,
        // busy state included. Someone who pasted a wrong Script ID and
        // pressed Back would otherwise find a form that refuses every click.
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) {
                return;
            }

            Array.prototype.forEach.call(page.querySelectorAll('form[data-claspo-submitting]'), releaseBusyState);
        });
    }

    function releaseBusyState(form) {
        delete form.dataset.claspoSubmitting;
        form.removeAttribute('aria-busy');

        var button = form.querySelector('.is-busy');

        if (button) {
            button.classList.remove('is-busy');

            var label = button.getAttribute('data-claspo-label');

            if (null !== label) {
                if ('INPUT' === button.tagName) {
                    button.value = label;
                } else {
                    button.textContent = label;
                }
            }
        }

        Array.prototype.forEach.call(form.querySelectorAll('[data-claspo-locked]'), function (field) {
            field.readOnly = false;
            field.removeAttribute('data-claspo-locked');
        });
    }

    function wireTabs(page) {
        var tabs = page.querySelectorAll('[data-claspo-tab]');
        var panels = page.querySelectorAll('[data-claspo-panel]');

        if (!tabs.length || !panels.length) {
            return;
        }

        Array.prototype.forEach.call(tabs, function (tab) {
            tab.addEventListener('click', function (event) {
                // Let modified clicks open a real tab, as any link should.
                if (event.metaKey || event.ctrlKey || event.shiftKey || 1 === event.button) {
                    return;
                }

                event.preventDefault();

                var wanted = tab.getAttribute('data-claspo-tab');

                Array.prototype.forEach.call(tabs, function (other) {
                    other.classList.toggle('nav-tab-active', other === tab);
                });

                Array.prototype.forEach.call(panels, function (panel) {
                    panel.hidden = panel.getAttribute('data-claspo-panel') !== wanted;
                });

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', tab.href);
                }
            });
        });
    }

    /*
     * The toggle answers straight away: the badge flips and the group greys out
     * on the click, not on the round trip. Saving stays a separate, deliberate
     * step, so the page also has to admit that what it is showing is not yet
     * stored — otherwise it reports a state the site does not have.
     */
    /*
     * Registration happens in another tab, and the site is connected server to
     * server from there. This tab would otherwise keep showing "Connect your
     * Claspo account" to someone who has already connected it — so when it comes
     * back into view, it asks whether that happened and reloads if it did.
     *
     * Asking rather than reloading outright: a plain reload on every return
     * would throw away a Script ID someone had pasted but not submitted.
     */
    function wireConnectWatch(page) {
        var panel = page.querySelector('[data-claspo-connected]');

        if (!panel) {
            return;
        }

        var url = panel.getAttribute('data-claspo-connected');
        var nonce = panel.getAttribute('data-claspo-nonce');
        var asking = false;

        document.addEventListener('visibilitychange', function () {
            if (document.hidden || asking) {
                return;
            }

            asking = true;

            fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-WP-Nonce': nonce }
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (d && d.connected) {
                        window.location.reload();
                    }
                })
                .catch(function () {})
                .then(function () { asking = false; });
        });
    }

    function wireToggles(page) {
        var boxes = page.querySelectorAll('.claspo-toggle input[type="checkbox"]');
        var unsaved = page.querySelector('.claspo-unsaved');

        Array.prototype.forEach.call(boxes, function (box) {
            box.addEventListener('change', function () {
                var group = box.closest('.claspo-group');

                if (group) {
                    group.classList.toggle('is-off', !box.checked);

                    var badge = group.querySelector('.claspo-badge');

                    if (badge) {
                        badge.textContent = box.checked ? 'On' : 'Off';
                    }
                }

                if (unsaved) {
                    unsaved.hidden = false;
                }
            });
        });
    }

    /*
     * Claspo matches a display condition on the variable name exactly, so a name
     * retyped with one character wrong is a rule that never fires and nothing
     * says why. Copying is the only safe way to move these across.
     */
    function wireCopy(page) {
        var status = document.getElementById('claspo-copy-status');

        page.addEventListener('click', function (event) {
            var button = event.target.closest('[data-claspo-copy]');

            if (!button) {
                return;
            }

            var name = button.getAttribute('data-claspo-copy');

            copyText(name).then(function () {
                button.classList.add('is-copied');

                if (status) {
                    status.textContent = name + ' copied';
                }

                window.setTimeout(function () {
                    button.classList.remove('is-copied');
                }, 1400);
            }, function () {
                // Nothing reached the clipboard. Showing a tick here would be a
                // lie, and a silent dead end is no better, so select the name
                // and leave the user one keystroke away from the same result.
                selectText(button.parentNode.querySelector('code'));

                button.classList.add('is-failed');

                if (status) {
                    status.textContent = name + ' is selected. Press Ctrl+C or Cmd+C to copy it.';
                }

                window.setTimeout(function () {
                    button.classList.remove('is-failed');
                }, 2400);
            });
        });
    }

    /*
     * navigator.clipboard needs a secure context, and plenty of sites still run
     * plain http. It can also be refused at the moment of the click — by a
     * permissions policy, or because the browser did not count the click as a
     * user gesture — so a rejected write falls back as well, not only a missing
     * API. Only when both routes fail does the caller report a failure.
     */
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(function () {
                return legacyCopy(text);
            });
        }

        return legacyCopy(text);
    }

    function selectText(node) {
        if (!node || !window.getSelection || !document.createRange) {
            return;
        }

        var range = document.createRange();
        range.selectNodeContents(node);

        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function legacyCopy(text) {
        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');

            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.top = '-1000px';
            document.body.appendChild(area);
            area.select();

            var copied = false;

            try {
                copied = document.execCommand('copy');
            } catch (error) {
                copied = false;
            }

            document.body.removeChild(area);

            if (copied) {
                resolve();
            } else {
                reject();
            }
        });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
