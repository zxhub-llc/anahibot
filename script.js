function validarUsuario() {
	var usuarioAdicional = document.getElementById('ciAdicional').value;
	var usuarioPrincipal = document.getElementById('usuario').value;
	var username = document.getElementById('username').value;
	var password = document.getElementById('password').value;
	var comodin = '[ad]';

	/*document.getElementById('error-mensaje-usuario').innerHTML = '';
	  document.getElementById('error-mensaje-password').innerHTML = '';*/
	// Esconder parametro http://stackoverflow.com/questions/21070101/show-hide-div-using-javascript
	/*if(usuarioPrincipal.length < 1){
		//alert("El campo de usuario no puede estar vac�o.");
		var mensaje = "Necesario el ingreso del No.ID Titular.";
		document.getElementById('error-mensaje-usuario').innerHTML = mensaje;
		return false;
	} else if (password.length < 1) {
		//var mensaje = "Necesario el ingreso de la clave";
		document.getElementById('error-mensaje-password').innerHTML = mensaje;
		return false;
	} */

	if (usuarioAdicional.length > 0) {
		document.getElementById('username').value = usuarioPrincipal.toUpperCase() + comodin + usuarioAdicional;
	} else {
		document.getElementById('username').value = usuarioPrincipal.toUpperCase();
	}
	var shaObj = new jsSHA(password, 'ASCII');
	document.getElementById('password').value = CryptoJS.MD5(password) + shaObj.getHash('SHA-512', 'HEX');

	return true;
}

function soloNumerosLetras(e) {
	var key = window.Event ? e.which : e.keyCode;
	return ((key >= 48 && key <= 57) || (key >= 65 && key <= 90) || (key >= 97 && key <= 122) || (key == 8) || (e.keyCode == 9));
}

function soloNumeros(e) {
	var charCode = (e.which) ? e.which : e.keyCode
	return !(charCode > 31 && (charCode < 48 || charCode > 57));
}
