<div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0 bg-orange-500 rounded-md p-3">
                <i class="fas fa-ticket-alt text-white text-xl"></i>
            </div>
            <h3 class="ml-3 text-lg leading-6 font-medium text-gray-900">
                🎫 Ticket da Completare
            </h3>
        </div>
        
        <div class="mt-5">
            <div class="flex items-baseline">
                <p class="text-4xl font-bold text-gray-900">{{ $ticketsCount }}</p>
                <p class="ml-2 text-sm text-gray-500">ticket</p>
            </div>
            
            <div class="mt-4">
                <p class="text-sm text-gray-600">
                    Ticket con stati: Nuovo, Backlog, Assegnato, Todo, In Corso, Da Testare, Problema, In Attesa
                </p>
            </div>
            
            <div class="mt-6">
                <a href="/resources/story-showed-by-customers" style="display: inline-block; padding: 10px 20px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#1d4ed8'" onmouseout="this.style.backgroundColor='#2563eb'">
                    Vedi tutti i ticket <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                </a>
            </div>
        </div>
    </div>
</div>

