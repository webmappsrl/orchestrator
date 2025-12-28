<div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                <i class="fas fa-money-bill-wave text-white text-xl"></i>
            </div>
            <h3 class="ml-3 text-lg leading-6 font-medium text-gray-900">
                💰 Budget
            </h3>
        </div>
        
        <div class="mt-5">
            @if($googleDriveBudgetUrl)
                <div class="mt-4">
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('Accesso alla cartella DRIVE per archiviazione dei documenti. È necessario essere loggati con un account abilitato all\'accesso.') }}
                    </p>
                    <a href="{{ $googleDriveBudgetUrl }}" target="_blank" style="display: inline-block; padding: 10px 20px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'">
                        Apri Google Drive <i class="fas fa-external-link-alt" style="margin-left: 8px;"></i>
                    </a>
                </div>
            @else
                <div class="mt-4">
                    <p class="text-sm text-gray-600" style="color: #dc2626;">
                        {{ __('Le funzionalità relative alla gestione del budget, non sono state attivate (Cartella archiviazione per il budget). Contattare l\'amministrazione per attivarle') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>

