// Estado global da aplicação
const state = {
  events: [],
  filteredEvents: [],
  stats: null,
  filters: {
    municipio: '',
    linguagem: ''
  },
  sort: {
    field: 'data_inicio',
    direction: 'desc'
  }
};

// Configuração da API
const API_BASE = '/api';

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
  initializeEventListeners();
  loadInitialData();
});

// Event listeners
function initializeEventListeners() {
  const syncBtn = document.getElementById('syncBtn');
  const exportBtn = document.getElementById('exportBtn');
  const filterMunicipio = document.getElementById('filterMunicipio');
  const filterLinguagem = document.getElementById('filterLinguagem');

  if (syncBtn) {
    syncBtn.addEventListener('click', syncData);
  }

  if (exportBtn) {
    exportBtn.addEventListener('click', exportToCSV);
  }

  if (filterMunicipio) {
    filterMunicipio.addEventListener('change', (e) => {
      state.filters.municipio = e.target.value;
      loadEvents();
    });
  }

  if (filterLinguagem) {
    filterLinguagem.addEventListener('change', (e) => {
      state.filters.linguagem = e.target.value;
      loadEvents();
    });
  }

  // Event listeners para ordenação de colunas
  document.querySelectorAll('[data-sort]').forEach(button => {
    button.addEventListener('click', (e) => {
      const field = e.currentTarget.dataset.sort;
      handleSort(field);
    });
  });
}

// Carrega dados iniciais
async function loadInitialData() {
  try {
    await Promise.all([
      loadStats(),
      loadEvents()
    ]);
  } catch (error) {
    console.error('Erro ao carregar dados iniciais:', error);
    showError('Erro ao carregar dados. Tente novamente.');
  }
}

// Carrega estatísticas da API
async function loadStats() {
  try {
    const response = await fetch(`${API_BASE}/stats.php?_t=${Date.now()}`);
    if (!response.ok) throw new Error('Erro ao carregar estatísticas');
    
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Erro desconhecido');
    
    console.log('Stats carregadas:', result);
    state.stats = result;
    updateStatsUI();
    updateChart();
    updateFilters();
    updateSyncInfo();
  } catch (error) {
    console.error('Erro ao carregar estatísticas:', error);
    throw error;
  }
}

// Carrega eventos da API
async function loadEvents() {
  try {
    const params = new URLSearchParams({
      _t: Date.now()
    });

    if (state.filters.municipio) {
      params.append('municipio', state.filters.municipio);
    }

    if (state.filters.linguagem) {
      params.append('linguagem', state.filters.linguagem);
    }

    const response = await fetch(`${API_BASE}/eventos.php?${params}`);
    if (!response.ok) throw new Error('Erro ao carregar eventos');
    
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Erro desconhecido');
    
    console.log('Eventos carregados:', {
      data: result.data.length,
      pagination: result.pagination
    });
    
    state.events = result.data;
    state.filteredEvents = result.data;
    
    updateTable();
    updateTableCount(result.pagination?.total || result.data.length);
  } catch (error) {
    console.error('Erro ao carregar eventos:', error);
    throw error;
  }
}

// Sincroniza dados com a API externa
async function syncData() {
  const syncBtn = document.getElementById('syncBtn');
  if (!syncBtn) return;

  const originalContent = syncBtn.innerHTML;
  
  try {
    // Desabilita botão e mostra loading
    syncBtn.disabled = true;
    syncBtn.innerHTML = `
      <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span class="text-sm font-medium">Sincronizando...</span>
    `;

    const response = await fetch(`${API_BASE}/sync.php`, {
      method: 'POST'
    });
    
    if (!response.ok) {
      if (response.status === 409) {
        throw new Error('Já existe uma sincronização em andamento');
      }
      throw new Error('Erro ao sincronizar dados');
    }
    
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Erro ao sincronizar');
    
    // Mostra resultado
    showSuccess(`Sincronização concluída! ${result.data.novos} novos, ${result.data.atualizados} atualizados.`);
    
    // Recarrega dados
    await loadInitialData();
    
  } catch (error) {
    console.error('Erro na sincronização:', error);
    showError(error.message || 'Erro ao sincronizar dados');
  } finally {
    // Restaura botão
    syncBtn.disabled = false;
    syncBtn.innerHTML = originalContent;
  }
}

