<footer class="site-footer">
    <div class="wrap footer-grid">
        <div>
            <a class="footer-brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
            <p class="footer-intro">{{ __('The production desk for creators, editors, and brands — from discovery to settlement.') }}</p>
            <div class="footer-social" aria-label="{{ __('Social') }}">
                <a href="{{ config('vidlix.social.instagram') }}" rel="noopener noreferrer" target="_blank" aria-label="{{ __('Instagram') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm5 4.8A4.2 4.2 0 1 0 16.2 12 4.2 4.2 0 0 0 12 7.8zm0 6.9A2.7 2.7 0 1 1 14.7 12 2.7 2.7 0 0 1 12 14.7zM17.6 6.5a1 1 0 1 0 1 1 1 1 0 0 0-1-1z"/></svg>
                </a>
                <a href="{{ config('vidlix.social.youtube') }}" rel="noopener noreferrer" target="_blank" aria-label="{{ __('YouTube') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 12.2s0-3.2-.4-4.6a3 3 0 0 0-2.1-2.1C18.9 5 12 5 12 5s-6.9 0-8.5.5A3 3 0 0 0 1.4 7.6C1 9 1 12.2 1 12.2s0 3.2.4 4.6a3 3 0 0 0 2.1 2.1C5.1 19.4 12 19.4 12 19.4s6.9 0 8.5-.5a3 3 0 0 0 2.1-2.1c.4-1.4.4-4.6.4-4.6zM9.8 15.5v-6.6l6.3 3.3z"/></svg>
                </a>
                <a href="{{ config('vidlix.social.x') }}" rel="noopener noreferrer" target="_blank" aria-label="{{ __('X') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 10.3 22 2h-2.2l-6.3 7.2L8.3 2H2l7.7 11.1L2 22h2.2l6.8-7.8L15.7 22H22zm-2.4 2.7-.8-1.1L5.1 3.5h2.6l5.1 7.3.8 1.1 7.2 10.3h-2.6z"/></svg>
                </a>
                <a href="{{ config('vidlix.social.linkedin') }}" rel="noopener noreferrer" target="_blank" aria-label="{{ __('LinkedIn') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 9H4V20h2.5zM5.2 4A1.6 1.6 0 1 0 5.2 7.2 1.6 1.6 0 0 0 5.2 4zM20 20h-2.5v-5.4c0-1.5-.5-2.5-1.8-2.5a1.9 1.9 0 0 0-1.8 1.3 2.5 2.5 0 0 0-.1.9V20H11V9h2.4v1.5A3 3 0 0 1 16 9c2.3 0 4 1.5 4 4.7z"/></svg>
                </a>
            </div>
            <a class="footer-cta" href="{{ \App\Support\Host::urlFor('app', 'register') }}">{{ __('Join Vidlix') }}</a>
        </div>
        <div>
            <h3>{{ __('Marketplace') }}</h3>
            <ul>
                <li><a href="{{ route('creators.index') }}">{{ __('Creators') }}</a></li>
                <li><a href="{{ route('editors.index') }}">{{ __('Editors') }}</a></li>
                <li><a href="{{ route('brands.index') }}">{{ __('Brands') }}</a></li>
                <li><a href="{{ route('campaigns.index') }}">{{ __('Campaigns') }}</a></li>
                <li><a href="{{ route('pricing') }}">{{ __('Pricing') }}</a></li>
                <li><a href="{{ \App\Support\Host::urlFor('autodm') }}">{{ __('Instagram AutoDM') }}</a></li>
                <li><a href="{{ route('pages.show', 'how-it-works') }}">{{ __('How it works') }}</a></li>
                <li><a href="{{ route('pages.show', 'for-creators') }}">{{ __('For creators') }}</a></li>
                <li><a href="{{ route('pages.show', 'for-editors') }}">{{ __('For editors') }}</a></li>
                <li><a href="{{ route('pages.show', 'for-brands') }}">{{ __('For brands') }}</a></li>
            </ul>
        </div>
        <div>
            <h3>{{ __('Company') }}</h3>
            <ul>
                <li><a href="{{ route('pages.show', 'about') }}">{{ __('About') }}</a></li>
                <li><a href="{{ route('blog.index') }}">{{ __('Journal') }}</a></li>
                <li><a href="{{ route('pages.show', 'careers') }}">{{ __('Careers') }}</a></li>
                <li><a href="{{ route('pages.show', 'press') }}">{{ __('Press') }}</a></li>
                <li><a href="{{ route('pages.show', 'security') }}">{{ __('Trust & security') }}</a></li>
                <li><a href="{{ route('pages.show', 'contact') }}">{{ __('Contact') }}</a></li>
                <li><a href="{{ route('pages.show', 'faq') }}">{{ __('FAQ') }}</a></li>
                <li><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
                <li><a href="{{ \App\Support\Host::urlFor('app', 'register') }}">{{ __('Join') }}</a></li>
                <li><a href="{{ \App\Support\Host::urlFor('app', 'login') }}">{{ __('Login') }}</a></li>
            </ul>
        </div>
        <div>
            <h3>{{ __('Workspace') }}</h3>
            <ul>
                <li><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
                <li><a href="{{ route('inbox') }}">{{ __('Inbox') }}</a></li>
                <li><a href="{{ route('app.campaigns') }}">{{ __('My campaigns') }}</a></li>
                <li><a href="{{ route('app.applications') }}">{{ __('Applications') }}</a></li>
                <li><a href="{{ route('app.projects') }}">{{ __('Projects') }}</a></li>
                <li><a href="{{ route('app.invoices') }}">{{ __('Invoices') }}</a></li>
                <li><a href="{{ route('app.earnings') }}">{{ __('Earnings') }}</a></li>
                <li><a href="{{ route('app.tickets') }}">{{ __('Support') }}</a></li>
                <li><a href="{{ route('app.settings') }}">{{ __('Settings') }}</a></li>
            </ul>
        </div>
        <div>
            <h3>{{ __('Legal') }}</h3>
            <ul>
                <li><a href="{{ route('pages.show', 'terms') }}">{{ __('Terms & conditions') }}</a></li>
                <li><a href="{{ route('pages.show', 'privacy') }}">{{ __('Privacy policy') }}</a></li>
                <li><a href="{{ route('pages.show', 'cookie') }}">{{ __('Cookie policy') }}</a></li>
                <li><a href="{{ route('pages.show', 'refund') }}">{{ __('Refund policy') }}</a></li>
                <li><a href="{{ route('pages.show', 'dispute-policy') }}">{{ __('Dispute policy') }}</a></li>
                <li><a href="{{ route('pages.show', 'creator-terms') }}">{{ __('Creator terms') }}</a></li>
                <li><a href="{{ route('pages.show', 'brand-terms') }}">{{ __('Brand terms') }}</a></li>
                <li><a href="{{ route('pages.show', 'editor-terms') }}">{{ __('Editor terms') }}</a></li>
                <li><a href="{{ route('pages.show', 'community') }}">{{ __('Community guidelines') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="wrap footer-bottom">
        <p>© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved worldwide.') }}</p>
        <nav class="footer-bottom-links" aria-label="{{ __('Legal') }}">
            <a href="{{ route('pages.show', 'terms') }}">{{ __('Terms') }}</a>
            <a href="{{ route('pages.show', 'privacy') }}">{{ __('Privacy policy') }}</a>
            <a href="{{ route('pages.show', 'cookie') }}">{{ __('Cookies') }}</a>
            <a href="{{ route('pages.show', 'refund') }}">{{ __('Refunds') }}</a>
            <a href="{{ route('pricing') }}">{{ __('Pricing') }}</a>
        </nav>
    </div>
</footer>
