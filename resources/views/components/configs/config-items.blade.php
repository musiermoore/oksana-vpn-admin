@props(['configs', 'files'])

<div x-data="{ configs: @json($configs ?: [[]]) }">
    <template x-for="(config, index) in configs" :key="index">
        <div class="config-item mb-1">
            <h4 class="d-flex justify-content-between">
                <div>
                    Config <span x-text="index + 1" />
                </div>

                <button type="button" class="btn btn-danger" @click="configs.splice(index, 1)">
                    Remove
                </button>
            </h4>
            <div class="form-group">
                <label :for="'name' + index">Name *</label>
                <select :name="`configs[${index}][name]`" :id="'name' + index" x-model="config.name" class="form-control" required>
                    @foreach ($files as $file)
                        <option value="{{ $file }}">{{ $file }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label :for="'description' + index">Description</label>
                <textarea :name="`configs[${index}][description]`" :id="'description' + index" class="form-control" x-model="config.description"></textarea>
            </div>
        </div>
    </template>

    <button type="button" class="btn btn-primary mb-3" @click="configs.push({})">
        Add Config Name
    </button>
</div>