// Atualiza estatísticas na UI
function updateStatsUI() {
  if (!state.stats) return;

  const { geral, linguagens, municipios, last_sync } = state.stats;

  // Atualiza cards (com fallback para 0)
  document.getElementById('totalEvents').textContent = (geral.total_eventos || 0).toLocaleString('pt-BR');
  document.getElementById('totalMunicipios').textContent = (geral.total_municipios || 0).toLocaleString('pt-BR');
  document.getElementById('totalLinguagens').textContent = (geral.total_linguagens || 0).toLocaleString('pt-BR');
  document.getElementById('totalAccessibility').textContent = (geral.total_acessibilidade || 0).toLocaleString('pt-BR');
}

// Atualiza informações de sincronização
function updateSyncInfo() {
  if (!state.stats?.last_sync || !state.stats?.geral) return;

  const { ultima_sincronizacao } = state.stats.last_sync;
  const total_processado = state.stats.geral.total_eventos; // Usar total real do banco
  
  if (ultima_sincronizacao) {
    const lastSyncDate = new Date(ultima_sincronizacao);
    const now = new Date();
    const diffMs = now - lastSyncDate;
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    let timeText = '';
    if (diffHours > 0) {
      timeText = `Há ${diffHours}h`;
      if (diffMinutes > 0) timeText += ` ${diffMinutes}m`;
    } else if (diffMinutes > 0) {
      timeText = `Há ${diffMinutes}m`;
    } else {
      timeText = 'Agora mesmo';
    }

    const lastSyncEl = document.getElementById('lastSync');
    if (lastSyncEl) lastSyncEl.textContent = timeText;

    // Próxima sincronização (estimativa de 6 horas)
    const nextSync = new Date(lastSyncDate.getTime() + 6 * 60 * 60 * 1000);
    const nextDiffMs = nextSync - now;
    if (nextDiffMs > 0) {
      const nextHours = Math.floor(nextDiffMs / (1000 * 60 * 60));
      const nextMinutes = Math.floor((nextDiffMs % (1000 * 60 * 60)) / (1000 * 60));
      const nextSyncEl = document.getElementById('nextSync');
      if (nextSyncEl) nextSyncEl.textContent = `Próxima em ${nextHours}h ${nextMinutes}m`;
    }
  }

  // Atualiza contador de sincronização (100% pois já temos todos os dados do banco)
  const syncCountEl = document.getElementById('syncCount');
  if (syncCountEl && total_processado) {
    syncCountEl.textContent = `${total_processado.toLocaleString('pt-BR')} / ${total_processado.toLocaleString('pt-BR')}`;
  }

  // Atualiza barra de progresso (100% pois dados já estão no banco)
  const progressBar = document.getElementById('progressBar');
  const progressText = document.getElementById('progressText');
  if (progressBar && progressText && total_processado) {
    progressBar.style.width = '100%';
    progressText.textContent = `${total_processado.toLocaleString('pt-BR')} eventos sincronizados (100% completo)`;
  }
}

// Atualiza gráfico
function updateChart() {
  if (!state.stats?.linguagens) return;

  const chartContainer = document.getElementById('chartContainer');
  if (!chartContainer) {
    console.error('chartContainer não encontrado');
    return;
  }

  // Pega as top 10 linguagens
  const topLinguagens = state.stats.linguagens
    .sort((a, b) => b.total - a.total)
    .slice(0, 10);

  const maxCount = Math.max(...topLinguagens.map(l => l.total));
  const containerHeight = 200;
  
  chartContainer.innerHTML = topLinguagens
    .map(({ linguagem, total }) => {
      const heightPx = maxCount > 0 ? Math.max((total / maxCount) * containerHeight, 30) : 30;
      return `
        <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
          <div style="display: flex; align-items: flex-end; justify-content: center; height: ${containerHeight}px; width: 100%;">
            <div style="position: relative; width: 64px; height: ${heightPx}px; background: linear-gradient(180deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.3) 100%); border-radius: 8px 8px 0 0; box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);">
              <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 100%; background: linear-gradient(to top, rgb(59, 130, 246) 0%, rgba(59, 130, 246, 0.7) 100%); border-radius: 8px 8px 0 0;"></div>
            </div>
          </div>
          <span style="font-size: 0.75rem; color: rgb(148, 163, 184); text-align: center; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 8px; margin-bottom: 4px;" title="${linguagem}">${linguagem}</span>
          <span style="font-size: 0.75rem; font-family: monospace; color: rgb(59, 130, 246); font-weight: 600;">${total}</span>
        </div>
      `;
    })
    .join('');
}

