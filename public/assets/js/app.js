document.addEventListener('DOMContentLoaded', () => {
    const API_BASE_URL = '/backend/routes.php';

    const uploadForm = document.getElementById('upload-form');
    const searchForm = document.getElementById('search-form');
    const nfeKeyInput = document.getElementById('nfe-key-input');
    const nfeXmlInput = document.getElementById('nfe-xml-input');

    const resultContainer = document.getElementById('result-container');
    const timeline = document.getElementById('timeline');
    const feedbackMessage = document.getElementById('feedback-message');
    const deliveryAddress = document.getElementById('delivery-address');

    const kpiTotal = document.getElementById('total-entregas');
    const kpiInProgress = document.getElementById('entregas-em-andamento');
    const kpiDelivered = document.getElementById('entregas-finalizadas');

    let map = null;

    function showFeedback(message, type) {
        feedbackMessage.textContent = message;
        feedbackMessage.className = type;
        setTimeout(() => {
            feedbackMessage.className = 'hidden';
        }, 5000);
    }

    // ============================================================
    // INICIALIZAR MAPA SEMPRE – COM COORDENADAS, CIDADE OU BRASIL
    // ============================================================
    function initializeMap(lat, lng, label = null) {

        // Remover mapa anterior
        if (map !== null) {
            map.remove();
        }

        map = L.map('map').setView([lat, lng], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // Adicionar marcador se informado
        if (label) {
            L.marker([lat, lng]).addTo(map).bindPopup(label).openPopup();
        } else {
            L.marker([lat, lng]).addTo(map);
        }

        // Correção do mapa cinza
        setTimeout(() => map.invalidateSize(), 150);
    }

    // ============================================================
    // GEOCODIFICAÇÃO VIA NOMINATIM (FRONT-END)
    // ============================================================
    async function geocodeNominatim(address) {
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`;

        try {
            const response = await fetch(url, {
                headers: { "User-Agent": "LuminaTrack/1.0" }
            });

            const json = await response.json();
            if (json.length > 0) {
                return {
                    lat: parseFloat(json[0].lat),
                    lng: parseFloat(json[0].lon)
                };
            }
        } catch (e) {
            console.error("Erro no Nominatim:", e);
        }

        return null;
    }

    function updateTimeline(events) {
        timeline.innerHTML = '';
        if (events.length === 0) {
            timeline.innerHTML = '<li>Nenhum evento encontrado.</li>';
            return;
        }
        events.forEach(event => {
            const li = document.createElement('li');
            const eventDate = new Date(event.event_date);
            const formattedDate = eventDate.toLocaleString('pt-BR');
            li.innerHTML = `
                <span class="status">${event.status}</span>
                <span class="date">${formattedDate}</span>
            `;
            timeline.appendChild(li);
        });
    }

    // DEBUG
    async function fetchJsonWithDebug(url, options = {}) {
        const response = await fetch(url, options);
        const rawText = await response.text();

        console.log("🔍 RESPOSTA BRUTA DO BACKEND (" + url + "):\n", rawText);

        try {
            return JSON.parse(rawText);
        } catch (e) {
            console.error("❌ ERRO AO CONVERTER PARA JSON:", e);
            throw new Error("Resposta inválida do servidor (não é JSON).");
        }
    }

    async function fetchMetrics() {
        try {
            const data = await fetchJsonWithDebug(`${API_BASE_URL}?route=/metricas`);
            kpiTotal.textContent = data.total_entregas || 0;
            kpiInProgress.textContent = data.entregas_em_andamento || 0;
            kpiDelivered.textContent = data.entregas_finalizadas || 0;
        } catch (error) {
            console.error('Erro nas métricas:', error);
        }
    }

    // ============================================================
    // BUSCA
    // ============================================================
    async function handleSearch(event) {
        event.preventDefault();

        const nfeKey = nfeKeyInput.value.trim();
        if (nfeKey.length !== 44) {
            showFeedback('A chave da NF-e deve ter 44 dígitos.', 'error');
            return;
        }

        try {
            const data = await fetchJsonWithDebug(`${API_BASE_URL}?route=/rastreamento/${nfeKey}`);

            if (data.error) throw new Error(data.error);

            resultContainer.classList.remove('hidden');

            updateTimeline(data.eventos);

            const entrega = data.entrega;

            // EXIBIR ENDEREÇO
            const fullAddress = `${entrega.dest_logradouro}, ${entrega.dest_numero} - ${entrega.dest_bairro}, ${entrega.dest_municipio}/${entrega.dest_uf} - CEP: ${entrega.dest_cep}`;
            deliveryAddress.textContent = fullAddress;

            // ========================================================
            // MAPA — 1º: usar coordenadas do banco
            // ========================================================
            if (entrega.dest_lat && entrega.dest_lng) {
                initializeMap(entrega.dest_lat, entrega.dest_lng, entrega.dest_municipio);
                return;
            }

            // ========================================================
            // MAPA — 2º tentativa: geocodificar endereço completo
            // ========================================================
            const fullAddr = `${entrega.dest_logradouro} ${entrega.dest_numero}, ${entrega.dest_bairro}, ${entrega.dest_municipio} - ${entrega.dest_uf}`;
            let coords = await geocodeNominatim(fullAddr);

            if (!coords) {
                // ====================================================
                // MAPA — 3º tentar somente cidade
                // ====================================================
                const cityAddr = `${entrega.dest_municipio} - ${entrega.dest_uf}, Brasil`;
                coords = await geocodeNominatim(cityAddr);
            }

            if (coords) {
                initializeMap(coords.lat, coords.lng, entrega.dest_municipio);
            } else {
                // ====================================================
                // MAPA — fallback final: Brasil
                // ====================================================
                initializeMap(-15.788497, -47.879873, "Brasil");
            }

            showFeedback('Busca realizada com sucesso!', 'success');

        } catch (error) {
            showFeedback(error.message, 'error');
            resultContainer.classList.add('hidden');
        }
    }

    // ============================================================
    // UPLOAD XML
    // ============================================================
    async function handleUpload(event) {
        event.preventDefault();

        const formData = new FormData();
        formData.append('nfe_xml', nfeXmlInput.files[0]);

        try {
            const data = await fetchJsonWithDebug(`${API_BASE_URL}?route=/upload`, {
                method: 'POST',
                body: formData
            });

            if (data.error) throw new Error(data.error);

            showFeedback('NF-e registrada com sucesso!', 'success');
            uploadForm.reset();
            fetchMetrics();

        } catch (error) {
            showFeedback(error.message, 'error');
        }
    }

    uploadForm.addEventListener('submit', handleUpload);
    searchForm.addEventListener('submit', handleSearch);

    fetchMetrics();
});

