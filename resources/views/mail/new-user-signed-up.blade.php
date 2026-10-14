<x-mail::message>
# New sign-up

**{{ $user->name }}** has just signed up to {{ config('app.name') }}.

<x-mail::table>
| | |
|:--|:--|
| Name | {{ $user->name }} |
| Email | {{ $user->email }} |
| Signed up | {{ $signedUpAt->setTimezone('Europe/London')->format('D j M Y, H:i T') }} |
| Users now | {{ $totalUsers }} |
@if ($country)
| Country | {{ $country }} |
@endif
@foreach ($source as $key => $value)
| {{ $key === 'referrer' ? 'Referrer' : $key }} | {{ $value }} |
@endforeach
</x-mail::table>
</x-mail::message>
