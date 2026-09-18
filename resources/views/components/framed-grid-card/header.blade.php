<div {{ $attributes->class(['grid grid-cols-12 gap-2 py-6 lg:py-8']) }}>
    <div class="col-span-12 lg:col-span-10 lg:col-start-2">
        {{ $slot }}
    </div>
</div>
