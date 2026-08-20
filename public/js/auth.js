/* ---------------------------------------------------------------------------
   Vidlix authentication — step navigation, OTP entry, orbit morph, terms modal.

   Progressive enhancement: every form here posts to a real endpoint and the
   server decides. This file makes the flow pleasant, never authoritative — an
   OTP is checked on the server, and the "verified" flag the browser holds is
   re-checked there before an account is created.
--------------------------------------------------------------------------- */
(function () {
  'use strict';

  var csrf = document.querySelector('meta[name="csrf-token"]');
  var CSRF = csrf ? csrf.getAttribute('content') : '';

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body || {}),
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        return { status: response.status, data: data };
      });
    });
  }

  /* --- Notices ----------------------------------------------------------- */
  function notice(el, kind, message) {
    if (!el) return;
    if (!message) { el.hidden = true; return; }
    el.className = 'notice ' + kind;
    el.textContent = message;
    el.hidden = false;
  }

  /* --- Password reveal --------------------------------------------------- */
  

  /* --- Terms modal ------------------------------------------------------- */
  var modal = document.querySelector('[data-terms-modal]');
  var lastFocused = null;

  function currentRole() {
    var checked = document.querySelector('input[name="role"]:checked');
    return checked ? checked.value : null;
  }

  function openTerms() {
    if (!modal) return;
    var role = currentRole();
    if (!role) {
      notice(document.querySelector('[data-notice]'), 'bad', 'Choose what you do first, so we can show the terms that apply to you.');
      return;
    }

    // Only the selected role's terms are rendered; the rest stay hidden.
    modal.querySelectorAll('[data-terms-for]').forEach(function (block) {
      block.hidden = block.getAttribute('data-terms-for') !== role;
    });

    lastFocused = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';

    // Accept is closed until the reader reaches the end. A button that is live
    // under an unread wall of text collects a click, not consent.
    gateAccept();

    var focusable = modal.querySelector('[data-terms-accept]');
    if (focusable) focusable.focus();
  }

  function gateAccept() {
    var body = modal.querySelector('.modal-body');
    var accept = modal.querySelector('[data-terms-accept]');
    var note = modal.querySelector('[data-terms-gate]');

    if (!body || !accept) return;

    body.scrollTop = 0;

    function check() {
      // A short agreement may not scroll at all, in which case it has already
      // been seen in full.
      var read = body.scrollHeight - body.clientHeight <= 4
        || body.scrollTop + body.clientHeight >= body.scrollHeight - 24;

      accept.disabled = !read;
      if (note) note.hidden = read;
    }

    body.removeEventListener('scroll', check);
    body.addEventListener('scroll', check);
    check();
  }

  function closeTerms() {
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocused) lastFocused.focus();
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-terms-open]')) { event.preventDefault(); openTerms(); return; }
    if (event.target.closest('[data-terms-close]')) { event.preventDefault(); closeTerms(); return; }

    if (event.target.closest('[data-terms-accept]')) {
      event.preventDefault();
      var box = document.querySelector('#accepted_terms');
      if (box) {
        box.checked = true;
        box.dispatchEvent(new Event('change', { bubbles: true }));
      }
      closeTerms();
      return;
    }

    // Clicking the backdrop, but not the card itself.
    if (modal && !modal.hidden && event.target === modal) closeTerms();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeTerms();
  });

  // Changing role after accepting invalidates the acceptance: they agreed to
  // different terms.
  document.querySelectorAll('input[name="role"]').forEach(function (input) {
    input.addEventListener('change', function () {
      var box = document.querySelector('#accepted_terms');
      if (box && box.checked) {
        box.checked = false;
        notice(document.querySelector('[data-notice]'), 'info', 'Terms differ by role — please read and accept the ones for ' + (input.dataset.label || 'this role') + '.');
      }
    });
  });

  /* --- Step machine ------------------------------------------------------ */
  function showStep(root, step) {
    root.querySelectorAll('[data-step]').forEach(function (panel) {
      panel.hidden = Number(panel.getAttribute('data-step')) !== step;
    });
    root.querySelectorAll('[data-step-dot]').forEach(function (dot) {
      dot.classList.toggle('is-done', Number(dot.getAttribute('data-step-dot')) <= step);
    });
    var focusTarget = root.querySelector('[data-step="' + step + '"] input, [data-step="' + step + '"] button');
    if (focusTarget) setTimeout(function () { focusTarget.focus(); }, 60);
  }

  function busy(button, on) {
    if (!button) return;
    button.disabled = on;
    button.classList.toggle('is-busy', on);
  }

  /* --- OTP --------------------------------------------------------------- */
  function setupOtp(root, options) {
    var stage = root.querySelector('[data-otp-stage]');
    var inputs = Array.prototype.slice.call(root.querySelectorAll('[data-otp] input'));
    var noticeEl = root.querySelector('[data-notice]');
    var timerEl = root.querySelector('[data-otp-timer]');
    var resendBtn = root.querySelector('[data-otp-resend]');
    if (!inputs.length) return null;

    var locked = false;

    function value() { return inputs.map(function (i) { return i.value; }).join(''); }

    function reset() {
      locked = false;
      stage.classList.remove('is-verifying', 'is-done');
      inputs.forEach(function (i) { i.value = ''; i.classList.remove('is-filled'); i.disabled = false; });
      inputs[0].focus();
    }

    function submit() {
      if (locked) return;
      locked = true;
      inputs.forEach(function (i) { i.disabled = true; });
      // The morph starts the moment the last digit lands, so the wait is the
      // animation rather than a frozen form.
      stage.classList.add('is-verifying');
      notice(noticeEl, 'info', '');

      post(options.verifyUrl, { code: value() }).then(function (res) {
        if (res.status === 419 || (res.data && res.data.restart)) {
          window.location.reload();
          return;
        }
        if (!res.data || !res.data.ok) {
          stage.classList.remove('is-verifying');
          notice(noticeEl, 'bad', (res.data && res.data.message) || 'That code could not be checked.');
          reset();
          return;
        }
        stage.classList.add('is-done');
        setTimeout(function () { options.onVerified(); }, 900);
      }).catch(function () {
        stage.classList.remove('is-verifying');
        notice(noticeEl, 'bad', 'Network problem. Please try again.');
        reset();
      });
    }

    inputs.forEach(function (input, index) {
      input.addEventListener('input', function () {
        // Keep one digit per box even when a phone keyboard sends more.
        var digits = input.value.replace(/\D/g, '');
        input.value = digits.slice(-1);
        input.classList.toggle('is-filled', input.value !== '');

        if (input.value && index < inputs.length - 1) inputs[index + 1].focus();
        if (value().length === inputs.length) submit();
      });

      input.addEventListener('keydown', function (event) {
        if (event.key === 'Backspace' && !input.value && index > 0) {
          event.preventDefault();
          inputs[index - 1].value = '';
          inputs[index - 1].classList.remove('is-filled');
          inputs[index - 1].focus();
        }
        if (event.key === 'ArrowLeft' && index > 0) { event.preventDefault(); inputs[index - 1].focus(); }
        if (event.key === 'ArrowRight' && index < inputs.length - 1) { event.preventDefault(); inputs[index + 1].focus(); }
      });

      // Pasting the whole code should just work.
      input.addEventListener('paste', function (event) {
        event.preventDefault();
        var text = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        if (!text) return;
        inputs.forEach(function (box, i) {
          box.value = text[i] || '';
          box.classList.toggle('is-filled', box.value !== '');
        });
        (inputs[Math.min(text.length, inputs.length - 1)] || inputs[0]).focus();
        if (value().length === inputs.length) submit();
      });
    });

    /* --- Resend countdown ------------------------------------------------ */
    var remaining = 0;
    var ticker = null;

    function tick() {
      remaining -= 1;
      if (remaining <= 0) {
        clearInterval(ticker);
        ticker = null;
        if (timerEl) timerEl.textContent = '';
        if (resendBtn) { resendBtn.disabled = false; resendBtn.textContent = 'Resend code'; }
        return;
      }
      if (timerEl) timerEl.textContent = 'Resend in ' + remaining + 's';
    }

    function startCountdown(seconds) {
      remaining = seconds;
      if (resendBtn) { resendBtn.disabled = true; }
      if (timerEl) timerEl.textContent = 'Resend in ' + remaining + 's';
      if (ticker) clearInterval(ticker);
      ticker = setInterval(tick, 1000);
    }

    if (resendBtn) {
      resendBtn.addEventListener('click', function (event) {
        event.preventDefault();
        resendBtn.disabled = true;
        post(options.resendUrl, {}).then(function (res) {
          if (res.status === 419 || (res.data && res.data.restart)) { window.location.reload(); return; }
          notice(noticeEl, res.data && res.data.ok ? 'ok' : 'bad', (res.data && res.data.message) || '');
          if (res.data && res.data.ok) { reset(); startCountdown(options.cooldown); }
          else { resendBtn.disabled = false; }
        });
      });
    }

    return { reset: reset, startCountdown: startCountdown };
  }

  /* --- Sign-up ----------------------------------------------------------- */
  var signup = document.querySelector('[data-flow="signup"]');
  if (signup) {
    var signupNotice = signup.querySelector('[data-notice]');
    var emailEcho = signup.querySelector('[data-otp-target]');
    var otp = setupOtp(signup, {
      verifyUrl: signup.dataset.verifyUrl,
      resendUrl: signup.dataset.resendUrl,
      cooldown: Number(signup.dataset.cooldown || 30),
      onVerified: function () { showStep(signup, 3); },
    });

    var startForm = signup.querySelector('[data-step="1"] form');
    if (startForm) {
      startForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = startForm.querySelector('button[type="submit"]');
        notice(signupNotice, 'info', '');

        var payload = {
          name: startForm.name.value.trim(),
          mobile: startForm.mobile.value.trim(),
          email: startForm.email.value.trim(),
          role: (startForm.querySelector('input[name="role"]:checked') || {}).value,
          accepted_terms: startForm.accepted_terms.checked ? 1 : 0,
        };

        if (!payload.role) { notice(signupNotice, 'bad', 'Choose what you do on Vidlix.'); return; }
        if (!payload.accepted_terms) { notice(signupNotice, 'bad', 'Please read and accept the terms for your role.'); return; }

        busy(button, true);
        post(startForm.action, payload).then(function (res) {
          busy(button, false);
          if (!res.data || !res.data.ok) {
            var message = (res.data && res.data.message) || 'Please check the details above.';
            if (res.data && res.data.errors) {
              message = Object.values(res.data.errors)[0][0];
            }
            notice(signupNotice, 'bad', message);
            return;
          }
          if (emailEcho) emailEcho.textContent = res.data.email;
          notice(signupNotice, 'ok', res.data.message);
          showStep(signup, 2);
          if (otp) { otp.reset(); otp.startCountdown(Number(signup.dataset.cooldown || 30)); }
        }).catch(function () {
          busy(button, false);
          notice(signupNotice, 'bad', 'Network problem. Please try again.');
        });
      });
    }

    // Resuming a half-finished sign-up after a refresh.
    var resumeStep = Number(signup.dataset.step || 1);
    if (resumeStep > 1 && otp) otp.startCountdown(Number(signup.dataset.cooldown || 30));
    showStep(signup, resumeStep);
  }

  /* --- Forgot password --------------------------------------------------- */
  var forgot = document.querySelector('[data-flow="forgot"]');
  if (forgot) {
    var forgotNotice = forgot.querySelector('[data-notice]');
    var forgotEcho = forgot.querySelector('[data-otp-target]');
    var forgotOtp = setupOtp(forgot, {
      verifyUrl: forgot.dataset.verifyUrl,
      resendUrl: forgot.dataset.resendUrl,
      cooldown: Number(forgot.dataset.cooldown || 30),
      onVerified: function () { showStep(forgot, 3); },
    });

    var forgotForm = forgot.querySelector('[data-step="1"] form');
    if (forgotForm) {
      forgotForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = forgotForm.querySelector('button[type="submit"]');
        busy(button, true);

        post(forgotForm.action, { login: forgotForm.login.value.trim() }).then(function (res) {
          busy(button, false);
          if (!res.data || !res.data.ok) {
            notice(forgotNotice, 'bad', (res.data && res.data.message) || 'Please try again.');
            return;
          }
          if (forgotEcho) forgotEcho.textContent = res.data.masked || 'your inbox';
          notice(forgotNotice, 'ok', res.data.message);
          showStep(forgot, 2);
          if (forgotOtp) { forgotOtp.reset(); forgotOtp.startCountdown(Number(forgot.dataset.cooldown || 30)); }
        }).catch(function () {
          busy(button, false);
          notice(forgotNotice, 'bad', 'Network problem. Please try again.');
        });
      });
    }

    var forgotResume = Number(forgot.dataset.step || 1);
    if (forgotResume > 1 && forgotOtp) forgotOtp.startCountdown(Number(forgot.dataset.cooldown || 30));
    showStep(forgot, forgotResume);
  }

  /* --- Plain form loading states ----------------------------------------- */
  document.querySelectorAll('form[data-loading]').forEach(function (form) {
    form.addEventListener('submit', function () {
      busy(form.querySelector('button[type="submit"]'), true);
    });
  });
})();
