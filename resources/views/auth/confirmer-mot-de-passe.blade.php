<x-layouts.base titre="Confirmation" classe-body="fond-nuit min-vh-100 d-flex align-items-center">
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4">
                        <h1 class="h5 fw-bold mb-2">Confirmez votre identité</h1>
                        <p class="text-secondary small">Cette action est sensible. Saisissez à nouveau votre PIN pour continuer.</p>

                        <form method="POST" action="{{ route('password.confirm.store') }}" x-data="{ envoi: false }" x-on:submit="envoi = true">
                            @csrf

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">PIN</label>
                                <input type="password" id="password" name="password" required autofocus
                                       inputmode="numeric" maxlength="5" autocomplete="current-password"
                                       class="form-control form-control-lg @error('password') is-invalid @enderror"
                                       @error('password') aria-describedby="erreur-password" @enderror>
                                @error('password')
                                    <div class="invalid-feedback" id="erreur-password">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100" x-bind:disabled="envoi">Confirmer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-layouts.base>
