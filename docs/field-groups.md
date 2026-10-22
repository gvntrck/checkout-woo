# Grupos visuais de campos

Na aba **Campos do Formulário**, use **Adicionar grupo**, configure a aparência e selecione o **Grupo visual** nos campos desejados. Clique em **Salvar alterações** para persistir tudo.

- O título é opcional. Um campo pode ficar **Sem grupo**, inclusive entre duas caixas.
- Campos habilitados consecutivos do mesmo grupo e da mesma etapa formam uma caixa. A ordem dos campos continua sendo a ordem do editor. Se outro campo ou etapa interromper a sequência, uma nova caixa é aberta.
- Configure cor, espessura (0–8 px) e estilo da borda (contínua, tracejada, pontilhada ou sem borda), cor do fundo, arredondamento (0–40 px) e espaçamento interno (0–64 px).
- Excluir um grupo preserva os campos, que passam a ficar sem grupo após salvar.
- Caixas sem campos visíveis são ocultadas durante a navegação e a avaliação das condições.
- Funciona nos layouts clássico e split. Sem grupos, nenhum contêiner adicional é renderizado. Nomes, IDs, máscaras, condições e validação dos campos são preservados.

As definições ficam em `gvn_checkout_field_groups`; cada campo possui um `group_id` opcional. Grupos não são dados de pedido e não aceitam grupos aninhados.
