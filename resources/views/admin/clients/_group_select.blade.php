<form action="{{ route('clients.addToGroups') }}" method="POST" id="groupSelectForm">
    @csrf
    <div class="d-flex align-items-center gap-2 mb-2">
        <select name="group_id" class="form-select form-select-sm w-auto" required>
            <option value="">Selecione o grupo</option>
            @foreach (\App\Models\Group::orderBy('name')->get() as $group)
                <option value="{{ $group->id }}">{{ $group->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Adicionar selecionados</button>
    </div>
    <input type="hidden" name="client_ids" id="selectedClientIds">
</form>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('groupSelectForm');
        form.addEventListener('submit', function (e) {
            const checked = Array.from(document.querySelectorAll('.client-checkbox:checked')).map(cb => cb.value);
            document.getElementById('selectedClientIds').value = checked.join(',');
            if (checked.length === 0) {
                e.preventDefault();
                alert('Selecione pelo menos um cliente!');
            }
        });
    });
</script>