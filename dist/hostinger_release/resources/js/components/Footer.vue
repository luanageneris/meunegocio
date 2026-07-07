<script setup lang="ts">
const currentYear = new Date().getFullYear();
</script>

<template>
    <footer class="bg-gradient-to-br from-amber-50 to-orange-100 border-t border-orange-200/50 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 3.5a1.5 1.5 0 013 0V4a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-.5a1.5 1.5 0 000 3h.5a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-.5a1.5 1.5 0 00-3 0v.5a1 1 0 01-1 1H6a1 1 0 01-1-1v-3a1 1 0 00-1-1h-.5a1.5 1.5 0 010-3H4a1 1 0 001-1V6a1 1 0 011-1h3a1 1 0 001-1v-.5z"/>
                    </svg>
                    <span class="font-medium">MEU NEGÓCIO</span>
                </div>
                
                <div class="text-sm text-gray-600 text-center">
                    <p>© {{ currentYear }} MEU NEGÓCIO. Todos os direitos reservados.</p>
                </div>
                
                <div class="flex items-center gap-4 text-sm text-gray-500">
                    <a href="/termos" class="hover:text-orange-600 transition">Termos</a>
                    <span class="text-gray-300">•</span>
                    <a href="/politica-de-privacidade" class="hover:text-orange-600 transition">Privacidade</a>
                </div>
            </div>
        </div>
    </footer>
</template>
