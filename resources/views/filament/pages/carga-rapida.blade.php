<x-filament-panels::page>
    <div class="carga-rapida-layout">

        {{-- ── Columna principal ─────────────────────────────── --}}
        <div class="flex flex-col gap-8">

            <div @class([
                'carga-rapida-card transition',
                'ring-2 ring-primary-500/40' => $showForm,
            ])>

                {{-- Cabecera --}}
                <div class="carga-rapida-card-header">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $showForm ? 'Completar producto' : 'Escaneá o cargá manualmente' }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $showForm ? 'Nombre, precio y stock opcional' : 'El lector dispara solo con Enter' }}
                            </p>
                        </div>
                    </div>

                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold uppercase tracking-wide',
                        'bg-success-500/10 text-success-600 dark:text-success-400' => ! $showForm,
                        'bg-warning-500/10 text-warning-600 dark:text-warning-400' => $showForm,
                    ])>
                        <span @class([
                            'h-1.5 w-1.5 rounded-full',
                            'bg-success-500 animate-pulse' => ! $showForm,
                            'bg-warning-500' => $showForm,
                        ])></span>
                        {{ $showForm ? 'Paso 2' : 'Paso 1' }}
                    </span>
                </div>

                {{-- Escáner --}}
                <div class="carga-rapida-card-body">
                    <label class="mb-3 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Código de barras
                    </label>

                    <div class="carga-rapida-scanner-row">
                        <div class="relative min-w-0 flex-1">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2">
                                <x-filament::icon
                                    icon="heroicon-o-qr-code"
                                    wire:loading.remove
                                    wire:target="scanBarcode"
                                    class="h-5 w-5 text-gray-400"
                                />
                                <svg wire:loading wire:target="scanBarcode" class="h-5 w-5 animate-spin text-primary-500" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                            </span>
                            <input
                                wire:model="barcodeInput"
                                wire:keydown.enter.prevent="scanBarcode"
                                type="text"
                                placeholder="Apuntá el escáner aquí…"
                                autocomplete="off"
                                x-init="$el.focus()"
                                x-on:focus-barcode.window="$nextTick(() => $el.focus())"
                                @if($showForm) disabled @endif
                                class="carga-rapida-input fi-input block w-full rounded-xl border border-gray-300 bg-white pl-10 pr-4 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder-gray-500"
                            />
                        </div>

                        @if (! $showForm)
                            <button
                                wire:click="scanBarcode"
                                wire:loading.attr="disabled"
                                wire:target="scanBarcode"
                                type="button"
                                class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:opacity-60 sm:w-auto"
                            >
                                <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-4 w-4" wire:loading.remove wire:target="scanBarcode" />
                                <span wire:loading.remove wire:target="scanBarcode">Buscar</span>
                                <span wire:loading wire:target="scanBarcode">Buscando…</span>
                            </button>
                        @endif
                    </div>

                    @if (! $showForm)
                        <div class="carga-rapida-footer-row">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Tras guardar, el foco vuelve acá automáticamente.
                            </p>
                            <button
                                wire:click="startManualEntry"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-primary-600 transition hover:bg-primary-50 dark:border-white/10 dark:text-primary-400 dark:hover:bg-primary-500/10"
                            >
                                <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                                Sin código de barras
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Formulario --}}
                @if ($showForm)
                    <div
                        x-data
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        class="carga-rapida-form-zone"
                    >
                        @if ($manualMode)
                            <div class="mb-6 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                                Carga manual
                            </div>
                        @elseif ($barcodeInput)
                            <div class="mb-6 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                <span class="text-gray-500 dark:text-gray-400">Código</span>
                                <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $barcodeInput }}</span>
                            </div>
                        @endif

                        <div class="carga-rapida-fields">
                            <div class="field-full">
                                <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Nombre <span class="text-danger-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        wire:model="productName"
                                        wire:keydown.enter.prevent="saveProduct"
                                        type="text"
                                        placeholder="Ej: Tornillo autorroscante 3/4&quot;"
                                        autocomplete="off"
                                        x-on:focus-name.window="$nextTick(() => $el.focus())"
                                        class="carga-rapida-input fi-input block w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-gray-900 dark:text-white {{ $lookupSource === 'api' ? 'pr-24' : '' }}"
                                    />
                                    @if ($lookupSource === 'api')
                                        <span class="absolute inset-y-0 right-3 flex items-center">
                                            <span class="inline-flex items-center gap-1 rounded-md bg-info-500/10 px-2 py-1 text-xs font-semibold text-info-600 dark:text-info-400">
                                                <x-filament::icon icon="heroicon-o-sparkles" class="h-3.5 w-3.5" />
                                                Auto
                                            </span>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Precio de venta <span class="text-danger-500">*</span>
                                </label>
                                <div class="flex">
                                    <span class="carga-rapida-input inline-flex items-center rounded-l-xl border border-r-0 border-gray-300 bg-gray-100 px-4 text-sm font-medium text-gray-500 dark:border-white/15 dark:bg-white/5 dark:text-gray-400">$</span>
                                    <input
                                        wire:model="salePrice"
                                        wire:keydown.enter.prevent="saveProduct"
                                        type="number"
                                        inputmode="decimal"
                                        placeholder="0,00"
                                        min="0"
                                        step="0.01"
                                        x-on:focus-price.window="$nextTick(() => $el.focus())"
                                        class="carga-rapida-input fi-input block w-full min-w-0 rounded-r-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-gray-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="mb-2.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Stock inicial
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-normal text-gray-400 dark:bg-white/10 dark:text-gray-500">opcional</span>
                                </label>
                                <div class="flex">
                                    <span class="carga-rapida-input inline-flex items-center rounded-l-xl border border-r-0 border-gray-300 bg-gray-100 px-4 text-gray-400 dark:border-white/15 dark:bg-white/5">
                                        <x-filament::icon icon="heroicon-o-cube" class="h-4 w-4" />
                                    </span>
                                    <input
                                        wire:model="stockInput"
                                        wire:keydown.enter.prevent="saveProduct"
                                        type="number"
                                        inputmode="numeric"
                                        placeholder="0"
                                        min="0"
                                        step="1"
                                        x-on:focus-stock.window="$nextTick(() => $el.focus())"
                                        class="carga-rapida-input fi-input block w-full min-w-0 rounded-r-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-gray-900 dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="carga-rapida-actions">
                            <button
                                wire:click="saveProduct"
                                wire:loading.attr="disabled"
                                wire:target="saveProduct"
                                type="button"
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-success-600 px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-success-500 focus:outline-none focus:ring-2 focus:ring-success-500/40 disabled:opacity-60"
                            >
                                <span class="inline-flex items-center gap-2" wire:loading.remove wire:target="saveProduct">
                                    <x-filament::icon icon="heroicon-o-check" class="h-5 w-5" />
                                    Guardar y continuar
                                </span>
                                <span wire:loading wire:target="saveProduct">Guardando…</span>
                            </button>
                            <button
                                wire:click="cancelForm"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-white/15 dark:bg-transparent dark:text-gray-400 dark:hover:bg-white/5"
                            >
                                <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                                Cancelar
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            @if ($showForm)
                <p class="px-1 text-sm text-gray-400 dark:text-gray-500">
                    <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:border-white/15 dark:bg-white/5">Enter</kbd>
                    guarda ·
                    <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:border-white/15 dark:bg-white/5">Tab</kbd>
                    siguiente campo
                </p>
            @endif
        </div>

        {{-- ── Sidebar ─────────────────────────────────────────── --}}
        <div class="carga-rapida-sidebar">

            {{-- Sesión --}}
            <div class="carga-rapida-card">
                <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-white/10">
                    <div class="px-6 py-7 text-center">
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Sesión</p>
                        <p class="mt-3 text-4xl font-bold tabular-nums text-primary-600 dark:text-primary-400">{{ $sessionCount }}</p>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $sessionCount === 1 ? 'producto' : 'productos' }}</p>
                    </div>
                    <div class="flex flex-col items-center justify-center gap-2.5 px-6 py-7">
                        @if ($sessionCount > 0)
                            <a
                                href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('index') }}?tableFilters[incomplete][isActive]=1"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 transition hover:text-primary-500 dark:text-primary-400"
                            >
                                Completar datos
                                <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                            </a>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Costo, margen, categoría</p>
                        @else
                            <x-filament::icon icon="heroicon-o-inbox" class="h-9 w-9 text-gray-300 dark:text-gray-600" />
                            <p class="text-sm text-gray-400 dark:text-gray-500">Sin cargas aún</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Recientes --}}
            <div class="carga-rapida-card">
                <div class="flex items-center justify-between px-6 py-5">
                    <div class="flex items-center gap-2.5">
                        <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-gray-400" />
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Recientes</h3>
                    </div>
                    @if (count($recentProducts) > 0)
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-white/10 dark:text-gray-400">
                            {{ count($recentProducts) }}
                        </span>
                    @endif
                </div>

                @if (count($recentProducts) > 0)
                    <div class="max-h-80 overflow-y-auto px-2 pb-2">
                        @foreach ($recentProducts as $recent)
                            <div
                                wire:key="recent-{{ $recent['id'] }}"
                                class="carga-rapida-recent-item group flex items-center gap-3 rounded-lg px-4 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-200" title="{{ $recent['name'] }}">
                                        {{ $recent['name'] }}
                                    </p>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <span class="text-sm font-semibold tabular-nums text-primary-600 dark:text-primary-400">
                                            ${{ number_format($recent['price'], 2, ',', '.') }}
                                        </span>
                                        @if (($recent['stock'] ?? 0) > 0)
                                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                                · {{ $recent['stock'] }} u.
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <button
                                    wire:click="removeRecent({{ $recent['id'] }})"
                                    wire:confirm="¿Eliminar &quot;{{ $recent['name'] }}&quot;?"
                                    type="button"
                                    title="Deshacer"
                                    class="shrink-0 rounded-lg p-2 text-gray-300 opacity-0 transition hover:bg-danger-500/10 hover:text-danger-500 group-hover:opacity-100 dark:text-gray-600 dark:hover:text-danger-400"
                                >
                                    <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-6 pb-8 pt-2 text-center">
                        <p class="text-sm leading-relaxed text-gray-400 dark:text-gray-500">
                            Los productos que guardes<br>aparecen acá al instante.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Tips --}}
            <div class="carga-rapida-tip-card">
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                    <x-filament::icon icon="heroicon-o-light-bulb" class="h-4 w-4 text-primary-500" />
                    Tips
                </p>
                <ul class="space-y-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                    <li>Solo nombre y precio son obligatorios.</li>
                    <li>Stock opcional — vacío = 0.</li>
                    <li>Pasá el mouse sobre un reciente para deshacer.</li>
                </ul>
            </div>

        </div>
    </div>
</x-filament-panels::page>
