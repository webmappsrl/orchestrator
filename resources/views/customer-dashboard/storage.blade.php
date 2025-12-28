<div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                <i class="fas fa-folder text-white text-xl"></i>
            </div>
            <h3 class="ml-3 text-lg leading-6 font-medium text-gray-900">
                📁 Archiviazione
            </h3>
        </div>
        
        <div class="mt-5">
            @if($googleDriveUrl)
                <div class="mt-4">
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('Accesso alla cartella DRIVE per archiviazione dei documenti. È necessario essere loggati con un account abilitato all\'accesso.') }}
                    </p>
                    <a href="{{ $googleDriveUrl }}" target="_blank" style="display: inline-block; padding: 10px 20px; background-color: #10b981; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#059669'" onmouseout="this.style.backgroundColor='#10b981'">
                        Apri Google Drive <i class="fas fa-external-link-alt" style="margin-left: 8px;"></i>
                    </a>
                </div>
            @else
                <div class="mt-4">
                    <p class="text-sm text-gray-600" style="color: #dc2626;">
                        {{ __('Cartella archiviazione non configurata: contattare l\'amministrazione per attivarla') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>

