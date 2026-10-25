{{--
    The one Brainy Clan email frame (README "Emails"): every mail is a Markdown mailable rendered
    through <x-mail::message> and this layout, with the "brand" theme inlined. The <style> below is
    not inlined: mobile width and a cheap dark mode for clients that honour prefers-color-scheme.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ config('app.name') }}</title>
<style>
@media only screen and (max-width: 600px) {
.inner-body,
.footer {
width: 100% !important;
}

.content-cell {
padding: 28px 22px !important;
}
}

@media (prefers-color-scheme: dark) {
body,
.wrapper,
.body {
background-color: #0f1626 !important;
}

.inner-body {
background-color: #172138 !important;
border-color: #2a3654 !important;
}

h1,
h2,
h3,
p,
li,
td,
.header-name {
color: #eef2f8 !important;
}

a {
color: #8db8ff !important;
}

a.button-primary {
color: #1b2a4a !important;
}

.footer p,
.subcopy p {
color: #b3bdd0 !important;
}

.panel-content {
background-color: #3a2a1a !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%">
<table class="inner-body" align="center" width="560" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
