// Build CSP do Alpine: não usa eval, então a Content-Security-Policy segue estrita.
import Alpine from '@alpinejs/csp';

Alpine.data('toggle', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
}));

// Dica de disponibilidade; quem decide é a validação do servidor.
Alpine.data('subdomain', () => ({
    message: '',
    async check(event) {
        const value = event.target.value;
        const url = `${this.$root.dataset.url}?valor=${encodeURIComponent(value)}`;
        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        if (value === event.target.value) {
            this.message = response.ok ? (await response.json()).message : '';
        }
    },
}));

// Autocompletar de paciente. O servidor devolve só quem a pessoa pode ver.
Alpine.data('patientPicker', () => ({
    id: '',
    init() {
        this.id = this.$root.dataset.id ?? '';
    },
    async search(event) {
        const input = event.target;
        const term = input.value.trim();
        this.id = '';
        this.$refs.results.replaceChildren();

        if (term.length < 2) {
            return;
        }

        const response = await fetch(`${this.$root.dataset.url}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });

        if (!response.ok || term !== input.value.trim()) {
            return;
        }

        this.$refs.results.replaceChildren(...(await response.json()).map((patient) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'block w-full px-3 py-2 text-left hover:bg-brand-50';
            button.textContent = patient.name;
            button.addEventListener('click', () => {
                this.id = patient.id;
                input.value = patient.name;
                this.$refs.results.replaceChildren();
            });
            item.append(button);

            return item;
        }));
    },
}));

Alpine.start();
