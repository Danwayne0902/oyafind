<h1>Verify your OyaFind email</h1>

<p>Hello {{ $user->name }},</p>

<p>Confirm this email address to activate your OyaFind account. The link expires in {{ $expirationMinutes }} minutes.</p>

<p><a href="{{ $verificationUrl }}">Verify my email</a></p>

<p>If you did not request an OyaFind account, you can ignore this email.</p>
