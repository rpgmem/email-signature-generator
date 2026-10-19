=== Email Signature Generator ===

Um formulário, inserido por shortcode, que monta uma assinatura de e-mail em HTML padronizada para a sua organização.

== Description ==

Todos na organização preenchem o mesmo formulário e recebem uma assinatura com o mesmo layout, pronta para colar no Outlook ou para baixar como imagem.

O plugin vem com o layout da Secretaria Municipal de Educação de São Paulo (SME-SP) como padrão, e um administrador pode adaptá-lo a outra organização.

Uma versão em HTML avulsa, que não precisa do WordPress, é publicada junto do plugin em cada release no GitHub, e a mais recente fica em https://rpgmem.github.io/email-signature-generator/.

== Uso ==

Coloque o shortcode em qualquer página ou post:

`[email_signature]`

Para oferecer só alguns dos modelos, liste-os na ordem em que devem aparecer:

`[email_signature models="staff_school,department"]`

Os modelos são `staff_regional` (servidor em uma diretoria regional), `staff_school` (servidor em uma escola) e `department` (um setor ou um prédio escolar). Com um único modelo, o formulário não pergunta qual.

A assinatura é montada no navegador do visitante: nada do que ele digita é enviado ao site. Os telefones são formatados enquanto são digitados e viram links que discam o número, a prévia pode ser vista no modo escuro, e uma seção recolhível explica como adicionar a assinatura no programa de e-mail que o domínio do endereço usa, Outlook ou Gmail.

== Configurações ==

Configurações → Assinatura de E-mail define, para todos os formulários do site:

* quais modelos são oferecidos, e se só usuários conectados podem usar o formulário;
* se cada navegador pode lembrar o que o usuário digitou, por 60 minutos depois da última assinatura gerada (desligado por padrão);
* quais domínios de e-mail são aceitos: só os listados, mostrados ao lado do campo, ou qualquer endereço, exceto domínios de exemplo como test.com;
* quais instruções de instalação cada domínio de endereço recebe: Outlook, Gmail ou nenhuma;
* a largura máxima do formulário na página (640 px por padrão);
* o logotipo, da biblioteca de mídia (o que acompanha o plugin é o padrão), sua largura e seu texto alternativo;
* o espaço acima da assinatura (4 px por padrão);
* se a imagem pode ser baixada e como isso é oferecido: um botão que recomenda Copiar HTML a cada download, o mesmo dentro de um "Mais opções" recolhido, ou um botão simples;
* o formato da imagem baixada (PNG por padrão, ou JPG com sua qualidade) e a resolução (1×, 2× ou 3×);
* as unidades, cada uma com um nome e uma sigla única, e a unidade em que o formulário começa;
* por campo: o rótulo que o formulário mostra, um valor padrão, se é obrigatório, se fica travado nesse valor e se é impresso em maiúsculas;
* o Google Analytics opcional, com um ID de métricas GA4 (desligado por padrão);
* as cores, os cantos arredondados e a fonte do formulário, para combinar com o site (desligado por padrão; a assinatura mantém o próprio visual);
* o Modo Escuro desta tela de configurações e do formulário no site: desligado, ligado ou seguindo o sistema operacional (desligado por padrão).

Uma aba Documentação, na mesma tela, explica cada configuração, como o formulário se comporta, a privacidade e as atualizações.

As configurações de liga/desliga são chaves que salvam assim que são acionadas, e o Modo Escuro salva assim que muda. Os campos de texto, o logotipo e as listas salvam com "Salvar alterações".

== Privacidade ==

Nada do que é digitado no formulário é enviado ao site, e o plugin não guarda dados pessoais. Com "Lembrar o que foi digitado" ligado, os valores ficam no próprio navegador do visitante e são esquecidos 60 minutos depois da última assinatura gerada, ou na hora, pelo link "Esquecer" do formulário. Deixe desligado onde os computadores são compartilhados.

Por padrão, o formulário não carrega nada de terceiros. Com o Google Analytics ligado (desligado por padrão), as páginas com o formulário carregam a tag do Google, que grava os cookies do Google, e registram quando uma assinatura é gerada, copiada ou baixada, apenas com o modelo e o formato da imagem.

== Installation ==

1. Baixe `email-signature-generator-X.Y.Z.zip` da release mais recente no GitHub.
2. No WordPress, vá em Plugins → Adicionar novo → Enviar plugin, escolha o zip e ative-o.

As atualizações passam a chegar pela tela de atualizações normal do WordPress, direto das Releases do plugin no GitHub. Cada pacote é conferido contra o SHA-256 publicado antes de ser instalado, e o próprio botão do WordPress por plugin liga as atualizações automáticas.

== Screenshots ==

1. O formulário, com uma assinatura gerada a partir de dados fictícios.
2. A prévia da assinatura no modo escuro.
3. Configurações, aba Geral: modelos, acesso, valores lembrados e domínios de e-mail.
4. Configurações, aba Layout: largura do formulário, logotipo e espaçamento.
5. Configurações, aba Imagem baixada.
6. Configurações, aba Unidades, com a unidade em que o formulário começa.
7. Configurações, aba Campos: rótulo, padrão, obrigatório, travado e maiúsculas por campo.
8. Configurações, aba Documentação.
