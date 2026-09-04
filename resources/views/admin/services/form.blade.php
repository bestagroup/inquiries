@extends('layouts.base')
@section('title',$service->exists?'ویرایش سرویس':'سرویس جدید')
@section('page-title',$service->exists?'ویرایش سرویس':'تعریف سرویس جدید')
@section('content')
@php
    $inputRows = old('inputs', $inputs->map(fn($f)=>[
        'key'=>$f->key,'label'=>$f->label,'type'=>$f->type->value,'is_required'=>(int)$f->is_required,
        'validation_rules'=>implode('|',(array)$f->validation_rules),'default_value'=>$f->default_value,
        'options'=>implode("\n",(array)$f->options),'is_sensitive'=>(int)$f->is_sensitive,
    ])->values()->all());
    $outputRows = old('outputs', $outputs->map(fn($f)=>[
        'key'=>$f->key,'label'=>$f->label,'type'=>$f->type->value,'json_path'=>$f->json_path,
    ])->values()->all());
    $serviceHeaders = collect($service->headers ?? [])
        ->reject(fn($value, $key) => strtolower((string) $key) === 'authorization')
        ->all();
    $headers = old('headers_json', $service->exists ? json_encode($serviceHeaders, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : "{}" );
@endphp
<form method="POST" action="{{ $service->exists ? route('admin.services.update',$service) : route('admin.services.store') }}">
    @csrf @if($service->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-header"><strong>تعریف سرویس</strong></div><div class="card-body"><div class="row g-3">
        <div class="col-md-4"><label class="form-label">نام سرویس</label><input class="form-control" name="name" value="{{ old('name',$service->name) }}" required></div>
        <div class="col-md-4"><label class="form-label">کد سرویس</label><input class="form-control" dir="ltr" name="slug" value="{{ old('slug',$service->slug) }}" placeholder="national-id-inquiry" required></div>
        <div class="col-md-4"><label class="form-label">وضعیت</label><select class="form-select" name="is_active"><option value="1" @selected((string)old('is_active',$service->exists?(int)$service->is_active:1)==='1')>فعال</option><option value="0" @selected((string)old('is_active',$service->exists?(int)$service->is_active:1)==='0')>غیرفعال</option></select></div>
        <div class="col-12"><label class="form-label">توضیحات</label><textarea class="form-control" rows="2" name="description">{{ old('description',$service->description) }}</textarea></div>
        <div class="col-md-8"><label class="form-label">Endpoint URL</label><input class="form-control" dir="ltr" name="endpoint_url" value="{{ old('endpoint_url',$service->endpoint_url) }}" placeholder="https://api.example.com/v1/inquiry" required></div>
        <div class="col-md-4"><label class="form-label">HTTP Method</label><select class="form-select" name="http_method">@foreach(['GET','POST','PUT','PATCH'] as $m)<option @selected(old('http_method',$service->http_method?->value ?? 'POST')===$m)>{{ $m }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">نحوه ارسال Payload</label><select class="form-select" name="payload_mode">@foreach(['json'=>'JSON Body','form'=>'Form URL Encoded','query'=>'Query String'] as $k=>$v)<option value="{{ $k }}" @selected(old('payload_mode',$service->payload_mode?->value ?? 'json')===$k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">فرمت پاسخ</label><select class="form-select" name="response_format">@foreach(['json'=>'JSON','text'=>'Text','xml'=>'XML'] as $k=>$v)<option value="{{ $k }}" @selected(old('response_format',$service->response_format?->value ?? 'json')===$k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">اجازه اجرای مجدد</label><select class="form-select" name="allow_resubmit"><option value="1" @selected((string)old('allow_resubmit',$service->exists?(int)$service->allow_resubmit:1)==='1')>بله</option><option value="0" @selected((string)old('allow_resubmit',$service->exists?(int)$service->allow_resubmit:1)==='0')>خیر</option></select></div>
    </div></div></div>

    <div class="card mb-3"><div class="card-header"><strong>تنظیمات ارتباط</strong></div><div class="card-body"><div class="row g-3">
        <div class="col-lg-6"><label class="form-label">HTTP Headers (JSON)</label><textarea class="form-control font-monospace" dir="ltr" rows="7" name="headers_json">{{ $headers }}</textarea><div class="form-text">فقط Headerهای اختصاصی این سرویس را وارد کنید؛ Authorization از بخش «توکن سرویس‌ها» به‌صورت سراسری اعمال می‌شود.</div></div>
        <div class="col-lg-6"><div class="row g-3"><div class="col-6"><label class="form-label">Timeout (sec)</label><input class="form-control" type="number" min="1" max="120" name="timeout_seconds" value="{{ old('timeout_seconds',$service->timeout_seconds ?? 15) }}"></div><div class="col-6"><label class="form-label">Connect Timeout</label><input class="form-control" type="number" min="1" max="30" name="connect_timeout_seconds" value="{{ old('connect_timeout_seconds',$service->connect_timeout_seconds ?? 5) }}"></div><div class="col-6"><label class="form-label">Retry</label><input class="form-control" type="number" min="0" max="5" name="retry_times" value="{{ old('retry_times',$service->retry_times ?? 1) }}"></div><div class="col-6"><label class="form-label">Retry Delay (ms)</label><input class="form-control" type="number" min="0" max="10000" name="retry_delay_ms" value="{{ old('retry_delay_ms',$service->retry_delay_ms ?? 200) }}"></div><div class="col-6"><label class="form-label">Rate / minute</label><input class="form-control" type="number" min="1" max="10000" name="rate_limit_per_minute" value="{{ old('rate_limit_per_minute',$service->rate_limit_per_minute ?? 60) }}"></div><div class="col-6"><label class="form-label">ترتیب نمایش</label><input class="form-control" type="number" min="0" name="sort_order" value="{{ old('sort_order',$service->sort_order ?? 0) }}"></div></div></div>
    </div></div></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><div><strong>ورودی‌های سرویس</strong><div class="text-muted small">فیلدهای فرم کاربر؛ قوانین مجاز مانند max:20، digits:10، email، numeric و in:A,B</div></div><button class="btn btn-sm btn-outline-primary" type="button" id="add-input"><i class="bi bi-plus"></i> ورودی</button></div><div class="card-body"><div id="input-rows"></div></div></div>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><div><strong>خروجی‌های سرویس</strong><div class="text-muted small">مسیر فیلدها در پاسخ JSON/XML؛ برای مثال data.person.name</div></div><button class="btn btn-sm btn-outline-primary" type="button" id="add-output"><i class="bi bi-plus"></i> خروجی</button></div><div class="card-body"><div id="output-rows"></div></div></div>

    <div class="d-flex gap-2"><button class="btn btn-primary">ذخیره سرویس</button><a class="btn btn-outline-secondary" href="{{ route('admin.services.index') }}">انصراف</a></div>
</form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const inputRows=@json($inputRows), outputRows=@json($outputRows), inputBox=document.getElementById('input-rows'), outputBox=document.getElementById('output-rows');
 const esc=v=>String(v??'').replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;').replaceAll('>','&gt;');
 function inputRow(v={},i){return `<div class="section-box mb-2 field-row"><div class="row g-2 align-items-end"><div class="col-md-2"><label class="form-label">Key</label><input class="form-control" dir="ltr" name="inputs[${i}][key]" value="${esc(v.key)}" required></div><div class="col-md-2"><label class="form-label">عنوان</label><input class="form-control" name="inputs[${i}][label]" value="${esc(v.label)}" required></div><div class="col-md-2"><label class="form-label">نوع</label><select class="form-select" name="inputs[${i}][type]">${['text','number','boolean','date','select'].map(x=>`<option ${v.type===x?'selected':''}>${x}</option>`).join('')}</select></div><div class="col-md-2"><label class="form-label">Validation</label><input class="form-control" dir="ltr" name="inputs[${i}][validation_rules]" value="${esc(v.validation_rules)}" placeholder="max:20|email"></div><div class="col-md-2"><label class="form-label">Default</label><input class="form-control" name="inputs[${i}][default_value]" value="${esc(v.default_value)}"></div><div class="col-md-2 text-end"><button type="button" class="btn btn-outline-danger remove-row">حذف</button></div><div class="col-md-6"><label class="form-label">Options (برای select)</label><input class="form-control" name="inputs[${i}][options]" value="${esc(v.options)}" placeholder="A,B,C"></div><div class="col-md-3 form-check mt-4"><input type="hidden" name="inputs[${i}][is_required]" value="0"><input class="form-check-input" type="checkbox" name="inputs[${i}][is_required]" value="1" ${Number(v.is_required)?'checked':''}><label class="form-check-label">اجباری</label></div><div class="col-md-3 form-check mt-4"><input type="hidden" name="inputs[${i}][is_sensitive]" value="0"><input class="form-check-input" type="checkbox" name="inputs[${i}][is_sensitive]" value="1" ${Number(v.is_sensitive)?'checked':''}><label class="form-check-label">حساس / مخفی</label></div></div></div>`}
 function outputRow(v={},i){return `<div class="section-box mb-2 field-row"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Key</label><input class="form-control" dir="ltr" name="outputs[${i}][key]" value="${esc(v.key)}" required></div><div class="col-md-3"><label class="form-label">عنوان</label><input class="form-control" name="outputs[${i}][label]" value="${esc(v.label)}" required></div><div class="col-md-2"><label class="form-label">نوع</label><select class="form-select" name="outputs[${i}][type]">${['text','number','boolean','date','select'].map(x=>`<option ${v.type===x?'selected':''}>${x}</option>`).join('')}</select></div><div class="col-md-3"><label class="form-label">JSON Path</label><input class="form-control" dir="ltr" name="outputs[${i}][json_path]" value="${esc(v.json_path)}" placeholder="data.result.name"></div><div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger remove-row">×</button></div></div></div>`}
 function render(){inputBox.innerHTML=inputRows.map(inputRow).join('')||'<div class="text-muted small empty-input">ورودی تعریف نشده است.</div>';outputBox.innerHTML=outputRows.map(outputRow).join('')||'<div class="text-muted small empty-output">خروجی تعریف نشده؛ پاسخ کامل ذخیره می‌شود.</div>';}
 document.getElementById('add-input').addEventListener('click',()=>{inputRows.push({type:'text'});render();}); document.getElementById('add-output').addEventListener('click',()=>{outputRows.push({type:'text'});render();});
 document.addEventListener('click',e=>{if(!e.target.classList.contains('remove-row'))return;const row=e.target.closest('.field-row'),parent=row.parentElement,idx=[...parent.children].filter(x=>x.classList.contains('field-row')).indexOf(row);if(parent===inputBox)inputRows.splice(idx,1);else outputRows.splice(idx,1);render();}); render();
});
</script>
@endpush
