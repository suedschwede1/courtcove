<div class="auth-box">
    @if($errors->any())
        <div class="error-message">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(session('error'))
        <div class="error-message">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf
        @if(!empty($redirectTo))
            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
        @endif

        <label class="auth-label" for="email">{{ __('booking.auth.email') }}</label>
        <input type="email" name="email" id="email"
               value="{{ old('email') }}"
               class="auth-input"
               required autofocus>

        <label class="auth-label" for="password">{{ __('booking.auth.password') }}</label>
        <input type="password" name="password" id="password"
               class="auth-input"
               required>

        <label class="auth-checkbox">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
            <span>{{ __('booking.auth.remember') }}</span>
        </label>

        <button type="submit" class="default-button auth-submit">{{ __('booking.nav.login') }}</button>
    </form>
</div>
