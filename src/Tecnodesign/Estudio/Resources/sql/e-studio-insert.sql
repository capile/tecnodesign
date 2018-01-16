insert into tdz_entry (id, created, title, summary, link, published, expired, language, type, master) values 
(1, now(), 'Página inicial', 'Página inicial de exemplo', '/', now(), null, 'pt-br', 'page', null);

insert into tdz_content (id, created, entry, slot, content_type, content, position, published, show_at, hide_at) values
(1, now(), 1, 'body', 'html', 'html: <h1>Esta é sua pagina inicial.</h1><p>Use os controles da ferramenta para criar novos contezdos e atualizar o contezdo existente.</p>', 1, now(), null, null),
(2, now(), 1, 'header', 'widget', 'app: signin', 1, now(), '*', null);
