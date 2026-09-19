{{-- FramedGridCard.Header: `className` lands on both the outer grid and the inner column, as historically. --}}
<div {{ $attributes->class(['grid grid-cols-12 gap-2 py-6 lg:py-8 rounded-t-lg']) }}>
    <div {{ $attributes->only('class')->class(['col-span-12 lg:col-span-10 lg:col-start-2']) }}>
        {{ $slot }}
    </div>
</div>