// Atualiza tabela
function updateTable() {
  const tableBody = document.getElementById('tableBody');
  if (!tableBody) return;

  if (state.filteredEvents.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7" class="px-6 py-12 text-center text-muted-foreground">
          Nenhum evento encontrado
        </td>
      </tr>
    `;
    return;
  }

  // Aplicar ordenação
  const sortedEvents = sortEvents([...state.filteredEvents]);

  tableBody.innerHTML = sortedEvents
    .map(event => {
      const linguagens = event.linguagens ? event.linguagens.split(',').map(l => l.trim()) : [];
      
      // Formatar data
      let dataFormatada = '-';
      if (event.data_inicio) {
        const dataObj = new Date(event.data_inicio);
        dataFormatada = dataObj.toLocaleDateString('pt-BR');
      }
      
      // Usar hora_inicio se disponível, senão extrair de data_inicio
      let horaFormatada = '-';
      if (event.hora_inicio) {
        horaFormatada = event.hora_inicio.substring(0, 5); // HH:MM
      } else if (event.data_inicio) {
        const dataObj = new Date(event.data_inicio);
        horaFormatada = dataObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
      }
      
      // Usar local_nome se disponível, senão local
      const localNome = event.local_nome || event.local || '-';
      
      // Usar tags se disponível, senão linguagens
      const tags = event.tags ? event.tags.split(',').map(t => t.trim()) : linguagens;
      
      return `
      <tr class="hover:bg-muted/20 transition-colors">
        <td class="px-6 py-4">
          <span class="text-sm font-mono text-muted-foreground">#${event.external_id || event.id}</span>
        </td>
        <td class="px-6 py-4">
          <p class="font-medium text-foreground">${event.nome || 'Sem nome'}</p>
        </td>
        <td class="px-6 py-4 text-sm text-foreground">${dataFormatada}</td>
        <td class="px-6 py-4 text-sm text-foreground">${horaFormatada}</td>
        <td class="px-6 py-4 text-sm text-foreground">${localNome}</td>
        <td class="px-6 py-4">
          ${tags.length > 0 ? tags.slice(0, 2).map(tag => `
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary mb-1 mr-1">
              ${tag}
            </span>
          `).join('') : '<span class="text-xs text-muted-foreground">-</span>'}
          ${tags.length > 2 ? `<span class="text-xs text-muted-foreground">+${tags.length - 2}</span>` : ''}
        </td>
        <td class="px-6 py-4">
          <a href="https://mapacultural.secult.ce.gov.br/evento/${event.external_id}" target="_blank" class="text-primary hover:underline text-sm font-medium">
            Ver detalhes
          </a>
        </td>
      </tr>
    `;
    })
    .join('');
}

// Atualiza contador da tabela
function updateTableCount(total) {
  const tableCountEl = document.getElementById('tableCount');
  if (tableCountEl) {
    tableCountEl.textContent = total.toLocaleString('pt-BR');
  }
}

// Atualiza filtros
function updateFilters() {
  if (!state.stats) return;

  const filterMunicipio = document.getElementById('filterMunicipio');
  const filterLinguagem = document.getElementById('filterLinguagem');

  if (filterMunicipio && state.stats.municipios) {
    const municipios = state.stats.municipios.sort((a, b) => a.municipio.localeCompare(b.municipio));
    filterMunicipio.innerHTML = `
      <option value="">Todos os municípios (${municipios.length})</option>
      ${municipios.map(m => `<option value="${m.municipio}">${m.municipio} (${m.total})</option>`).join('')}
    `;
  }

  if (filterLinguagem && state.stats.linguagens) {
    const linguagens = state.stats.linguagens.sort((a, b) => a.linguagem.localeCompare(b.linguagem));
    filterLinguagem.innerHTML = `
      <option value="">Todas as linguagens (${linguagens.length})</option>
      ${linguagens.map(l => `<option value="${l.linguagem}">${l.linguagem} (${l.total})</option>`).join('')}
    `;
  }
}

// Exporta para CSV
function exportToCSV() {
  if (state.filteredEvents.length === 0) {
    showError('Nenhum dado para exportar');
    return;
  }

  const headers = ['ID', 'ID Externo', 'Nome', 'Município', 'Linguagens', 'Data Início', 'Data Fim'];
  const rows = state.filteredEvents.map(event => [
    event.id,
    event.external_id || '',
    `"${(event.nome || '').replace(/"/g, '""')}"`,
    event.municipio || '',
    `"${(event.linguagens || '').replace(/"/g, '""')}"`,
    event.data_inicio ? new Date(event.data_inicio).toLocaleDateString('pt-BR') : '',
    event.data_fim ? new Date(event.data_fim).toLocaleDateString('pt-BR') : ''
  ]);

  const csv = [headers, ...rows].map(row => row.join(',')).join('\n');
  const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const filename = state.filters.municipio || state.filters.linguagem 
    ? `eventos-culturais-filtrado-${new Date().toISOString().split('T')[0]}.csv`
    : `eventos-culturais-${new Date().toISOString().split('T')[0]}.csv`;
  
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  
  showSuccess('Arquivo CSV exportado com sucesso!');
}

