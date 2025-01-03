class CoordPicker {
    constructor() {
        this.modal = null;
        this.searchInput = null;
        this.searchResults = null;
        this.map = null;
        this.marker = null;
        this.currentInput = null;
        this.initModal();
        this.initEvents();
    }

    initModal() {
        if (document.getElementById('rex-coord-modal')) {
            this.modal = document.getElementById('rex-coord-modal');
            this.searchInput = document.getElementById('rex-coord-search-input');
            this.searchResults = document.getElementById('rex-coord-search-results');
            return;
        }

        const modal = `
            <div id="rex-coord-modal" class="rex-coord-modal">
                <div class="rex-coord-content">
                    <div class="rex-coord-header">
                        <h3>Select Location</h3>
                        <span class="rex-coord-close">×</span>
                    </div>
                    <div class="rex-coord-search">
                        <input type="text" id="rex-coord-search-input" 
                               placeholder="Search address..." autocomplete="off">
                        <div id="rex-coord-search-results"></div>
                    </div>
                    <div id="rex-coord-map"></div>
                    <div class="rex-coord-footer">
                        <button type="button" class="btn btn-primary" id="rex-coord-apply">Apply</button>
                        <button type="button" class="btn btn-default" id="rex-coord-cancel">Cancel</button>
                    </div>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', modal);
        this.modal = document.getElementById('rex-coord-modal');
        this.searchInput = document.getElementById('rex-coord-search-input');
        this.searchResults = document.getElementById('rex-coord-search-results');
    }

    destroyModal() {
        if (this.modal) {
            this.modal.remove();
        }
        this.modal = null;
        this.searchInput = null;
        this.searchResults = null;
        this.map = null;
        this.marker = null;
        this.currentInput = null;
    }

    initEvents() {
        if (!this.modal) return;
        const self = this;
        document.querySelectorAll('.rex-coords').forEach(input => {
            input.addEventListener('click', function() {
                self.openPicker(this);
            });
        });

        document.querySelector('.rex-coord-close').addEventListener('click', () => this.closeModal());
        document.getElementById('rex-coord-cancel').addEventListener('click', () => this.closeModal());
        document.getElementById('rex-coord-apply').addEventListener('click', () => this.applyCoords());

        let searchTimeout;
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.performSearch(e.target.value), 300);
        });
    }

   openPicker(input) {
       this.currentInput = input;
        this.modal.style.display = 'block';

        const coords = this.parseCoords(input.value);
        let lat, lng;

        if (coords) {
            lat = coords.lat;
            lng = coords.lng;
        } else {
            lat = 0;
            lng = 0;
        }
       this.initMap(lat, lng, !coords); // Übergebe 'initialWorldView' Flag
    }

   initMap(lat, lng, initialWorldView = false) {
       if (this.map) {
          this.map.remove();
        }

        this.map = L.map('rex-coord-map').setView([lat, lng], initialWorldView ? 2 : 16); // Zoom-Level anpassen
        
       L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
       }).addTo(this.map);

       this.marker = L.marker([lat, lng], {
            draggable: true
       }).addTo(this.map);
   }

    async performSearch(searchText) {
        if (!searchText.trim()) {
            this.searchResults.innerHTML = '';
            return;
        }

        const response = await fetch(
            'https://nominatim.openstreetmap.org/search?format=json&q=' + 
            encodeURIComponent(searchText)
        );
        const data = await response.json();

        this.searchResults.innerHTML = '';
        data.slice(0, 5).forEach(result => {
            const div = document.createElement('div');
            div.className = 'search-result';
            div.textContent = result.display_name;
            div.addEventListener('click', () => {
                this.selectLocation(result);
                this.searchResults.innerHTML = '';
                this.searchInput.value = result.display_name;
            });
            this.searchResults.appendChild(div);
        });
    }

    selectLocation(location) {
        const lat = parseFloat(location.lat);
        const lng = parseFloat(location.lon);
        this.map.setView([lat, lng], 16);
        this.marker.setLatLng([lat, lng]);
    }

    parseCoords(value) {
        if (!value) return null;
        const [lat, lng] = value.split(',').map(v => parseFloat(v.trim()));
        return lat && lng ? { lat, lng } : null;
    }

    applyCoords() {
        const pos = this.marker.getLatLng();
        this.currentInput.value = `${pos.lat}, ${pos.lng}`;
        this.closeModal();
    }

    closeModal() {
        this.modal.style.display = 'none';
        this.searchResults.innerHTML = '';
        this.searchInput.value = '';
    }
}

let coordPickerInstance;

$(document).on('rex:ready', function() {
    if (coordPickerInstance) {
        coordPickerInstance.destroyModal();
    }
    coordPickerInstance = new CoordPicker();

    const observer = new MutationObserver(() => {
        if (coordPickerInstance) {
            coordPickerInstance.initEvents();
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
