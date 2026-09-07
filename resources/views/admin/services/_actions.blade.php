<div class="table-actions">
    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.services.edit', $service) }}"><i class="bi bi-pencil"></i> ویرایش</a>
    <form method="POST" action="{{ route('admin.services.destroy', $service) }}" onsubmit="return confirmDelete('سرویس حذف شود؟ سوابق قبلی حفظ می‌شوند.')">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger" aria-label="حذف {{ $service->name }}"><i class="bi bi-trash3"></i></button>
    </form>
</div>
