{{-- Honeypot field - hidden from users but visible to bots --}}
<div style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
    <label for="website_url">Website URL (leave empty)</label>
    <input type="text" 
           name="website_url" 
           id="website_url" 
           value="" 
           autocomplete="off"
           tabindex="-1">
</div>

<div style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
    <label for="email_confirmation">Email Confirmation (leave empty)</label>
    <input type="email" 
           name="email_confirmation" 
           id="email_confirmation" 
           value="" 
           autocomplete="off"
           tabindex="-1">
</div>

{{-- Time-based protection --}}
<input type="hidden" name="form_token" value="{{ csrf_token() }}">
<input type="hidden" name="form_start_time" value="{{ time() }}">