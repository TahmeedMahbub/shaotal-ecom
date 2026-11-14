Thanks for your order, {!! $firstName !!}.

We have order {!! $number !!} and we are getting it ready. It was placed on {!! $placedOn !!}.

PAYMENT: {!! $payment['status'] !!}
@foreach ($payment['lines'] as $line)
{!! $line !!}
@endforeach
@if ($gift)

THIS ORDER IS A GIFT
{!! $gift['count'] !!} {!! $gift['count'] === 1 ? 'item' : 'items' !!} will be gift wrapped ({!! $gift['fee'] !!}):
@foreach ($gift['items'] as $g)
- {!! $g !!}
@endforeach
@if ($gift['message'] !== '')
Your gift message: "{!! $gift['message'] !!}"
@endif
@endif

YOUR ITEMS
@foreach ($items as $i)
- {!! $i['name'] !!}{!! $i['variant'] !== '' ? ' ('.$i['variant'].')' : '' !!}, qty {!! $i['qty'] !!} x {!! $i['unit'] !!} = {!! $i['total'] !!}
@endforeach

@foreach ($rows as [$label, $value, $strong])
{!! $label !!}: {!! $value !!}
@endforeach

{!! strtoupper($deliveryHeading) !!}
@foreach ($address as $line)
{!! $line !!}
@endforeach
@if ($phone)
{!! $phone !!}
@endif
{!! $deliveryNote !!}

View your order (sign in required): {!! $orderUrl !!}

If something here looks wrong, just reply to this email and we will sort it out.

{!! $brand !!}
