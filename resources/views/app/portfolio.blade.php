@extends('layouts.app')
@section('title', __('Portfolio'))
@section('content')

<h1>{{ __('Portfolio') }}</h1>
<p class="muted">{{ __('The work you want people to see first. Drag the order into shape — this is what appears on your public page.') }}</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

@forelse($items as $item)
    <article class="card" style="margin-bottom:12px">
        <form class="form" method="post" action="{{ route('app.portfolio.update', $item) }}">
            @csrf

            <label for="title-{{ $item->id }}">{{ __('Title') }}</label>
            <input id="title-{{ $item->id }}" name="title" value="{{ $item->title }}" required maxlength="160">

            <label for="url-{{ $item->id }}">{{ __('Link') }} <span class="muted">{{ __('(optional)') }}</span></label>
            <input id="url-{{ $item->id }}" name="url" value="{{ $item->url }}" maxlength="2000" placeholder="https://">

            <label for="desc-{{ $item->id }}">{{ __('Description') }}</label>
            <textarea id="desc-{{ $item->id }}" name="description" maxlength="2000">{{ $item->description }}</textarea>

            @if($item->storage_key)
                <p class="muted">{{ __('A file is attached to this item.') }}</p>
            @endif

            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn secondary" type="submit">{{ __('Save') }}</button>

                {{-- Its own form, outside the one above: a delete nested inside
                     the save form would submit the save instead. --}}
                <button class="btn secondary" type="submit" form="remove-{{ $item->id }}"
                        onclick="return confirm('{{ __('Remove this item? The file goes with it.') }}')">
                    {{ __('Remove') }}
                </button>
            </div>
        </form>
    </article>
@empty
    @include('partials.state', [
        'state' => 'empty',
        'detail' => __('Nothing here yet. The first thing you add is the first thing people will see.'),
    ])
@endforelse

@foreach($items as $item)
    <form id="remove-{{ $item->id }}" method="post" action="{{ route('app.portfolio.destroy', $item) }}" class="hp">
        @csrf @method('DELETE')
    </form>
@endforeach

@if($items->count() > 1)
    <h2>{{ __('Order') }}</h2>
    <form method="post" action="{{ route('app.portfolio.reorder') }}">
        @csrf
        {{-- A plain list of selects rather than drag-and-drop: it works without
             JavaScript, on a phone, and with a keyboard, which drag-and-drop
             on its own does not. --}}
        <ol class="form">
            @foreach($items as $index => $item)
                <li>
                    <label class="sr-only" for="order-{{ $item->id }}">{{ $item->title }}</label>
                    <select id="order-{{ $item->id }}" name="order[]">
                        @foreach($items as $position => $candidate)
                            <option value="{{ $items[$position]->id }}" @selected($position === $index)>
                                {{ $position + 1 }}. {{ \Illuminate\Support\Str::limit($candidate->title, 40) }}
                            </option>
                        @endforeach
                    </select>
                </li>
            @endforeach
        </ol>
        <button class="btn secondary" type="submit">{{ __('Save order') }}</button>
    </form>
@endif

<h2>{{ __('Add something') }}</h2>
<form class="form" method="post" action="{{ route('app.portfolio.store') }}" enctype="multipart/form-data">
    @csrf

    <label for="new-title">{{ __('Title') }}</label>
    <input id="new-title" name="title" required maxlength="160">

    <label for="new-url">{{ __('Link') }} <span class="muted">{{ __('(optional)') }}</span></label>
    <input id="new-url" name="url" maxlength="2000" placeholder="https://">

    <label for="new-desc">{{ __('Description') }}</label>
    <textarea id="new-desc" name="description" maxlength="2000"></textarea>

    <label for="new-file">{{ __('Image or video') }} <span class="muted">{{ __('(optional)') }}</span></label>
    <input id="new-file" name="file" type="file" accept="image/*,video/*">
    <p class="muted">{{ __('Images and video only. The file is checked by its contents, not its name.') }}</p>

    <button class="btn" type="submit">{{ __('Add to portfolio') }}</button>
</form>
@endsection
