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

    // ============================================
    // MAPA
    // ============================================
    function initializeMap(lat, lng, label = null) {
        if (map !== null) {
            map.remove();
        }

        map = L.map('map').setView([lat, lng], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        if (label) {
            L.marker([lat, lng]).addTo(map).bindPopup(label).openPopup();
        } else {
            L.marker([lat, lng]).addTo(map);
        }

        setTimeout(() => map.invalidateSize(), 150);
    }

    // ============================================
    // GEOCODIFICAÇÃO VIA BACKEND
    // ============================================
async function geocodeNominatim(address) {
    // Constrói a URL final do Nominatim
    const nominatimUrl = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&format=json&limit=1`;

    // Usa um proxy CORS público e confiável para contornar as restrições.
    const proxyUrl = `https://cors-anywhere.herokuapp.com/${nominatimUrl}`;

    console.log('🌍 Usando proxy para buscar:', proxyUrl);

    try {
        const response = await fetch(proxyUrl, {
            headers: {
                // O proxy exige este cabeçalho para evitar abuso
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!response.ok) {
            throw new Error(`Erro na resposta do proxy: ${response.statusText}`);
        }
        const data = await response.json();
        console.log('🌍 Geocode via proxy público:', data);
        return data;
    } catch (error) {
        console.error('Erro ao geocodificar via proxy público:', error);
        return { error: 'Falha na comunicação com o serviço de geocodificação.' };
    }
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

    // ============================================
    // BUSCA
    // ============================================
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
            const fullAddress = `${entrega.dest_logradouro}, ${entrega.dest_numero || 'S/N'} - ${entrega.dest_bairro}, ${entrega.dest_municipio}/${entrega.dest_uf} - CEP: ${entrega.dest_cep}`;
            deliveryAddress.textContent = fullAddress;

            // Se o banco já tem coordenadas, usa elas
            if (entrega.dest_lat && entrega.dest_lng) {
                updateMap({ lat: entrega.dest_lat, lng: entrega.dest_lng }, entrega);
                showFeedback('Busca realizada com sucesso!', 'success');
                return;
            }

            // --- Lógica de Fallback de Geocodificação ---
            const addressesToTry = [
                `${entrega.dest_logradouro}, ${entrega.dest_numero || ''}, ${entrega.dest_bairro}, ${entrega.dest_municipio} - ${entrega.dest_uf}`,
                `${entrega.dest_logradouro}, ${entrega.dest_municipio} - ${entrega.dest_uf}`,
                `${entrega.dest_municipio} - ${entrega.dest_uf}`,
                `${entrega.dest_cep}, Brasil`
            ];

            let coordinates = null;
            for (const address of addressesToTry) {
                // Pula tentativas vazias ou malformadas
                if (!address || address.trim() === ', Brasil') continue;

                console.log(`🔎 Tentando geocodificar: "${address}"`);
                const geoData = await geocodeNominatim(address);
                if (geoData && geoData.length > 0 && geoData[0].lat && geoData[0].lon) {
                    coordinates = { lat: geoData[0].lat, lng: geoData[0].lon };
                    console.log('✅ Sucesso na geocodificação!', coordinates);
                    break; // Para no primeiro sucesso
                }
            }

            updateMap(coordinates, entrega);
            showFeedback('Busca realizada com sucesso!', 'success');

        } catch (error) {
            showFeedback(error.message, 'error');
            resultContainer.classList.add('hidden');
        }
    }

    function updateMap(coords, deliveryInfo) {
        if (!map) { // Inicializa o mapa se ainda não existir
            map = L.map('map').setView([-15.78, -47.92], 4); // Posição inicial no Brasil
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
        }

        // Limpa marcadores anteriores
        if (window.currentMarker) {
            map.removeLayer(window.currentMarker);
        }

        // Verifica se as coordenadas são válidas
        if (coords && coords.lat && coords.lng) {
            const lat = parseFloat(coords.lat);
            const lng = parseFloat(coords.lng);

            if (!isNaN(lat) && !isNaN(lng)) {
                console.log(`🗺️ Atualizando mapa para [${lat}, ${lng}]`);
                document.getElementById('map-container').style.display = 'block';
                map.setView([lat, lng], 15);
                window.currentMarker = L.marker([lat, lng]).addTo(map)
                    .bindPopup(`<b>${deliveryInfo.dest_name}</b><br>${deliveryInfo.dest_logradouro || 'Endereço principal'}.`)
                    .openPopup();
                setTimeout(() => map.invalidateSize(), 150);
                return;
            }
        }

        // Se as coordenadas forem inválidas ou nulas, esconde o mapa
        console.warn('Não foi possível obter coordenadas válidas. O mapa não será exibido.');
        document.getElementById('map-container').style.display = 'none';
    }

    // ============================================
    // UPLOAD XML
    // ============================================
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

