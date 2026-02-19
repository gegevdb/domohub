/**
 * DomoHub Frontend Application
 */

class DomoHubApp {
    constructor() {
        this.apiBase = '/api/v1';
        this.token = localStorage.getItem('domohub_token');
        this.devices = [];
        this.currentFilter = 'all';
        this.isDarkMode = false;
        this.isVoiceActive = false;
        
        this.init();
    }

    async init() {
        this.setupEventListeners();
        this.checkAuth();
        this.loadTheme();
        await this.loadDevices();
        await this.loadSystemStatus();
        this.startPeriodicUpdates();
    }

    setupEventListeners() {
        // Theme toggle
        document.getElementById('themeToggle').addEventListener('click', () => {
            this.toggleTheme();
        });

        // Voice button
        document.getElementById('voiceBtn').addEventListener('click', () => {
            this.toggleVoice();
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            this.loadDevices();
        });

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.filterDevices(e.target.dataset.filter);
            });
        });

        // Quick actions
        document.querySelectorAll('button').forEach(btn => {
            if (btn.textContent.includes('Tout éteindre')) {
                btn.addEventListener('click', () => this.turnOffAll());
            }
            if (btn.textContent.includes('Allumer les lumières')) {
                btn.addEventListener('click', () => this.turnOnAllLights());
            }
            if (btn.textContent.includes('Mode nuit')) {
                btn.addEventListener('click', () => this.nightMode());
            }
        });
    }

    async checkAuth() {
        if (!this.token) {
            // Pour la démo, on utilise un token fixe
            this.token = 'demo_token';
            localStorage.setItem('domohub_token', this.token);
        }
    }

    async apiCall(endpoint, options = {}) {
        const url = `${this.apiBase}${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        
        if (!response.ok) {
            throw new Error(`API Error: ${response.status}`);
        }

        return response.json();
    }

    async loadDevices() {
        try {
            const data = await this.apiCall('/devices');
            this.devices = data;
            this.updateDevicesList();
            this.updateStats();
        } catch (error) {
            console.error('Error loading devices:', error);
            this.showError('Erreur lors du chargement des dispositifs');
        }
    }

    updateDevicesList() {
        const container = document.getElementById('devicesList');
        const filteredDevices = this.currentFilter === 'all' 
            ? this.devices 
            : this.devices.filter(d => d.device_type === this.currentFilter);

        if (filteredDevices.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>Aucun dispositif trouvé</p>
                </div>
            `;
            return;
        }

        container.innerHTML = filteredDevices.map(device => this.createDeviceCard(device)).join('');
        this.attachDeviceEventListeners();
    }

    createDeviceCard(device) {
        const isOnline = device.online;
        const isEnabled = device.enabled;
        const statusColor = isOnline ? 'green' : 'red';
        const statusText = isOnline ? 'En ligne' : 'Hors ligne';

        let controls = '';
        if (device.device_type === 'light') {
            const isOn = device.properties?.power || false;
            const brightness = device.properties?.brightness || 0;
            
            controls = `
                <div class="flex items-center space-x-2">
                    <button class="device-toggle px-3 py-1 ${isOn ? 'bg-yellow-500' : 'bg-gray-300'} text-white rounded-lg text-sm" data-device-id="${device.id}">
                        <i class="fas fa-power-off"></i>
                    </button>
                    <input type="range" class="brightness-slider" min="0" max="100" value="${brightness}" 
                           data-device-id="${device.id}" ${!isOn ? 'disabled' : ''}>
                    <span class="text-sm text-gray-600">${brightness}%</span>
                </div>
            `;
        } else if (device.device_type === 'sensor') {
            const temp = device.properties?.temperature || 0;
            const humidity = device.properties?.humidity || 0;
            
            controls = `
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="bg-blue-50 p-2 rounded">
                        <i class="fas fa-thermometer-half text-blue-500"></i>
                        <span class="ml-1">${temp}°C</span>
                    </div>
                    <div class="bg-green-50 p-2 rounded">
                        <i class="fas fa-tint text-green-500"></i>
                        <span class="ml-1">${humidity}%</span>
                    </div>
                </div>
            `;
        }

        return `
            <div class="device-card bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="font-semibold text-gray-800">${device.name}</h3>
                        <p class="text-sm text-gray-600">${device.room || 'Non assigné'}</p>
                    </div>
                    <div class="flex items-center">
                        <div class="w-2 h-2 bg-${statusColor}-500 rounded-full mr-2"></div>
                        <span class="text-xs text-gray-600">${statusText}</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <span class="inline-block px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded">
                        ${device.device_type}
                    </span>
                    ${device.manufacturer ? `
                        <span class="inline-block px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded ml-1">
                            ${device.manufacturer}
                        </span>
                    ` : ''}
                </div>
                
                ${controls}
            </div>
        `;
    }

    attachDeviceEventListeners() {
        // Device toggle buttons
        document.querySelectorAll('.device-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const deviceId = e.currentTarget.dataset.deviceId;
                this.toggleDevice(deviceId);
            });
        });

        // Brightness sliders
        document.querySelectorAll('.brightness-slider').forEach(slider => {
            slider.addEventListener('input', (e) => {
                const deviceId = e.target.dataset.deviceId;
                const brightness = e.target.value;
                this.setBrightness(deviceId, brightness);
            });
        });
    }

    async toggleDevice(deviceId) {
        try {
            const device = this.devices.find(d => d.id === deviceId);
            const newState = !device.properties?.power;
            
            await this.apiCall(`/devices/${deviceId}/actions`, {
                method: 'POST',
                body: JSON.stringify({
                    action: newState ? 'turn_on' : 'turn_off',
                    parameters: {}
                })
            });

            device.properties.power = newState;
            this.updateDevicesList();
            this.showSuccess(`Dispositif ${newState ? 'allumé' : 'éteint'}`);
        } catch (error) {
            console.error('Error toggling device:', error);
            this.showError('Erreur lors du changement d\'état');
        }
    }

    async setBrightness(deviceId, brightness) {
        try {
            await this.apiCall(`/devices/${deviceId}/actions`, {
                method: 'POST',
                body: JSON.stringify({
                    action: 'set_brightness',
                    parameters: { brightness: parseInt(brightness) }
                })
            });

            const device = this.devices.find(d => d.id === deviceId);
            device.properties.brightness = parseInt(brightness);
        } catch (error) {
            console.error('Error setting brightness:', error);
        }
    }

    filterDevices(filter) {
        this.currentFilter = filter;
        
        // Update filter button styles
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.dataset.filter === filter) {
                btn.className = 'filter-btn px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm';
            } else {
                btn.className = 'filter-btn px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-sm';
            }
        });

        this.updateDevicesList();
    }

    updateStats() {
        const lightsCount = this.devices.filter(d => d.device_type === 'light').length;
        const sensorsCount = this.devices.filter(d => d.device_type === 'sensor').length;
        
        document.getElementById('lightsCount').textContent = lightsCount;
        document.getElementById('sensorsCount').textContent = sensorsCount;
        document.getElementById('pluginsCount').textContent = '2'; // Hardcoded for demo
    }

    async loadSystemStatus() {
        try {
            const status = await this.apiCall('/system/status');
            
            document.getElementById('cpuUsage').textContent = `${status.cpu_percent.toFixed(1)}%`;
            document.getElementById('memoryUsage').textContent = `${status.memory_percent.toFixed(1)}%`;
            document.getElementById('systemTemp').textContent = `${status.temperature.toFixed(1)}°C`;
        } catch (error) {
            console.error('Error loading system status:', error);
        }
    }

    async toggleVoice() {
        this.isVoiceActive = !this.isVoiceActive;
        const btn = document.getElementById('voiceBtn');
        const text = document.getElementById('voiceText');
        const indicator = document.getElementById('voiceIndicator');
        const hint = document.getElementById('voiceHint');

        if (this.isVoiceActive) {
            btn.className = 'bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition voice-wave';
            text.textContent = 'Désactiver le vocal';
            indicator.className = 'w-20 h-20 mx-auto bg-red-500 rounded-full flex items-center justify-center mb-4 voice-wave';
            indicator.innerHTML = '<i class="fas fa-microphone text-white text-2xl"></i>';
            hint.textContent = 'Écoute en cours...';
            document.getElementById('voiceStatus').textContent = 'Actif';
            
            // Start voice recognition simulation
            this.startVoiceRecognition();
        } else {
            btn.className = 'bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition';
            text.textContent = 'Activer le vocal';
            indicator.className = 'w-20 h-20 mx-auto bg-gray-200 rounded-full flex items-center justify-center mb-4';
            indicator.innerHTML = '<i class="fas fa-microphone text-gray-500 text-2xl"></i>';
            hint.textContent = 'Cliquez pour activer';
            document.getElementById('voiceStatus').textContent = 'Inactif';
            
            this.stopVoiceRecognition();
        }
    }

    startVoiceRecognition() {
        // Simulate voice recognition
        // In a real implementation, this would use Web Speech API
        console.log('Voice recognition started');
    }

    stopVoiceRecognition() {
        console.log('Voice recognition stopped');
    }

    async turnOffAll() {
        const lights = this.devices.filter(d => d.device_type === 'light');
        
        for (const light of lights) {
            if (light.properties?.power) {
                await this.toggleDevice(light.id);
            }
        }
        
        this.showSuccess('Toutes les lumières ont été éteintes');
    }

    async turnOnAllLights() {
        const lights = this.devices.filter(d => d.device_type === 'light');
        
        for (const light of lights) {
            if (!light.properties?.power) {
                await this.toggleDevice(light.id);
            }
        }
        
        this.showSuccess('Toutes les lumières ont été allumées');
    }

    async nightMode() {
        // Dim all lights to 30%
        const lights = this.devices.filter(d => d.device_type === 'light');
        
        for (const light of lights) {
            await this.setBrightness(light.id, 30);
            if (!light.properties?.power) {
                await this.toggleDevice(light.id);
            }
        }
        
        this.showSuccess('Mode nuit activé');
    }

    toggleTheme() {
        this.isDarkMode = !this.isDarkMode;
        document.body.classList.toggle('dark-mode');
        
        const icon = document.querySelector('#themeToggle i');
        icon.className = this.isDarkMode ? 'fas fa-sun text-xl' : 'fas fa-moon text-xl';
        
        localStorage.setItem('darkMode', this.isDarkMode);
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('darkMode');
        if (savedTheme === 'true') {
            this.isDarkMode = true;
            document.body.classList.add('dark-mode');
            document.querySelector('#themeToggle i').className = 'fas fa-sun text-xl';
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 ${
            type === 'success' ? 'bg-green-500 text-white' : 
            type === 'error' ? 'bg-red-500 text-white' : 
            'bg-blue-500 text-white'
        }`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    startPeriodicUpdates() {
        // Update system status every 30 seconds
        setInterval(() => {
            this.loadSystemStatus();
        }, 30000);

        // Update devices every 60 seconds
        setInterval(() => {
            this.loadDevices();
        }, 60000);
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.domohub = new DomoHubApp();
});
