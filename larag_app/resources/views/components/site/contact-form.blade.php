@props(['topics' => [], 'recaptchaEnabled' => false, 'recaptchaSiteKey' => '', 'id' => 'hiremium-contact-form'])
{{-- Shared contact form: the contact page and the home page render this same
     form, posting to the same AJAX endpoint with the same behaviour. --}}
<form id="{{ $id }}" data-contact-form method="POST" action="{{ route('contact.send') }}"
      {{ $attributes->merge(['class' => 'bg-white rounded-2xl ring-1 ring-slate-200 p-6 sm:p-8 space-y-5']) }}>
    @csrf

    {{-- Success / error banner --}}
    <div data-contact-alert class="hidden rounded-lg px-4 py-3 text-sm"></div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="{{ $id }}-name">Name</label>
            <input id="{{ $id }}-name" name="name" type="text" required maxlength="100"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <p data-error="name" class="hidden mt-1 text-xs text-red-600"></p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="{{ $id }}-email">Email</label>
            <input id="{{ $id }}-email" name="email" type="email" required maxlength="150"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <p data-error="email" class="hidden mt-1 text-xs text-red-600"></p>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="{{ $id }}-topic">Topic</label>
            <select id="{{ $id }}-topic" name="topic" required
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="" disabled selected>Choose a topic…</option>
                @foreach ($topics as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <p data-error="topic" class="hidden mt-1 text-xs text-red-600"></p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="{{ $id }}-subject">Subject</label>
            <input id="{{ $id }}-subject" name="subject" type="text" required maxlength="150"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <p data-error="subject" class="hidden mt-1 text-xs text-red-600"></p>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700" for="{{ $id }}-message">How can we help?</label>
        <textarea id="{{ $id }}-message" name="message" rows="5" required maxlength="3000"
                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
        <p data-error="message" class="hidden mt-1 text-xs text-red-600"></p>
    </div>

    @if ($recaptchaEnabled)
        <div>
            <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
            <p data-error="g-recaptcha-response" class="hidden mt-1 text-xs text-red-600"></p>
        </div>
    @endif

    <button type="submit" data-contact-submit
            class="inline-flex items-center justify-center rounded-md bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition">
        <span data-submit-label>Send message</span>
    </button>

    <p class="text-xs text-slate-500">
        For account or billing issues, please email from the address registered to your Hiremium account.
    </p>
</form>

@once
    @if ($recaptchaEnabled)
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]');

            document.querySelectorAll('form[data-contact-form]').forEach(function (form) {
                if (form.dataset.contactBound) return;
                form.dataset.contactBound = '1';

                var submitBtn = form.querySelector('[data-contact-submit]');
                var submitLabel = form.querySelector('[data-submit-label]');
                var alertBox = form.querySelector('[data-contact-alert]');

                function clearErrors() {
                    form.querySelectorAll('[data-error]').forEach(function (el) {
                        el.textContent = '';
                        el.classList.add('hidden');
                    });
                    alertBox.classList.add('hidden');
                    alertBox.textContent = '';
                }

                function showAlert(message, ok) {
                    alertBox.textContent = message;
                    alertBox.className = 'rounded-lg px-4 py-3 text-sm ' + (ok
                        ? 'bg-emerald-50 ring-1 ring-emerald-200 text-emerald-800'
                        : 'bg-red-50 ring-1 ring-red-200 text-red-800');
                }

                function showErrors(errors) {
                    Object.keys(errors).forEach(function (field) {
                        var el = form.querySelector('[data-error="' + field + '"]');
                        if (el) {
                            el.textContent = errors[field][0];
                            el.classList.remove('hidden');
                        }
                    });
                }

                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    clearErrors();

                    submitBtn.disabled = true;
                    submitLabel.textContent = 'Sending…';

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
                        },
                        body: new FormData(form)
                    }).then(function (response) {
                        return response.json().then(function (data) {
                            return { status: response.status, data: data };
                        });
                    }).then(function (result) {
                        if (result.status === 200 && result.data.ok) {
                            showAlert(result.data.message, true);
                            form.reset();
                            if (typeof grecaptcha !== 'undefined') { try { grecaptcha.reset(); } catch (err) {} }
                        } else if (result.status === 422) {
                            showAlert('Please fix the highlighted fields.', false);
                            showErrors(result.data.errors || {});
                            if (typeof grecaptcha !== 'undefined') { try { grecaptcha.reset(); } catch (err) {} }
                        } else if (result.status === 429) {
                            showAlert('Too many messages. Please wait a moment and try again.', false);
                        } else {
                            showAlert(result.data.message || 'Something went wrong. Please try again.', false);
                        }
                    }).catch(function () {
                        showAlert('Network error. Please try again.', false);
                    }).finally(function () {
                        submitBtn.disabled = false;
                        submitLabel.textContent = 'Send message';
                    });
                });
            });
        })();
    </script>
@endonce
