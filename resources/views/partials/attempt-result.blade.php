@if($attempt->status->value === 'running')
    <div class="text-muted d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span> ارتباط با سرویس مقصد در حال انجام است.</div>
@else
    @if($attempt->error_message)
        <div class="alert alert-danger">{{ $attempt->error_message }}</div>
    @endif

    @if($attempt->mapped_response !== null)
        @php($mapped = $attempt->mapped_response)
        @if(array_is_list($mapped) && isset($mapped[0]['label']))
            <div class="row g-2">
                @foreach($mapped as $row)
                    @php($value = $row['value'] ?? null)
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ $row['label'] ?? $row['key'] }}</div>
                            <div class="fw-bold text-break">{{ is_bool($value) ? ($value ? 'بله' : 'خیر') : (is_scalar($value) ? ($value === '' ? '-' : $value) : json_encode($value, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <pre class="code-block mb-0">{{ json_encode($mapped, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) }}</pre>
        @endif
    @elseif($attempt->response_raw !== null)
        <pre class="code-block mb-0">{{ $attempt->response_raw }}</pre>
    @elseif(! $attempt->error_message)
        <div class="text-muted">پاسخی برای این اجرا ذخیره نشده است.</div>
    @endif
@endif
