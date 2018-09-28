// Classe para fazer as requisições
// Uso: 
//
// Ajax.requestData = {dado: 'dado', etc: 'etc', id: 0, campos: ['oi', 'xau']}; <-- (opcional) Se for enviar nada não precisa setar esse campo.
// Ajax.async = false; <-- (opcional) Diz se é assincrono ou não, default é true, se não for querer uma requisição sincrona nao precisa setar.
// var teste = Ajax.post('http://site.com'); <-- também pode ser get, put ou delete no lugar do post
// var teste = Ajax.get('http://site.com', function() { console.log('acabou')}); <-- também pode definir um callback para quando a requisicao terminar de executar
// teste.isDone <-- se já acabou ou nao a requisicao
// teste.percentage <-- % atual de andamento da requisição
// teste.successful <-- booleano, indica se acabou com erro ou nao
// teste.response <-- objeto de retorno da requisição com tudo que tem direito


class Ajax {

	constructor() {
		this.percentage = 0;
		this.successful = false;
		this.isDone = false;
	}

	static makeRequest($url, $method = "GET", $callback = null) {
		var requisicao = new Ajax();
		$.ajax({
			url: $url,
			type: $method,
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			async: Ajax.async !== undefined ? Ajax.async : true,
			data: Ajax.requestData !== undefined ? Ajax.requestData : null,
			xhr : function() {
	            var xhr = new window.XMLHttpRequest();
	            xhr.upload.addEventListener("progress", function(evt) {
	                if (evt.lengthComputable) {
	                    var percentComplete = (evt.loaded / evt.total) * 100;
	                }
	            }, false);

	            xhr.addEventListener("progress", function(evt) {
	                if (evt.lengthComputable) {
	                    var percentComplete = (evt.loaded / evt.total) * 100;
	                    requisicao.percentage = percentComplete;   
	                }
	            }, false);

	            return xhr;
	        },
	        complete: function(response) {
	        	requisicao.response = response;
	        }
		})
		.done(function() {
			requisicao.successful = true;
		})
		.fail(function() {
			requisicao.successful = false;
		})
		.always(function(data) {
			Ajax.requestData = null;
			Ajax.async = true;
			requisicao.data = data;
			requisicao.percentage = 100;
			requisicao.isDone = true;
			if(data.successful !== undefined && !data.successful) requisicao.successful = false;
			if (typeof $callback === 'function') $callback(requisicao);
		});
		return requisicao;
	}

	static post($url, $callback) {
		return Ajax.makeRequest($url, "POST", $callback);
	}

	static get($url, $callback) {
		return Ajax.makeRequest($url, "GET", $callback);
	}

	static put($url, $callback) {
		return Ajax.makeRequest($url, "PUT", $callback);
	}

	static delete($url, $callback) {
		return Ajax.makeRequest($url, "DELETE", $callback);
	}
}	