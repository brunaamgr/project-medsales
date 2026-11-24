# Módulo Operação

> **Cards:** OP01, OP02 (parcial) · 24/11/2026

## Análise do legado

Fonte: interface pública de `operacaopinhabel.medsalesbr.com` e `operacao.mychelleamaral.com.br` (telas, rótulos, guia da operação).
Nenhuma API foi chamada e nenhum dado de paciente foi lido. O painel da Vercel não foi acessado: o conector não tem acesso ao time.

| Área | Pinhabel | Mychelle | No SaaS |
|---|---|---|---|
| Agenda de consultas | sim | sim | **pronta** |
| Pacientes (cadastro na hora) | sim | sim | **pronto** |
| Cadastros (médicos, locais, tipos) | 15 listas | 12 listas | **6 listas da agenda** |
| Operadoras e permissões | 18 chaves por pessoa | sim | perfis e Equipe no próprio cliente (ADR 0007) |
| Auditoria | sim | — | core |
| Agenda cirúrgica + Google Calendar | sim | — | em breve |
| Fechamento de cirurgia | sim | dentro do painel | em breve |
| Venda de produto / protocolo | sim | no painel central | em breve |
| Injetáveis e estoque por lote | sim | sim | em breve |
| Entradas, saídas, parcelas, NF | sim | sim | em breve |
| Orçamentos | — | sim | em breve |
| Exames | — | sim | em breve |
| Painel central e resumo financeiro | sim | sim | em breve |
| Contratos (ZapSign) | sim | — | em breve |

### Por que os campos precisam ser do cliente

| Campo da agenda | Pinhabel | Mychelle |
|---|---|---|
| Paciente, data da consulta, tipo de atendimento | sim | sim |
| Data do agendamento | obrigatória | opcional |
| Horário, duração | não tem | sim |
| Consultora | obrigatória | não tem |
| Profissional | obrigatório | opcional |
| Local de atendimento | sim | não tem |
| Procedimento | não tem | sim |
| Valor | obrigatório | opcional |
| Reserva | opcional, com valor e origem | obrigatória |
| Nota fiscal e status da NF | não tem | obrigatória |
| Pacote vinculado | não tem | sim |
| Observação | não tem | sim |

## Como a personalização funciona

1. **Campos padrão** (13): o cliente mostra, oculta, renomeia, ordena e torna obrigatório.
2. **Campos criados**: até 30, de 8 tipos. O tipo não muda depois.
3. **Listas**: cada cliente tem os próprios profissionais, tipos, locais, convênios e procedimentos.
4. **Modelos**: Essencial, Consulta com reserva (base Pinhabel) e Agenda com horário e duração (base Mychelle).

Pode ser feito pela plataforma (`/clientes/{id}/operacao/campos`) ou pelo cliente (`/operacao/campos`, permissões `operacao.campo.*`).
Excluir campo: só Super Adm do cliente (`operacao.campo.delete`, reservada no manifesto) e administração da plataforma. Sai do formulário, os valores ficam e pode ser restaurado em "Campos excluídos". Apagar de vez só vale para campo criado que nunca foi preenchido. Modelos prontos não trazem campo excluído de volta.
Campos fixos: paciente, data e status. Nenhuma regra depende de qual é o cliente.

Garantias: campo oculto é ignorado no envio e mantém o valor gravado; opção ou item desativado continua válido no que já existe.

## Ainda não migrado do legado

Consultora, nota fiscal, pacote, origem do pagamento e divisão em várias formas podem ser criados como campos simples hoje. As **regras** deles (gerar entrada, parcelas, comissão) chegam com o financeiro.
