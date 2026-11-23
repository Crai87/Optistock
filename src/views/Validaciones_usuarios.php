<?php

function validar_nombre($name) {
    $resultado;
    if(!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{3,12}$/", $name)){
        $resultado = 'false';
    } else {
        $resultado = 'true';
    }
    return $resultado;
}

function validar_alias($name) {
    $resultado;
    if(!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{3,12}$/", $name)){
        $resultado = 'false';
    } else {
        $resultado = 'true';
    }
    return $resultado;
}

function validar_apellido($last_name) {
    $resultado;
    if(!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{3,12}$/", $last_name)){
        $resultado = 'false';
    } else {
        $resultado = 'true';
    }
    return $resultado;
}

function validar_contraseña($pass) {
    $resultado;
    if(!preg_match("/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,16}$/", $pass)){
        $resultado = 'false';
    } else {
        $resultado = 'true';
    }
    return $resultado;
}

/*$texto = $_REQUEST["Validaciones"];
$h = validar_telefono($texto);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Titulo</h1>
    <form method = "POST" action=""><input type="text" name = "Validaciones">
    <button type="submit">enviar</button>
</form>
    <?php 
    echo $h;
    ?>
</body>
</html>*/