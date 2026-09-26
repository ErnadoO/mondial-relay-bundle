import { Controller } from '@hotwired/stimulus';

// The official Mondial Relay widget is a jQuery plugin that draws its map with Leaflet.
// Both are loaded on demand, so the page only pays for them when a picker is displayed.
const JQUERY = 'https://code.jquery.com/jquery-3.7.1.min.js';
const LEAFLET = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet';
const PICKER = 'https://widget.mondialrelay.com/parcelshop-picker/jquery.plugin.mondialrelay.parcelshoppicker.min.js';

const loaded = new Map();

function loadScript(src) {
    if (!loaded.has(src)) {
        loaded.set(src, new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Unable to load ${src}`));
            document.head.append(script);
        }));
    }

    return loaded.get(src);
}

function loadStylesheet(href) {
    if (!document.querySelector(`link[href="${href}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.append(link);
    }
}

// Mondial Relay doubles apostrophes in its data ("D''ACTIVITE").
const clean = (text) => (text || '').replaceAll("''", "'").trim();

/**
 * Relay point picker.
 *
 * Fills the optional `id`, `name`, `address` and `summary` targets when a relay point is selected,
 * and dispatches a `select` event whose detail describes the relay point
 * ({ id: "FR-066974", number, name, address, postCode, city, country }).
 */
export default class extends Controller {
    static targets = ['map', 'id', 'name', 'address', 'summary'];
    static values = {
        brand: String,
        country: { type: String, default: 'FR' },
        postCode: String,
        city: String,
        mode: { type: String, default: '24R' },
        results: { type: Number, default: 7 },
    };

    async connect() {
        loadStylesheet(`${LEAFLET}.css`);
        if (!window.jQuery) {
            await loadScript(JQUERY);
        }
        await loadScript(`${LEAFLET}.js`);
        await loadScript(PICKER);

        window.jQuery(this.hasMapTarget ? this.mapTarget : this.element).MR_ParcelShopPicker({
            Brand: this.brandValue,
            Country: this.countryValue,
            AllowedCountries: this.countryValue,
            PostCode: this.postCodeValue,
            City: this.cityValue,
            ColLivMod: this.modeValue,
            NbResults: String(this.resultsValue),
            Responsive: true,
            ShowResultsOnMap: true,
            OnParcelShopSelected: (point) => this.select(point),
        });
    }

    select(point) {
        const street = [point.Adresse1, point.Adresse2].map(clean).filter(Boolean).join(', ');
        const relayPoint = {
            id: `${point.Pays}-${point.ID}`,
            number: point.ID,
            name: clean(point.Nom),
            address: `${street}, ${point.CP} ${clean(point.Ville)}`,
            postCode: point.CP,
            city: clean(point.Ville),
            country: point.Pays,
        };

        if (this.hasIdTarget) this.idTarget.value = relayPoint.id;
        if (this.hasNameTarget) this.nameTarget.value = relayPoint.name;
        if (this.hasAddressTarget) this.addressTarget.value = relayPoint.address;
        if (this.hasSummaryTarget) this.summaryTarget.textContent = `${relayPoint.name} — ${relayPoint.address}`;

        this.dispatch('select', { detail: relayPoint });
    }
}
