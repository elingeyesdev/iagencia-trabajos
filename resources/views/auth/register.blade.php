<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Cedula -->
        <div>
            <x-input-label for="cedula" :value="__('Cédula')" />
            <x-text-input id="cedula" class="block mt-1 w-full" type="number" name="cedula" :value="old('cedula')" required autofocus />
            <x-input-error :messages="$errors->get('cedula')" class="mt-2" />
        </div>

        <!-- Nombre -->
        <div class="mt-4">
            <x-input-label for="nombre" :value="__('Nombre completo')" />
            <x-text-input id="nombre" class="block mt-1 w-full" type="text" name="nombre" :value="old('nombre')" required />
            <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
        </div>

        <!-- Email -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Telefono -->
        <div class="mt-4">
            <x-input-label for="telefono" :value="__('Teléfono')" />
            <x-text-input id="telefono" class="block mt-1 w-full" type="text" name="telefono" :value="old('telefono')" />
            <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
        </div>

        <!-- Direccion -->
        <div class="mt-4">
            <x-input-label for="direccion" :value="__('Dirección')" />
            <x-text-input id="direccion" class="block mt-1 w-full" type="text" name="direccion" :value="old('direccion')" />
            <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
        </div>

        <!-- Ciudad -->
        <div class="mt-4">
            <x-input-label for="ciudad" :value="__('Ciudad')" />
            <x-text-input id="ciudad" class="block mt-1 w-full" type="text" name="ciudad" :value="old('ciudad')" />
            <x-input-error :messages="$errors->get('ciudad')" class="mt-2" />
        </div>

        <!-- Fecha Nacimiento -->
        <div class="mt-4">
            <x-input-label for="fechanac" :value="__('Fecha de nacimiento')" />
            <x-text-input id="fechanac" class="block mt-1 w-full" type="date" name="fechanac" :value="old('fechanac')" />
            <x-input-error :messages="$errors->get('fechanac')" class="mt-2" />
        </div>

        <!-- ROL -->
        <div class="mt-4">
            <x-input-label :value="__('¿Cómo quieres unirte?')" />
            <div class="mt-2 grid grid-cols-2 gap-3">

                <label class="rol-option cursor-pointer">
                    <input type="radio" name="rol" value="trabajador"
                        class="hidden rol-radio"
                        {{ old('rol') == 'trabajador' ? 'checked' : '' }}>
                    <div class="rol-card flex flex-col items-center justify-center p-4 rounded-xl border-2 border-gray-200 dark:border-gray-700 transition-all duration-200 hover:border-red-500">
                        <span class="text-3xl mb-2">🔧</span>
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Trabajador</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 text-center mt-1">Ofrece tus servicios</span>
                    </div>
                </label>

                <label class="rol-option cursor-pointer">
                    <input type="radio" name="rol" value="contratador"
                        class="hidden rol-radio"
                        {{ old('rol') == 'contratador' ? 'checked' : '' }}>
                    <div class="rol-card flex flex-col items-center justify-center p-4 rounded-xl border-2 border-gray-200 dark:border-gray-700 transition-all duration-200 hover:border-red-500">
                        <span class="text-3xl mb-2">💼</span>
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Contratador</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 text-center mt-1">Publica trabajos</span>
                    </div>
                </label>

            </div>
            <x-input-error :messages="$errors->get('rol')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Contraseña')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirmar contraseña')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
                href="{{ route('login') }}">
                {{ __('¿Ya tienes cuenta?') }}
            </a>
            <x-primary-button class="ms-4">
                {{ __('Registrarse') }}
            </x-primary-button>
        </div>
    </form>

    <style>
        .rol-radio:checked + .rol-card {
            border-color: #dc2626;
            background-color: rgba(220, 38, 38, 0.05);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
        }
        .rol-radio:checked + .rol-card span.font-semibold {
            color: #dc2626;
        }    
    </style>
</x-guest-layout>