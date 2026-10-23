# CNPJ numérico e alfanumérico

Na versão 1.16.36, a máscara **CNPJ** aceita números e letras A–Z nas primeiras 12 posições e dois dígitos verificadores numéricos. A formatação permanece `AA.AAA.AAA/AAAA-00`. Letras minúsculas são convertidas para maiúsculas na digitação e no salvamento do pedido.

No editor de campos, use **Tipo: Texto** e **Máscara: CNPJ**. Os campos padrão/importados já usam essa combinação; não é necessário recriá-los. Não configure como campo numérico, pois letras e zeros iniciais fazem parte do identificador.

A validação no servidor aceita valores com a máscara completa ou sem pontuação e calcula os dois dígitos pelo módulo 11, convertendo cada caractere por `ASCII - 48`. CNPJs numéricos válidos continuam aceitos; preenchimentos inválidos agora geram erro. Campos opcionais vazios e campos ocultos por condição não são exigidos. O CPF não muda.

Não há migração nem alteração de pedidos anteriores. A validação verifica formato e dígitos, não a existência/situação cadastral da empresa. Gateways, plugins fiscais e outros plugins de campos precisam oferecer sua própria compatibilidade: esta alteração não remove validações de terceiros.

Referência oficial e exemplo `12.ABC.345/01DE-35`: [Receita Federal — cálculo do DV](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj).

QA: `php vendor/bin/phpunit --testsuite unit --filter 'FieldValidatorTest|FieldSanitizerTest|FieldOrderPersisterTest|ParticipantsTest'` e `node --test tests/checkout-cnpj.test.cjs`.

Validação local: 41 testes PHP / 188 asserções passaram; os 4 testes JS/de navegador de CNPJ e participantes passaram, incluindo digitação e envio nos dois layouts. Sintaxe PHP, PHPStan dos arquivos alterados, sniffs de segurança e compatibilidade PHP 7.4 passaram. A execução mais ampla com filtro `Field` manteve a falha preexistente `SettingsSchemaTest::test_sanitize_text_fields_strips_tags` (71 testes, 368 asserções).
