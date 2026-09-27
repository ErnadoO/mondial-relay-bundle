import { Controller } from '@hotwired/stimulus';

// Leaflet is loaded on demand from a CDN: pages without a picker do not pay for it.
const LEAFLET = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet';
const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

let leaflet = null;

function loadLeaflet() {
    if (!leaflet) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `${LEAFLET}.css`;
        document.head.append(link);

        leaflet = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `${LEAFLET}.js`;
            script.onload = () => resolve(window.L);
            script.onerror = () => reject(new Error('Unable to load Leaflet'));
            document.head.append(script);
        });
    }

    return leaflet;
}

function element(tag, attributes = {}, text = null) {
    const node = document.createElement(tag);
    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
    if (null !== text) {
        node.textContent = text;
    }

    return node;
}

/**
 * Relay point picker fed by the Mondial Relay search API (through the bundle endpoint):
 * a search field, an accessible list and a Leaflet map.
 *
 * Same contract as the widget picker: fills the optional `id`, `name`, `address` and `summary`
 * targets and dispatches a `select` event whose detail describes the relay point.
 */
export default class extends Controller {
    static targets = ['panel', 'id', 'name', 'address', 'summary'];
    static values = {
        url: String,
        country: { type: String, default: 'FR' },
        postCode: String,
        mode: { type: String, default: '24R' },
        selected: String,
        labels: Object,
    };

    connect() {
        this.markers = new Map();
        this.render();
        if (this.postCodeValue) {
            this.search(this.postCodeValue);
        }
    }

    disconnect() {
        this.map?.remove();
        this.map = null;
    }

    label(key, parameters = {}) {
        return Object.entries(parameters).reduce((text, [name, value]) => text.replace(`%${name}%`, value), this.labelsValue[key] ?? key);
    }

    render() {
        const container = this.hasPanelTarget ? this.panelTarget : this.element;
        const id = `mr-picker-${Math.random().toString(36).slice(2)}`;

        this.root = element('div', { class: 'mr-picker' });

        const search = element('div', { class: 'mr-picker__search' });
        this.input = element('input', { type: 'text', id: `${id}-postcode`, inputmode: 'numeric', autocomplete: 'postal-code', maxlength: '10', class: 'mr-picker__input' });
        this.input.value = this.postCodeValue;
        this.input.addEventListener('keydown', (event) => {
            if ('Enter' === event.key) {
                // Inside a form, Enter would submit it
                event.preventDefault();
                this.search(this.input.value);
            }
        });
        const button = element('button', { type: 'button', class: 'mr-picker__button' }, this.label('search'));
        button.addEventListener('click', () => this.search(this.input.value));
        search.append(element('label', { for: `${id}-postcode`, class: 'mr-picker__label' }, this.label('postCode')), this.input, button);

        this.status = element('p', { class: 'mr-picker__status', role: 'status', 'aria-live': 'polite' });
        const results = element('div', { class: 'mr-picker__results' });
        this.list = element('ul', { class: 'mr-picker__list' });
        this.mapElement = element('div', { class: 'mr-picker__map' });
        results.append(this.list, this.mapElement);

        this.root.append(search, this.status, results);
        container.replaceChildren(this.root);
    }

