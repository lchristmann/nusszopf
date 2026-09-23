@props(['name'])

{{--
    `InputGroup.molecule.js` over an `Input` (docs/design/components.md,
    "InputGroup" — Listed, not pixel-read in full): every historical password
    field pairs with an Eye/EyeOff toggle inside its right edge
    (`LoginForm.js`, `SignUpForm.js`, `PasswordForm.js`). Positioning here is a
    reasonable, documented reproduction of that pattern (right-inset icon
    button, vertically centered), not a pixel-exact trace — no component
    archaeology pass measured `InputGroup`'s own padding/icon geometry.
--}}
<div class="relative" x-data="{ visible: false }">
    <x-input
        :type="'password'"
        x-bind:type="visible ? 'text' : 'password'"
        name="{{ $name }}"
        class="pr-11"
        {{ $attributes }}
    />
    <button
        type="button"
        x-on:click="visible = ! visible"
        x-bind:aria-label="visible ? 'Passwort verbergen' : 'Passwort anzeigen'"
        class="absolute inset-y-0 right-0 flex items-center px-3 text-current"
        tabindex="-1"
    >
        <span x-show="! visible"><x-icon name="eye-off" :size="24" /></span>
        <span x-show="visible" x-cloak><x-icon name="eye" :size="24" /></span>
    </button>
</div>
