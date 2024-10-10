@props(['configs', 'files'])

<div x-data="{ configs: @json($configs ?: [[]]), existing: false }">
    <label>
        <input type="checkbox" x-model="existing"> Показать существующие конфиги
    </label>

    <template x-for="(config, index) in configs" :key="index">
        <div class="config-item mb-1">
            <h4 class="d-flex justify-content-between">
                <div>
                    Конфиг №<span x-text="index + 1" />
                </div>

                <button type="button" class="btn btn-danger" @click="configs.splice(index, 1)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </h4>
            <div class="form-group">
                <label :for="'name' + index">Имя *</label>
                <template x-if="existing">
                    <select :name="`configs[${index}][name]`" :id="'name' + index" x-model="config.name" class="form-control" required>
                        @foreach ($files as $file)
                            <option value="{{ $file }}">{{ $file }}</option>
                        @endforeach
                    </select>
                </template>
                <template x-if="!existing">
                    <input :name="`configs[${index}][name]`" :id="'name' + index" x-model="config.name" class="form-control" required>
                </template>
            </div>
            <div class="form-group">
                <label :for="'description' + index">Описание</label>
                <textarea :name="`configs[${index}][description]`" :id="'description' + index" class="form-control" x-model="config.description"></textarea>
            </div>
        </div>
    </template>

    <button type="button" class="btn btn-primary mb-3" @click="configs.push({})">
        Добавить
    </button>
</div>
