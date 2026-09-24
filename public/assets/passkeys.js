/* Passkey ceremonies (P5-11). Progressive enhancement only: every surface this
   file touches is rendered hidden or no-op by the server; without JS or
   WebAuthn support, password/TOTP/recovery flows remain the working baseline. */
(function () {
    'use strict';

    if (!('PublicKeyCredential' in window) || !window.fetch || !window.FormData) {
        return;
    }

    var CANCELLED = 'Passkey step was cancelled or unavailable — your other sign-in methods still work.';

    // Only the product's own words reach the page. A WebAuthn DOMException
    // always carries a message, and it is the browser's ("The operation either
    // timed out or was not allowed. See: https://www.w3.org/TR/webauthn-2/…"),
    // so testing err.message printed a spec URL on every cancelled prompt.
    // Copy we wrote travels as a productError; anything else (a cancelled or
    // failed ceremony, a dropped connection, an HTML error page where JSON was
    // expected) reads as CANCELLED.
    function productError(message) {
        var error = new Error(message);
        error.product = true;

        return error;
    }

    function messageFor(err) {
        return err && err.product ? err.message : CANCELLED;
    }

    // One ceremony per control at a time: a second press while the browser's
    // passkey sheet is up would start a second challenge and spend another
    // rate-limit slot. aria-busy is the app's shared pending state (board
    // Follow uses it too).
    function claim(button) {
        if (button.getAttribute('aria-busy') === 'true') {
            return false;
        }

        button.setAttribute('aria-busy', 'true');
        return true;
    }

    function release(button) {
        button.removeAttribute('aria-busy');
    }

    function b64uToBuf(value) {
        var s = String(value).replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) {
            s += '=';
        }

        var bin = atob(s);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) {
            bytes[i] = bin.charCodeAt(i);
        }

        return bytes.buffer;
    }

    function bufToB64u(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        for (var i = 0; i < bytes.length; i++) {
            bin += String.fromCharCode(bytes[i]);
        }

        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function tokenNear(el) {
        var input = (el && el.querySelector && el.querySelector('input[name="_token"]'))
            || document.querySelector('input[name="_token"]');

        return input ? input.value : '';
    }

    function post(url, fields, tokenScope) {
        var data = new FormData();
        Object.keys(fields).forEach(function (key) {
            if (fields[key] !== null && fields[key] !== undefined) {
                data.append(key, fields[key]);
            }
        });

        if (!data.get('_token')) {
            data.append('_token', tokenNear(tokenScope));
        }

        return fetch(url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().then(function (json) {
                json.__status = response.status;
                return json;
            });
        });
    }

    function firstError(json, fallback) {
        // A WebAuthnException answer carries a `code` and a message written for
        // operators ("COSE key does not decode…"), not for members.
        if (json && json.errors && !json.code) {
            var keys = Object.keys(json.errors);
            if (keys.length) {
                return json.errors[keys[0]];
            }
        }

        return fallback;
    }

    function show(el, message) {
        if (!el) {
            return;
        }

        el.textContent = message;
        el.hidden = false;
    }

    // The error lines are role="alert" live regions. Clearing one empties it
    // instead of hiding it, so the next failure is a change of text, which is
    // what gets announced, even when it repeats the last one. The login line is
    // rendered empty from the start; the settings lines start hidden (an empty
    // line would add height to their forms) and are first shown with their text.
    function clear(el) {
        if (el) {
            el.textContent = '';
        }
    }

    function prepCreateOptions(options) {
        options.challenge = b64uToBuf(options.challenge);
        options.user.id = b64uToBuf(options.user.id);
        (options.excludeCredentials || []).forEach(function (credential) {
            credential.id = b64uToBuf(credential.id);
        });

        return options;
    }

    function prepGetOptions(options) {
        options.challenge = b64uToBuf(options.challenge);
        (options.allowCredentials || []).forEach(function (credential) {
            credential.id = b64uToBuf(credential.id);
        });

        return options;
    }

    function serializeCreated(credential) {
        var ext = credential.getClientExtensionResults ? credential.getClientExtensionResults() : {};

        return JSON.stringify({
            id: credential.id,
            rawId: bufToB64u(credential.rawId),
            type: credential.type,
            transports: credential.response.getTransports ? credential.response.getTransports() : [],
            credProps: ext.credProps || null,
            response: {
                clientDataJSON: bufToB64u(credential.response.clientDataJSON),
                attestationObject: bufToB64u(credential.response.attestationObject)
            }
        });
    }

    function serializeAssertion(credential) {
        return JSON.stringify({
            id: credential.id,
            rawId: bufToB64u(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: bufToB64u(credential.response.clientDataJSON),
                authenticatorData: bufToB64u(credential.response.authenticatorData),
                signature: bufToB64u(credential.response.signature),
                userHandle: credential.response.userHandle ? bufToB64u(credential.response.userHandle) : null
            }
        });
    }

    function bindAddForm() {
        var addForm = document.querySelector('[data-passkey-add-form]');
        if (!addForm) {
            return;
        }

        addForm.hidden = false;
        var addBtn = addForm.querySelector('[data-passkey-add-btn]');
        var addErr = addForm.querySelector('[data-passkey-add-error]');
        if (!addBtn) {
            return;
        }

        function beginRegistrationChallenge() {
            var password = addForm.querySelector('input[name="current_password"]');
            var assertion = addForm.querySelector('input[name="passkey_assertion"]');

            if (!password && assertion && !assertion.value) {
                return post(addForm.getAttribute('data-stepup-url'), {}, addForm)
                    .then(function (json) {
                        if (!json.ok) {
                            throw productError(firstError(json, 'Confirm with an existing passkey before adding another one.'));
                        }

                        return navigator.credentials.get({ publicKey: prepGetOptions(json.options) });
                    })
                    .then(function (credential) {
                        if (!credential) {
                            throw productError(CANCELLED);
                        }

                        assertion.value = serializeAssertion(credential);
                        return beginRegistrationChallenge();
                    });
            }

            return post(addForm.getAttribute('data-challenge-url'), {
                current_password: password ? password.value : null,
                passkey_assertion: assertion ? assertion.value : null
            }, addForm);
        }

        addBtn.addEventListener('click', function () {
            if (!claim(addBtn)) {
                return;
            }

            clear(addErr);

            beginRegistrationChallenge()
                .then(function (json) {
                    if (!json.ok) {
                        throw productError(firstError(json, 'Could not start the passkey setup.'));
                    }

                    return navigator.credentials.create({ publicKey: prepCreateOptions(json.options) });
                })
                .then(function (credential) {
                    if (!credential) {
                        throw productError(CANCELLED);
                    }

                    var nickname = addForm.querySelector('input[name="nickname"]');
                    return post(addForm.getAttribute('data-store-url'), {
                        credential: serializeCreated(credential),
                        nickname: nickname ? nickname.value : null
                    }, addForm);
                })
                .then(function (json) {
                    if (!json.ok) {
                        throw productError(firstError(json, 'The passkey could not be saved.'));
                    }

                    window.location.reload();
                })
                .catch(function (err) {
                    release(addBtn);
                    show(addErr, messageFor(err));
                });
        });
    }

    function bindRevokeForms() {
        document.querySelectorAll('[data-passkey-revoke-form]').forEach(function (form) {
            var stepBtn = form.querySelector('[data-passkey-stepup-btn]');
            var needsStepUp = form.querySelector('[data-passkey-needs-stepup]');
            if (!stepBtn) {
                return;
            }

            stepBtn.hidden = false;
            if (needsStepUp) {
                needsStepUp.disabled = true;
            }

            stepBtn.addEventListener('click', function () {
                if (!claim(stepBtn)) {
                    return;
                }

                var stepErr = form.querySelector('[data-passkey-revoke-error]');
                clear(stepErr);
                var panel = document.querySelector('[data-passkey-add-form]');
                var stepUpUrl = panel ? panel.getAttribute('data-stepup-url') : '';
                post(stepUpUrl, {}, form)
                    .then(function (json) {
                        if (!json.ok) {
                            throw productError(firstError(json, CANCELLED));
                        }

                        return navigator.credentials.get({ publicKey: prepGetOptions(json.options) });
                    })
                    .then(function (credential) {
                        if (!credential) {
                            throw productError(CANCELLED);
                        }

                        form.querySelector('input[name="passkey_assertion"]').value = serializeAssertion(credential);
                        if (needsStepUp) {
                            needsStepUp.disabled = false;
                        }

                        form.submit();
                    })
                    .catch(function (err) {
                        release(stepBtn);
                        show(stepErr, messageFor(err));
                    });
            });
        });
    }

    function bindSignin() {
        var signin = document.querySelector('[data-passkey-signin]');
        if (!signin) {
            return;
        }

        signin.hidden = false;
        var signinBtn = signin.querySelector('[data-passkey-signin-btn]');
        var signinErr = signin.querySelector('[data-passkey-signin-error]');
        if (!signinBtn) {
            return;
        }

        signinBtn.addEventListener('click', function () {
            if (!claim(signinBtn)) {
                return;
            }

            clear(signinErr);

            var emailInput = document.querySelector('form input[name="email"]');
            var nextInput = document.querySelector('form input[name="next"]');
            post(signin.getAttribute('data-challenge-url'), {
                email: emailInput ? emailInput.value : ''
            }, document)
                .then(function (json) {
                    if (!json.ok) {
                        throw productError(firstError(json, 'Could not start passkey sign-in.'));
                    }

                    return navigator.credentials.get({ publicKey: prepGetOptions(json.options) });
                })
                .then(function (credential) {
                    if (!credential) {
                        throw productError(CANCELLED);
                    }

                    return post(signin.getAttribute('data-login-url'), {
                        email: emailInput ? emailInput.value : '',
                        credential: serializeAssertion(credential),
                        next: nextInput ? nextInput.value : null
                    }, document);
                })
                .then(function (json) {
                    if (!json.ok) {
                        throw productError(firstError(json, 'That passkey could not be used to sign in.'));
                    }

                    window.location.assign(json.redirect || '/');
                })
                .catch(function (err) {
                    release(signinBtn);
                    show(signinErr, messageFor(err));
                });
        });
    }

    // A page restored from the back/forward cache comes back as it was left:
    // the control whose ceremony navigated away is still marked busy, and
    // would ignore every press until a reload.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            document.querySelectorAll('[data-passkey-signin-btn], [data-passkey-add-btn], [data-passkey-stepup-btn]').forEach(release);
        }
    });

    bindAddForm();
    bindRevokeForms();
    bindSignin();
})();
