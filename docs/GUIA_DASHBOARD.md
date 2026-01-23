# 📊 Dashboard - Guia de Uso

## Como usar o Dashboard

### 1. Acesso Inicial

Após iniciar os containers Docker, acesse: http://localhost:10500

O dashboard carregará automaticamente:
- ✅ Estatísticas gerais (total de espaços, municípios, áreas, acessibilidade)
- 📊 Gráfico de distribuição por área de atuação
- 📋 Tabela com os espaços culturais

### 2. Sincronização de Dados

**Primeira sincronização (obrigatória):**
```bash
docker compose exec app php cron/sync_espacos.php
```

Isso irá:
- Buscar todos os espaços da API do Mapa Cultural
- Processar e armazenar no banco de dados
- Pode levar alguns minutos (são 6.700+ espaços)

**Sincronizações posteriores:**
- Clique no botão "Sincronizar" no dashboard
- Ou execute via terminal conforme acima
- Ou configure cron para automação

### 3. Filtros

**Filtrar por Município:**
1. Clique no select "Filtrar por Município"
2. Escolha um município da lista
3. A tabela será atualizada automaticamente

**Filtrar por Área de Atuação:**
1. Clique no select "Filtrar por Área"
2. Escolha uma área (Teatro, Música, Artes Visuais, etc.)
3. A tabela será atualizada automaticamente

**Combinar filtros:**
- Você pode usar ambos os filtros simultaneamente
- Exemplo: "Fortaleza" + "Teatro" mostrará apenas teatros de Fortaleza

### 4. Exportação de Dados

**Exportar para CSV:**
1. Aplique os filtros desejados (ou deixe sem filtros para exportar tudo)
2. Clique no botão "Exportar CSV"
3. O arquivo será baixado automaticamente
4. Nome do arquivo: `espacos-culturais-[filtrado-]YYYY-MM-DD.csv`

**Formato do CSV:**
- Codificação: UTF-8 com BOM
- Separador: vírgula (,)
- Colunas: ID, ID Externo, Nome, Município, Áreas de Atuação, Acessibilidade

### 5. Visualização de Dados

**Cards de Estatísticas:**
- **Total de Espaços**: Quantidade total de espaços cadastrados
- **Municípios**: Número de cidades com espaços culturais
- **Áreas de Atuação**: Quantidade de categorias diferentes
- **Com Acessibilidade**: Espaços com recursos de acessibilidade

**Gráfico de Barras:**
- Mostra as 5 principais áreas de atuação
- Altura da barra representa a quantidade de espaços
- Hover sobre a barra mostra o nome completo da área

**Tabela de Espaços:**
- Nome do espaço com iniciais em destaque
- Município onde está localizado
- Áreas de atuação (até 2 visíveis + contador se houver mais)
- Status de acessibilidade (Sim/Não)
- Link para ver detalhes no Mapa Cultural

**Informações de Sincronização:**
- **Última sincronização**: Tempo decorrido desde a última atualização
- **Próxima em**: Estimativa para próxima sincronização automática (se configurada)
- **Contador**: Espaços processados / Total de espaços
- **Barra de progresso**: Percentual de dados carregados

### 6. Navegação

**Ver detalhes de um espaço:**
1. Clique em "Ver detalhes" na linha do espaço
2. Você será redirecionado para a página oficial no Mapa Cultural
3. Abre em nova aba

### 7. Dicas de Uso

**Performance:**
- O sistema usa cache Redis para melhor desempenho
- Estatísticas são cacheadas por 30 minutos
- Lista de espaços é cacheada por 1 hora
- Após uma sincronização, o cache é limpo automaticamente

**Atualização de dados:**
- Execute sincronização periodicamente para manter dados atualizados
- Recomendado: a cada 6 horas ou diariamente
- A sincronização não remove dados existentes, apenas atualiza

**Filtros e Exportação:**
- Os filtros afetam tanto a tabela quanto a exportação
- Para exportar tudo: remova os filtros (selecione "Todos")
- Para exportar apenas dados filtrados: aplique filtros antes de exportar

**Responsividade:**
- O dashboard funciona em desktop, tablet e mobile
- Em mobile, a tabela tem scroll horizontal
- Cards de estatísticas se reorganizam automaticamente

### 8. Resolução de Problemas

**Dashboard vazio:**
- Execute a primeira sincronização
- Verifique se os containers estão rodando: `docker compose ps`
- Veja os logs: `docker compose logs -f app`

**Botão sincronizar não funciona:**
- Abra o Console do navegador (F12)
- Verifique se há erros JavaScript
- Teste o endpoint: `curl -X POST http://localhost:10500/api/sync.php`

**Filtros não aparecem:**
- Aguarde o carregamento completo das estatísticas
- Recarregue a página (F5)
- Verifique se há dados no banco: acesse phpMyAdmin

**Exportação falha:**
- Verifique se há espaços na tabela
- Tente sem filtros primeiro
- Verifique permissões do navegador para downloads

### 9. Atalhos de Teclado

- **F5**: Recarrega a página e atualiza dados
- **Ctrl/Cmd + Shift + I**: Abre DevTools (para debug)

### 10. API para Desenvolvedores

Se você precisa integrar com outras aplicações:

**Listar espaços:**
```javascript
fetch('/api/espacos.php?municipio=Fortaleza&page=1&limit=50')
  .then(r => r.json())
  .then(data => console.log(data));
```

**Obter estatísticas:**
```javascript
fetch('/api/stats.php')
  .then(r => r.json())
  .then(stats => console.log(stats));
```

**Sincronizar:**
```javascript
fetch('/api/sync.php', { method: 'POST' })
  .then(r => r.json())
  .then(result => console.log(result));
```

---

**Precisa de ajuda?** Consulte o [README.md](README.md) principal ou abra uma issue no GitHub.
