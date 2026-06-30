// app.js
// Lógica de frontend para o Sistema de Controle de Efetivo

document.addEventListener('DOMContentLoaded', () => {
    const statusSelects = document.querySelectorAll('.status-select');
    const saveCallBtn = document.getElementById('saveCallBtn');
    const dateInput = document.getElementById('dateInput');
    
    // Função para atualizar a cor do select com base no valor selecionado
    const updateSelectColor = (select) => {
        const val = select.value.toLowerCase();
        
        // Remover classes de cores anteriores
        select.className = 'status-select';
        
        if (['p', 'ea', 'ho', 'o'].includes(val)) {
            select.classList.add('p'); // Presentes
        } else if (['a', 'pa', 'pb'].includes(val)) {
            select.classList.add('a'); // Ausentes / Faltas
        } else if (['f'].includes(val)) {
            select.classList.add('f'); // Férias
        } else if (['dm', 'ins', 'lpm'].includes(val)) {
            select.classList.add('dm'); // Dispensas/Instalação
        } else if (['sv', 'ssv', 'fs'].includes(val)) {
            select.classList.add('sv'); // Serviços/Folgas
        } else if (['c', 'm'].includes(val)) {
            select.classList.add('c'); // Cursos/Missões
        }
    };

    // Aplicar a cor a todos os selects carregados
    statusSelects.forEach(select => {
        updateSelectColor(select);
        select.addEventListener('change', () => updateSelectColor(select));
    });

    // Enviar dados da chamada via AJAX
    if (saveCallBtn) {
        saveCallBtn.addEventListener('click', () => {
            const date = dateInput.value;
            if (!date) {
                showToast('Por favor, selecione uma data válida.', 'error');
                return;
            }

            const presencas = {};
            statusSelects.forEach(select => {
                const militarId = select.dataset.militarId;
                presencas[militarId] = select.value;
            });

            // Desabilitar botão para evitar cliques duplicados
            saveCallBtn.disabled = true;
            const originalContent = saveCallBtn.innerHTML;
            saveCallBtn.innerHTML = `
                <svg class="animate-spin" width="20" height="20" fill="none" viewBox="0 0 24 24" style="animation: spin 1s linear infinite; margin-right: 8px;">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity: 0.25;"></circle>
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg> Salvando...`;

            fetch('api.php?action=save_call', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ date, presencas })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.error || 'Erro desconhecido ao salvar.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Erro de rede ao salvar a chamada.', 'error');
            })
            .finally(() => {
                saveCallBtn.disabled = false;
                saveCallBtn.innerHTML = originalContent;
            });
        });
    }

    // Lançamento de indisponibilidade por período
    const periodForm = document.getElementById('periodForm');
    if (periodForm) {
        periodForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const militarId = document.getElementById('periodMilitar').value;
            const status = document.getElementById('periodStatus').value;
            const dateStart = document.getElementById('periodInicio').value;
            const dateEnd = document.getElementById('periodFim').value;
            
            const btn = document.getElementById('btnLaunchPeriod');
            btn.disabled = true;
            const originalText = btn.innerText;
            btn.innerText = 'Gravando...';
            
            fetch('api.php?action=save_period', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    militar_id: militarId, 
                    status, 
                    date_start: dateStart, 
                    date_end: dateEnd 
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    periodForm.reset();
                    // Recarregar a página para atualizar o status do militar na data atual
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.error || 'Erro ao lançar período.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Erro de rede ao salvar período.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerText = originalText;
            });
        });
    }

    // Função auxiliar para exibir Toast Notifications na tela
    const showToast = (message, type = 'success') => {
        // Remover toas anterior se existir
        const existingToast = document.querySelector('.toast-notification');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.style.cssText = `
            position: fixed;
            bottom: 30px;
            left: 30px;
            background-color: ${type === 'success' ? '#2F855A' : '#C53030'};
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        `;

        const icon = type === 'success' 
            ? `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`
            : `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;

        toast.innerHTML = `${icon} <span>${message}</span>`;
        document.body.appendChild(toast);

        // Animação de entrada
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        }, 50);

        // Animação de saída
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };
});

// Adicionar estilos CSS para animação spin do SVG de loading
const styleSheet = document.createElement("style");
styleSheet.innerText = `
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
`;
document.head.appendChild(styleSheet);
