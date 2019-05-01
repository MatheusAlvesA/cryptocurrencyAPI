<?php

header('Access-Control-Allow-Origin: *'); // Permitindo acesso de qualquer domínio
// Obtendo cotação atual do dolar em reais
$cotacao = floatval(json_decode(file_get_contents('https://economia.awesomeapi.com.br/USD'), true)[0]['high']);

if(array_key_exists('search', $_GET)) { // Rquisição de nome das moedas
	search();
} else {								// Requisição de uma moeda específica
	if(!array_key_exists('moeda', $_GET)) // A moeda não foi informada
		retornarJSON(['erro' => 'Informe a moeda'], 400); // Bad Request

	moeda($_GET['moeda']);
}

/*
	Retorna via Rest uma lista das moedas disponíveis
*/
function search() {
	// Obtendo lista completa da API do coinmarketcap
	$bruto = file_get_contents('https://s2.coinmarketcap.com/generated/search/quick_search.json');
	$dados = json_decode($bruto, true); // Parseando JSON

	for($i = 0; $i < count($dados); $i++) { // Removendo info desnecessária
		unset($dados[$i]['tokens']);
	}

	retornarJSON($dados);
}

/*
	Retorna via Rest infos a respeito da moeda solicitada
*/
function moeda(String $moeda) {
	$ch = curl_init('https://graphs2.coinmarketcap.com/currencies/'.$moeda); // iniciando curl

	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Siga os redirecionamentos que o servidor solicitar
	curl_setopt($ch, CURLOPT_RETURNTRANSFER , true); // Retorne os dados ao executar

	$dados = curl_exec($ch); // Executando e obtendo dados retornados
	$resCode = curl_getinfo($ch, CURLINFO_HTTP_CODE ); // Obtendo código http de retorno

	if($resCode !== 200 || $dados === false) { // Se o código http não for 200(ok) ou não foram retornados dados
		curl_close($ch);
		retornarJSON(['erro' => 'Moeda não encontrada ('.$resCode.')'], $resCode); // Retorne erro
	}

	curl_close($ch);
	$dados = processarMoeda(json_decode($dados, true)); // Processando dados obtidos

	retornarJSON($dados);
}

/*
	Esta função consolida o retorno da API
*/
function retornarJSON($dados, $cod = 200) {
	header('Content-Type: application/json'); // Retorno do tipo Json
	http_response_code($cod); // Setando Código HTTP de retorno
	echo json_encode($dados); // Codificando dados e retornando

	exit; // Nada mais deve ser executado após isso
}

/*
	Processa os dados obtidos a respeito de uma moeda
	retorna a cotação da moeda convertida e BRL
*/
function processarMoeda($dados) {
	global $cotacao; // Obtendo cotação do escopo global
	$price = [];
	foreach ($dados['price_usd'] as $chave => $valor) { // Percorrendo valor em USD
		array_push($price, [
			$valor[0],
			$cotacao * $valor[1] // Convertendo USD -> BRL
		]);
	}
	$dados['price'] = $price;

	$volume = [];
	foreach ($dados['volume_usd'] as $chave => $valor) {  // Percorrendo volume em USD captalizado
		array_push($volume, [
			$valor[0],
			$cotacao * $valor[1] // Convertendo USD -> BRL
		]);
	}
	$dados['volume'] = $volume;

	// Removendo infos desnecessárias
	unset($dados['price_usd']);
	unset($dados['volume_usd']);
	unset($dados['market_cap_by_available_supply']);

	return $dados;
}

?>