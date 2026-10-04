            <div class="space-y-3">
                @foreach ($platforms as $p)
                    @php
                        $sub = $subscriptions->get($p->id);

                        $oldPlatforms = session()->hasOldInput() ? old('platforms', []) : null;
                        $isChecked = is_array($oldPlatforms)
                            ? in_array((int) $p->id, array_map('intval', $oldPlatforms), true)
                            : (bool) ($sub?->pivot?->is_active ?? false);

                        $viaOld = old('via.' . $p->id, $sub?->pivot?->subscribed_via_platform_id);
                        $notifyOld = old('notify.' . $p->id, $sub?->pivot?->notify_opt_in ?? true);
                    @endphp

                    <div class="rounded border border-slate-700 bg-slate-900/40 p-3"
                         data-platform-row="{{ $p->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <label class="flex items-center gap-2">
                                <input type="checkbox"
                                       class="rounded border-slate-600 platform-checkbox"
                                       name="platforms[]"
                                       value="{{ $p->id }}"
                                       {{ $isChecked ? 'checked' : '' }}>
                                <span class="text-sm font-semibold">{{ $p->name }}</span>
                                <span class="text-xs text-slate-500">({{ $p->slug }})</span>
                            </label>

                        </div>

                        {{-- Options par plateforme (affichées si cochée) --}}
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 platform-options {{ $isChecked ? '' : 'hidden' }}"
                             data-platform-options="{{ $p->id }}">

                            <div>
                                <label class="block text-xs mb-1 text-slate-400">Souscrit via</label>
                                <select name="via[{{ $p->id }}]"
                                        class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm">
                                    <option value="">- Direct -</option>
                                    @foreach ($platforms as $viaP)
                                        @if ($viaP->id !== $p->id)
                                            <option value="{{ $viaP->id }}" {{ (string)$viaOld === (string)$viaP->id ? 'selected' : '' }}>
                                                {{ $viaP->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex items-start gap-3">
                                {{-- hidden pour garantir une valeur envoyée --}}
                                <input type="hidden" name="notify[{{ $p->id }}]" value="0">

                                <input id="notify_{{ $p->id }}"
                                       type="checkbox"
                                       name="notify[{{ $p->id }}]"
                                       value="1"
                                       class="mt-1 rounded border-slate-600"
                                       {{ $notifyOld ? 'checked' : '' }}>

                                <label for="notify_{{ $p->id }}" class="text-sm">
                                    <div class="font-semibold">Notifications pour {{ $p->name }}</div>
                                    <div class="text-xs text-slate-400">
                                        Recevoir des notifications liées à cette plateforme.
                                    </div>
                                </label>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