// Mostra mensagem de sucesso
function showSuccess(message) {
  // Implementação simples - pode ser melhorada com toast/notification library
  console.log('✅ Sucesso:', message);
  alert(message);
}

// Mostra mensagem de erro
function showError(message) {
  // Implementação simples - pode ser melhorada com toast/notification library
  console.error('❌ Erro:', message);
  alert(message);
}

// Manipula ordenação de colunas
function handleSort(field) {
  // Se clicar na mesma coluna, inverte a direção
  if (state.sort.field === field) {
    state.sort.direction = state.sort.direction === 'asc' ? 'desc' : 'asc';
  } else {
    // Nova coluna, sempre começa em ordem ascendente
    state.sort.field = field;
    state.sort.direction = 'asc';
  }
  
  updateTable();
  updateSortIndicators();
}

// Ordena eventos baseado no estado atual
function sortEvents(events) {
  const { field, direction } = state.sort;
  
  return events.sort((a, b) => {
    let aVal = a[field];
    let bVal = b[field];
    
    // Tratamento especial para valores nulos/undefined
    if (aVal === null || aVal === undefined) aVal = '';
    if (bVal === null || bVal === undefined) bVal = '';
    
    // Conversão para string se necessário
    if (typeof aVal === 'string') aVal = aVal.toLowerCase();
    if (typeof bVal === 'string') bVal = bVal.toLowerCase();
    
    // Comparação
    let comparison = 0;
    if (aVal > bVal) comparison = 1;
    if (aVal < bVal) comparison = -1;
    
    // Aplica direção
    return direction === 'asc' ? comparison : -comparison;
  });
}

// Atualiza indicadores visuais de ordenação
function updateSortIndicators() {
  // Remove indicadores antigos
  document.querySelectorAll('[data-sort]').forEach(button => {
    const svg = button.querySelector('svg');
    button.classList.remove('text-primary');
    if (svg) {
      svg.classList.remove('opacity-100', 'text-primary');
      svg.classList.add('opacity-30');
    }
  });
  
  // Adiciona indicador na coluna ativa
  const activeButton = document.querySelector(`[data-sort="${state.sort.field}"]`);
  if (activeButton) {
    const svg = activeButton.querySelector('svg');
    activeButton.classList.add('text-primary');
    if (svg) {
      svg.classList.remove('opacity-30');
      svg.classList.add('opacity-100', 'text-primary');
      
      // Rotaciona ícone baseado na direção
      svg.style.transition = 'transform 0.2s ease';
      if (state.sort.direction === 'asc') {
        svg.style.transform = 'rotate(180deg)';
      } else {
        svg.style.transform = 'rotate(0deg)';
      }
    }
  }
}
