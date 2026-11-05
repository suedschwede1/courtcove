<x-ui.card>
    <div class="grid gap-4 md:grid-cols-2">
        <x-ui.field :label="__('members::admin.fiscal_years.name')" for="fy-name" :error="$errors->first('name')">
            <x-ui.input name="name" :value="old('name', $fiscalYear->name ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.fiscal_years.starts_on')" for="fy-starts-on" :error="$errors->first('starts_on')">
            <x-ui.input type="date" name="starts_on" :value="old('starts_on', optional($fiscalYear->starts_on ?? null)->format('Y-m-d'))" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.fiscal_years.ends_on')" for="fy-ends-on" :error="$errors->first('ends_on')">
            <x-ui.input type="date" name="ends_on" :value="old('ends_on', optional($fiscalYear->ends_on ?? null)->format('Y-m-d'))" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.fiscal_years.due_date')" for="fy-faellig-am" :error="$errors->first('faellig_am')">
            <x-ui.input type="date" name="faellig_am" :value="old('faellig_am', optional($fiscalYear->faellig_am ?? null)->format('Y-m-d'))" />
        </x-ui.field>
    </div>
</x-ui.card>
