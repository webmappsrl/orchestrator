<div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                <i class="fas fa-user text-white text-xl"></i>
            </div>
            <h3 class="ml-3 text-lg leading-6 font-medium text-gray-900">
                Informazioni Login
            </h3>
        </div>
        
        <div class="mt-5 space-y-4">
            <div>
                <dt class="text-sm font-medium text-gray-500">Nome</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $loginInfo['name'] }}</dd>
            </div>
            
            <div>
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $loginInfo['email'] }}</dd>
            </div>
            
            <div>
                <dt class="text-sm font-medium text-gray-500">Ultimo Accesso</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if($loginInfo['last_login'] !== 'Mai')
                        {{ \Carbon\Carbon::parse($loginInfo['last_login'])->format('d/m/Y H:i') }}
                    @else
                        <span class="text-gray-400">{{ $loginInfo['last_login'] }}</span>
                    @endif
                </dd>
            </div>
        </div>
    </div>
</div>