    async search(postCode) {
        postCode = (postCode || '').trim();
        if (!/^[A-Za-z0-9 -]{2,10}$/.test(postCode)) {
            this.status.textContent = this.label('invalidPostCode');

            return;
        }

        this.status.textContent = this.label('searching');
        this.root.setAttribute('aria-busy', 'true');

        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.search = new URLSearchParams({ country: this.countryValue, postCode, mode: this.modeValue }).toString();
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (429 === response.status) {
                this.status.textContent = this.label('tooManyRequests');

                return;
            }
            if (!response.ok) {
                this.status.textContent = this.label(400 === response.status ? 'invalidPostCode' : 'unavailable');

                return;
            }

            const { relayPoints } = await response.json();
            this.status.textContent = relayPoints.length ? this.label('results', { count: relayPoints.length }) : this.label('noResult');
            await this.show(relayPoints);
        } catch (error) {
            this.status.textContent = this.label('unavailable');
        } finally {
            this.root.removeAttribute('aria-busy');
        }
    }

    async show(relayPoints) {
        this.points = relayPoints;
        this.list.replaceChildren(...relayPoints.map((point) => this.item(point)));

        const L = await loadLeaflet();
        if (!this.map) {
            this.map = L.map(this.mapElement, { scrollWheelZoom: false });
            L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(this.map);
        }

        this.markers.forEach((marker) => marker.remove());
        this.markers.clear();
        relayPoints.forEach((point) => {
            const marker = L.marker([point.latitude, point.longitude], { title: point.name, alt: point.name })
                .addTo(this.map)
                .bindPopup(`<strong>${this.escape(point.name)}</strong><br>${this.escape(point.address)}`)
                .on('click', () => this.select(point));
            this.markers.set(point.id, marker);
        });

        if (relayPoints.length) {
            this.map.fitBounds(L.latLngBounds(relayPoints.map((point) => [point.latitude, point.longitude])), { padding: [24, 24] });
        }
        // The map may have been laid out while hidden or resized
        this.map.invalidateSize();

        const selected = relayPoints.find((point) => point.id === this.selectedValue);
        if (selected) {
            this.select(selected, false);
        }
    }

    item(point) {
        const li = element('li', { class: 'mr-picker__item', 'data-relay-point': point.id });
        const button = element('button', { type: 'button', class: 'mr-picker__choose', 'aria-pressed': 'false' });
        button.append(
            element('span', { class: 'mr-picker__name' }, point.name),
            element('span', { class: 'mr-picker__address' }, point.address),
            element('span', { class: 'mr-picker__distance' }, `${point.distanceKm.toLocaleString(undefined, { maximumFractionDigits: 1 })} km`),
        );
        button.addEventListener('click', () => this.select(point));
        li.append(button);

        if (point.openingHours.length) {
            const details = element('details', { class: 'mr-picker__hours' });
            details.append(element('summary', {}, this.label('openingHours')));
            const table = element('dl');
            const weekday = new Intl.DateTimeFormat(document.documentElement.lang || undefined, { weekday: 'long' });
            point.openingHours.forEach(({ day, slots }) => {
                // 2024-01-01 is a Monday: day 1 → Monday
                table.append(
                    element('dt', {}, weekday.format(new Date(2024, 0, day))),
                    element('dd', {}, slots.length ? slots.join(', ') : this.label('closed')),
                );
            });
            details.append(table);
            li.append(details);
        }

        return li;
    }

    select(point, announce = true) {
        this.selectedValue = point.id;

        this.list.querySelectorAll('.mr-picker__item').forEach((li) => {
            const isSelected = li.dataset.relayPoint === point.id;
            li.classList.toggle('mr-picker__item--selected', isSelected);
            li.querySelector('.mr-picker__choose').setAttribute('aria-pressed', String(isSelected));
        });
        this.list.querySelector(`[data-relay-point="${CSS.escape(point.id)}"]`)?.scrollIntoView({ block: 'nearest' });
        this.markers.get(point.id)?.openPopup();

        if (this.hasIdTarget) this.idTarget.value = point.id;
        if (this.hasNameTarget) this.nameTarget.value = point.name;
        if (this.hasAddressTarget) this.addressTarget.value = point.address;
        if (this.hasSummaryTarget) this.summaryTarget.textContent = `${point.name} — ${point.address}`;

        if (announce) {
            this.status.textContent = this.label('selected', { name: point.name });
            this.dispatch('select', { detail: { ...point } });
        }
    }

    escape(text) {
        const div = document.createElement('div');
        div.textContent = text;

        return div.innerHTML;
    }
}
