@props(['user', 'variant' => 'project', 'project' => null, 'edit' => null])

{{--
    Avatar.molecule.js (Confirmed): a 56px (`w-14 h-14`) circle, `border-2
    border-steel-700`, `bg-steel-700` behind the image. Real avatar images
    (docs/rewrite/master-roadmap.md, "Slice 8") replace the initial-on-grey
    fallback the second slice shipped as a temporary ui-avatars.com
    replacement (docs/rewrite/intentional-changes.md) whenever the user has
    one — the fallback itself is unchanged and still used when they don't.

    `variant="settings"`: an edit-pencil overlay opens the avatar dialog, but
    only for a non-social account (`!isSocialAccount`, Confirmed) — an
    account linked to Google has its picture kept in sync from there
    (BUG-004) and was never offered a manual "replace avatar" control.
    `variant="project"` shows "Aktualisiert am <date>" instead of the name's
    own second line, matching the project-detail author block.
--}}
@php
    $avatarUrl = $user->avatarUrl();
    $editable = $variant === 'settings' && ! $user->isSocialAccount();
@endphp
<div {{ $attributes->class(['flex items-center']) }}>
    {{-- The 56px image sits inside the 2px border, so the circle is 60px across. --}}
    <div class="relative flex-shrink-0 overflow-hidden border-2 rounded-full border-steel-700 bg-steel-700">
        @if ($avatarUrl)
            <img
                src="{{ $avatarUrl }}"
                alt="Avatar"
                data-test="img_avatar"
                class="object-cover w-14 h-14 {{ $editable ? 'opacity-30' : '' }}"
            />
        @else
            <div
                class="flex items-center justify-center w-14 h-14 uppercase"
                {{-- ui-avatars.com `size=128&font-size=0.6`: the initial is 0.6 of the circle, regular weight. --}}
                style="background-color: #cfd8dc; color: #37474f; font-size: 33.6px; line-height: 1"
                aria-hidden="true"
            >{{ mb_substr($user->name, 0, 1) }}</div>
        @endif

        @if ($editable)
            <button
                type="button"
                data-test="btn_edit-avatar_settings-page"
                aria-label="Avatar bearbeiten"
                title="Avatar bearbeiten"
                x-on:click="{{ $edit }}"
                class="absolute p-3 transition-transform duration-150 ease-out transform scale-100 outline-none cursor-pointer text-steel-100 left-1 top-1 hover:scale-110"
            ><x-icon name="edit-3" /></button>
        @endif
    </div>
    <div class="ml-5">
        <x-text data-test="username_avatar" variant="textSmMedium">{{ \Illuminate\Support\Str::limit($user->name, 33, '...') }}</x-text>
        @if ($variant === 'project')
            <x-text variant="textSm">Aktualisiert am {{ $project->updated_at->format('j.n.Y') }}</x-text>
        @else
            {{-- Own account only (`variant="settings"`) — never rendered for
                 another user's avatar, matching the historical "View email:
                 Allowed (own only)" rule (docs/security/authorization-matrix.md). --}}
            <x-text variant="textSm">{{ \Illuminate\Support\Str::limit($user->email, 33, '...') }}</x-text>
        @endif
    </div>
</div>
