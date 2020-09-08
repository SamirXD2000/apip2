<div style="width: 100%; background-color: #fff; text-align: center">

	<div style="text-align: center; padding-top: 20px; padding-bottom: 20px;"> 
		<a href="https://www.arces.mx"><img src="{{ $message->embed('images/mailing/logo.png') }}" height="30"></a>
	</div>

	<p style="color:#949494; font-size: 14; font-family: Arial">¡Hola!</p>

	<p style="color:#FF7400; font-size: 14; font-family: Arial; text-decoration: none">{{ $data["email"] }}</p>

	<p style="color:#949494; font-size: 14; font-family: Arial">Confirma tu correo en el siguiente enlace y<br> participa en los <strong>mejores proyectos inmobiliarios</strong>.</p><br>

	<a href="https://www.arces.mx/users/confirmemail?code={{ $data["code_confirmation"] }}"><img src="{{ $message->embed('images/mailing/emailConfirmButton.png') }}" height="48"></a>
	<br><br><br>
	<img src="{{ $message->embed('images/mailing/buildings.png') }}" height="400">
	
	<p style="color:#949494; font-size: 14; font-family: Arial; text-decoration: none">Correo: <span style="color:#214283; font-size: 14; font-family: Arial">contacto@arces.com</span></p>
		<p style="color:#949494; font-size: 14; font-family: Arial; text-decoration: none">Teléfono: <span style="color:#214283; font-size: 14; font-family: Arial"> (999) 454 18 58</span></p>
	<div style="width:100%; background-color: #214283; padding-top: 3px; padding-bottom: 3px"><a href="https://www.arces.mx"><img src="{{ $message->embed('images/mailing/logoSmall.png') }}" height="10"></a></div>

</div>
