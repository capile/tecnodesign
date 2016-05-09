# Tipos de Campos (@TODO)

Atualmente a framework comporta os seguintes tipos de campos:

- **string**: campo texto livre
- **textarea**: caixa de texto
- **html**: caixa de texto com converção de conteúdo html
- **number**: número inteiro
- **password**: campo para senhas
- **bool**: campo boleano
- ...
- **[file](#file)**: para upload de arquivos
- **color**: campo para especificação de cor


## File
Os campos de upload de arquivo e imagem tem as seguintes opções:
- **multiple**: _true_ - permite o upload de mais de um arquivo simultaneamente.
- **class**: _"app-file-preview"_ - exibe link para visualização do arquivo; _"app-image-preview"_ - exibe a visualização da imagem.

Sintaxe:
```php
columns => array (
	'imagem' => array ( 'type' => 'file', 'size' => 255, 'null' => true )
) 
... 
form => array(
	'imagem' => array ( 'bind' => 'imagem', 'class' => 'app-image-preview' )
) 
``` 
