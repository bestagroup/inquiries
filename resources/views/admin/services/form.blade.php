@extends('layouts.base')
@section('title', $service->exists ? 'ویرایش سرویس' : 'تعریف سرویس جدید')
@section('page-title', $service->exists ? 'ویرایش سرویس' : 'تعریف سرویس جدید')

@section('content')
@php
    $inputRows = old('inputs', $inputs->map(fn ($field) => [
        'key' => $field->key, 'label' => $field->label, 'type' => $field->type->value,
        'is_required' => (int) $field->is_required, 'validation_rules' => implode('|', (array) $field->validation_rules),
        'default_value' => $field->default_value, 'options' => implode("\n", (array) $field->options),
        'is_sensitive' => (int) $field->is_sensitive,
    ])->values()->all());
    $outputRows = old('outputs', $outputs->map(fn ($field) => [
        'key' => $field->key, 'label' => $field->label, 'type' => $field->type->value, 'json_path' => $field->json_path,
    ])->values()->all());
    $serviceHeaders = collect($service->headers ?? [])->reject(fn ($value, $key) => strtolower((string) $key) === 'authorization')->all();
    $headers = old('headers_json', $service->exists ? json_encode($serviceHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}');
@endphp

<form method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}" class="service-editor">
    @csrf
    @if($service->exists) @method('PUT') @endif

    <header class="page-heading service-editor-heading">
        <div>
            <a class="page-back-link" href="{{ route('admin.services.index') }}"><i class="bi bi-arrow-right"></i> بازگشت به سرویس‌ها</a>
            <h1>{{ $service->exists ? 'ویرایش «'.$service->name.'»' : 'تعریف سرویس جدید' }}</h1>
            <p>اطلاعات نمایشی و نحوه اتصال سرویس را مشخص کنید؛ سپس ورودی‌ها و خروجی‌های مورد نیاز را بسازید.</p>
        </div>
        <div class="page-heading-actions">
            <a class="btn btn-outline-secondary" href="{{ route('admin.services.index') }}">انصراف</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> ذخیره سرویس</button>
        </div>
    </header>

    <div class="service-editor-layout">
        <aside class="service-editor-nav" aria-label="بخش‌های فرم">
            <div class="service-editor-nav-title">مراحل تعریف سرویس</div>
            <a href="#service-general"><span>۱</span><div><strong>مشخصات اصلی</strong><small>عنوان و نمایش در کاتالوگ</small></div></a>
            <a href="#service-connection"><span>۲</span><div><strong>اتصال به API</strong><small>Endpoint و فرمت ارتباط</small></div></a>
            <a href="#service-policy"><span>۳</span><div><strong>سیاست اجرا</strong><small>Timeout و محدودیت‌ها</small></div></a>
            <a href="#service-inputs"><span>۴</span><div><strong>ورودی‌ها</strong><small>فیلدهای فرم استعلام</small></div></a>
            <a href="#service-outputs"><span>۵</span><div><strong>خروجی‌ها</strong><small>نگاشت نتیجه سرویس</small></div></a>
            <div class="service-editor-tip"><i class="bi bi-lightbulb"></i><p>فیلدهای دارای ستاره اجباری‌اند. کد سرویس پس از اتصال مشتریان بهتر است تغییر نکند.</p></div>
        </aside>

        <main class="service-editor-content">
            <section class="editor-section" id="service-general">
                <div class="editor-section-heading"><span><i class="bi bi-window"></i></span><div><h2>مشخصات اصلی</h2><p>اطلاعاتی که کاربر در کاتالوگ سرویس‌ها مشاهده می‌کند.</p></div></div>
                <div class="row g-3">
                    <div class="col-lg-8"><label class="form-label">عنوان فارسی <b>*</b></label><input class="form-control" name="name" value="{{ old('name', $service->name) }}" placeholder="مثلاً استعلام اطلاعات هویتی" required></div>
                    <div class="col-lg-4"><label class="form-label">وضعیت انتشار <b>*</b></label><select class="form-select" name="is_active"><option value="1" @selected((string) old('is_active', $service->exists ? (int) $service->is_active : 1) === '1')>فعال و قابل استفاده</option><option value="0" @selected((string) old('is_active', $service->exists ? (int) $service->is_active : 1) === '0')>غیرفعال</option></select></div>
                    <div class="col-lg-6"><label class="form-label">کد یکتای سرویس <b>*</b></label><input class="form-control" dir="ltr" name="slug" value="{{ old('slug', $service->slug) }}" placeholder="identity-inquiry" required><div class="form-text">فقط حروف انگلیسی، عدد و خط تیره؛ این کد برای یکپارچه‌سازی استفاده می‌شود.</div></div>
                    <div class="col-lg-3"><label class="form-label">دسته‌بندی</label><input class="form-control" name="category" value="{{ old('category', $service->category) }}" placeholder="احراز هویت"></div>
                    <div class="col-lg-3"><label class="form-label">آیکن</label><div class="icon-input"><span id="icon-preview"><i class="bi {{ old('icon', $service->icon ?: 'bi-hdd-network') }}"></i></span><input class="form-control" dir="ltr" name="icon" id="service-icon" list="service-icons" value="{{ old('icon', $service->icon) }}" placeholder="bi-person-check"></div><datalist id="service-icons"><option value="bi-person-check"><option value="bi-person-vcard"><option value="bi-bank"><option value="bi-buildings"><option value="bi-shield-check"><option value="bi-car-front"><option value="bi-house-check"></datalist></div>
                    <div class="col-12"><label class="form-label">توضیح کوتاه</label><textarea class="form-control" rows="3" name="description" placeholder="کاربرد و نتیجه این سرویس را کوتاه و روشن توضیح دهید.">{{ old('description', $service->description) }}</textarea></div>
                </div>
            </section>

            <section class="editor-section" id="service-connection">
                <div class="editor-section-heading"><span><i class="bi bi-link-45deg"></i></span><div><h2>اتصال به API</h2><p>نشانی مقصد و قرارداد ارسال و دریافت اطلاعات.</p></div></div>
                <div class="row g-3">
                    <div class="col-xl-8"><label class="form-label">Endpoint URL <b>*</b></label><input class="form-control" dir="ltr" name="endpoint_url" value="{{ old('endpoint_url', $service->endpoint_url) }}" placeholder="https://api.example.com/v1/inquiry" required></div>
                    <div class="col-sm-4 col-xl-4"><label class="form-label">HTTP Method <b>*</b></label><select class="form-select" name="http_method">@foreach(['GET', 'POST', 'PUT', 'PATCH'] as $method)<option @selected(old('http_method', $service->http_method?->value ?? 'POST') === $method)>{{ $method }}</option>@endforeach</select></div>
                    <div class="col-sm-6"><label class="form-label">نحوه ارسال داده <b>*</b></label><select class="form-select" name="payload_mode">@foreach(['json' => 'JSON Body', 'form' => 'Form URL Encoded', 'query' => 'Query String'] as $key => $label)<option value="{{ $key }}" @selected(old('payload_mode', $service->payload_mode?->value ?? 'json') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-sm-6"><label class="form-label">فرمت پاسخ <b>*</b></label><select class="form-select" name="response_format">@foreach(['json' => 'JSON', 'text' => 'Text', 'xml' => 'XML'] as $key => $label)<option value="{{ $key }}" @selected(old('response_format', $service->response_format?->value ?? 'json') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">Headerهای اختصاصی</label><textarea class="form-control font-monospace editor-code-input" dir="ltr" rows="6" name="headers_json" spellcheck="false">{{ $headers }}</textarea><div class="form-text"><i class="bi bi-shield-lock"></i> Authorization از «توکن سرویس‌ها» اعمال می‌شود؛ اینجا فقط Headerهای اختصاصی را به شکل JSON وارد کنید.</div></div>
                </div>
            </section>

            <section class="editor-section" id="service-policy">
                <div class="editor-section-heading"><span><i class="bi bi-speedometer2"></i></span><div><h2>سیاست اجرا</h2><p>مقادیر پیش‌فرض برای اغلب سرویس‌ها مناسب‌اند.</p></div></div>
                <div class="row g-3">
                    <div class="col-6 col-lg-4"><label class="form-label">مهلت پاسخ</label><div class="input-group"><input class="form-control" type="number" min="1" max="120" name="timeout_seconds" value="{{ old('timeout_seconds', $service->timeout_seconds ?? 15) }}"><span class="input-group-text">ثانیه</span></div></div>
                    <div class="col-6 col-lg-4"><label class="form-label">مهلت اتصال</label><div class="input-group"><input class="form-control" type="number" min="1" max="30" name="connect_timeout_seconds" value="{{ old('connect_timeout_seconds', $service->connect_timeout_seconds ?? 5) }}"><span class="input-group-text">ثانیه</span></div></div>
                    <div class="col-6 col-lg-4"><label class="form-label">تعداد تلاش مجدد</label><input class="form-control" type="number" min="0" max="5" name="retry_times" value="{{ old('retry_times', $service->retry_times ?? 1) }}"></div>
                    <div class="col-6 col-lg-4"><label class="form-label">فاصله تلاش‌ها</label><div class="input-group"><input class="form-control" type="number" min="0" max="10000" name="retry_delay_ms" value="{{ old('retry_delay_ms', $service->retry_delay_ms ?? 200) }}"><span class="input-group-text">ms</span></div></div>
                    <div class="col-6 col-lg-4"><label class="form-label">محدودیت در دقیقه</label><input class="form-control" type="number" min="1" max="10000" name="rate_limit_per_minute" value="{{ old('rate_limit_per_minute', $service->rate_limit_per_minute ?? 60) }}"></div>
                    <div class="col-6 col-lg-4"><label class="form-label">ترتیب نمایش</label><input class="form-control" type="number" min="0" name="sort_order" value="{{ old('sort_order', $service->sort_order ?? 0) }}"></div>
                    <div class="col-12"><label class="form-check editor-check"><input type="hidden" name="allow_resubmit" value="0"><input class="form-check-input" type="checkbox" name="allow_resubmit" value="1" @checked((bool) old('allow_resubmit', $service->exists ? $service->allow_resubmit : true))><span><strong>اجازه اجرای مجدد درخواست</strong><small>کاربر می‌تواند همان استعلام را بدون ورود دوباره اطلاعات اجرا کند.</small></span></label></div>
                </div>
            </section>

            <section class="editor-section" id="service-inputs">
                <div class="editor-section-heading with-action"><span><i class="bi bi-input-cursor-text"></i></span><div><h2>ورودی‌های سرویس</h2><p>فیلدهایی که کاربر هنگام ثبت استعلام تکمیل می‌کند.</p></div><button class="btn btn-sm btn-outline-primary" type="button" id="add-input"><i class="bi bi-plus-lg"></i> افزودن ورودی</button></div>
                <div id="input-rows" class="field-builder"></div>
            </section>

            <section class="editor-section" id="service-outputs">
                <div class="editor-section-heading with-action"><span><i class="bi bi-braces"></i></span><div><h2>خروجی‌های سرویس</h2><p>فیلدهای مهم پاسخ را برای نمایش خوانا به کاربر نگاشت کنید.</p></div><button class="btn btn-sm btn-outline-primary" type="button" id="add-output"><i class="bi bi-plus-lg"></i> افزودن خروجی</button></div>
                <div id="output-rows" class="field-builder"></div>
            </section>

            <div class="service-editor-submit"><div><strong>آماده ذخیره‌سازی است؟</strong><span>پس از ذخیره، سرویس فعال در حساب کاربران تخصیص‌یافته نمایش داده می‌شود.</span></div><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> ذخیره سرویس</button></div>
        </main>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputRows = @json($inputRows);
    const outputRows = @json($outputRows);
    const inputBox = document.getElementById('input-rows');
    const outputBox = document.getElementById('output-rows');
    const escape = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
    const types = {text: 'متن', number: 'عدد', boolean: 'بله / خیر', date: 'تاریخ', select: 'فهرست انتخاب'};
    const typeOptions = selected => Object.entries(types).map(([value, label]) => `<option value="${value}" ${selected === value ? 'selected' : ''}>${label}</option>`).join('');

    const emptyState = (kind, title, text) => `<div class="field-builder-empty"><i class="bi ${kind === 'input' ? 'bi-ui-radios-grid' : 'bi-braces'}"></i><strong>${title}</strong><span>${text}</span></div>`;
    const inputRow = (value = {}, index) => `<div class="field-builder-row" data-kind="input">
        <div class="field-builder-row-head"><div><span>${index + 1}</span><strong>${escape(value.label) || 'ورودی جدید'}</strong></div><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="bi bi-trash3"></i> حذف</button></div>
        <div class="row g-3">
            <div class="col-md-6 col-xl-3"><label class="form-label">عنوان نمایشی</label><input class="form-control" name="inputs[${index}][label]" value="${escape(value.label)}" placeholder="کد ملی" required></div>
            <div class="col-md-6 col-xl-3"><label class="form-label">کلید API</label><input class="form-control" dir="ltr" name="inputs[${index}][key]" value="${escape(value.key)}" placeholder="national_id" required></div>
            <div class="col-md-6 col-xl-3"><label class="form-label">نوع فیلد</label><select class="form-select" name="inputs[${index}][type]">${typeOptions(value.type || 'text')}</select></div>
            <div class="col-md-6 col-xl-3"><label class="form-label">قوانین اعتبارسنجی</label><input class="form-control" dir="ltr" name="inputs[${index}][validation_rules]" value="${escape(value.validation_rules)}" placeholder="required|digits:10"></div>
            <div class="col-md-6"><label class="form-label">مقدار پیش‌فرض</label><input class="form-control" name="inputs[${index}][default_value]" value="${escape(value.default_value)}"></div>
            <div class="col-md-6"><label class="form-label">گزینه‌ها <small>(برای فهرست انتخاب)</small></label><input class="form-control" name="inputs[${index}][options]" value="${escape(value.options)}" placeholder="گزینه اول، گزینه دوم"></div>
            <div class="col-12 field-builder-checks"><label><input type="hidden" name="inputs[${index}][is_required]" value="0"><input class="form-check-input" type="checkbox" name="inputs[${index}][is_required]" value="1" ${Number(value.is_required) ? 'checked' : ''}> اجباری باشد</label><label><input type="hidden" name="inputs[${index}][is_sensitive]" value="0"><input class="form-check-input" type="checkbox" name="inputs[${index}][is_sensitive]" value="1" ${Number(value.is_sensitive) ? 'checked' : ''}> مقدار حساس و مخفی است</label></div>
        </div>
    </div>`;
    const outputRow = (value = {}, index) => `<div class="field-builder-row" data-kind="output">
        <div class="field-builder-row-head"><div><span>${index + 1}</span><strong>${escape(value.label) || 'خروجی جدید'}</strong></div><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="bi bi-trash3"></i> حذف</button></div>
        <div class="row g-3">
            <div class="col-md-6 col-xl-3"><label class="form-label">عنوان نمایشی</label><input class="form-control" name="outputs[${index}][label]" value="${escape(value.label)}" placeholder="نام و نام خانوادگی" required></div>
            <div class="col-md-6 col-xl-3"><label class="form-label">کلید داخلی</label><input class="form-control" dir="ltr" name="outputs[${index}][key]" value="${escape(value.key)}" placeholder="full_name" required></div>
            <div class="col-md-6 col-xl-3"><label class="form-label">نوع مقدار</label><select class="form-select" name="outputs[${index}][type]">${typeOptions(value.type || 'text')}</select></div>
            <div class="col-md-6 col-xl-3"><label class="form-label">مسیر در پاسخ</label><input class="form-control" dir="ltr" name="outputs[${index}][json_path]" value="${escape(value.json_path)}" placeholder="data.person.name"></div>
        </div>
    </div>`;

    const render = () => {
        inputBox.innerHTML = inputRows.length ? inputRows.map(inputRow).join('') : emptyState('input', 'هنوز ورودی تعریف نشده است', 'برای ساخت فرم استعلام، اولین ورودی را اضافه کنید.');
        outputBox.innerHTML = outputRows.length ? outputRows.map(outputRow).join('') : emptyState('output', 'خروجی مشخصی تعریف نشده است', 'در این حالت پاسخ کامل سرویس ذخیره خواهد شد.');
    };

    document.getElementById('add-input').addEventListener('click', () => { inputRows.push({type: 'text', is_required: 1}); render(); inputBox.lastElementChild?.scrollIntoView({behavior: 'smooth', block: 'center'}); });
    document.getElementById('add-output').addEventListener('click', () => { outputRows.push({type: 'text'}); render(); outputBox.lastElementChild?.scrollIntoView({behavior: 'smooth', block: 'center'}); });
    document.addEventListener('click', event => {
        const button = event.target.closest('.remove-row');
        if (!button) return;
        const row = button.closest('.field-builder-row');
        const list = row.dataset.kind === 'input' ? inputRows : outputRows;
        const box = row.dataset.kind === 'input' ? inputBox : outputBox;
        list.splice([...box.querySelectorAll('.field-builder-row')].indexOf(row), 1);
        render();
    });

    const iconInput = document.getElementById('service-icon');
    iconInput?.addEventListener('input', () => {
        const valid = /^bi-[a-z0-9-]+$/.test(iconInput.value) ? iconInput.value : 'bi-hdd-network';
        document.querySelector('#icon-preview i').className = `bi ${valid}`;
    });
    render();
});
</script>
@endpush
