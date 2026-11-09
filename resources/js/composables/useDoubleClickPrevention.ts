import { ref } from 'vue';

/**
 * Composable para prevenir duplo clique em botões por 3 segundos
 * @param delayMs - Tempo em milissegundos para bloqueio (padrão: 3000ms)
 * @returns { isClicking, handleClick }
 */
export function useDoubleClickPrevention(delayMs: number = 3000) {
  const isClicking = ref(false);

  const handleClick = async (callback: () => void | Promise<void>) => {
    if (isClicking.value) {
      return;
    }

    isClicking.value = true;

    try {
      await callback();
    } finally {
      setTimeout(() => {
        isClicking.value = false;
      }, delayMs);
    }
  };

  return {
    isClicking,
    handleClick,
  };
}
