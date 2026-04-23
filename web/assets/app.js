document.addEventListener('DOMContentLoaded', () => {
  setupDishDialog();
  setupOrderStreams();
});

function setupDishDialog() {
  const dialog = document.querySelector('[data-dish-dialog]');
  if (!(dialog instanceof HTMLDialogElement)) {
    return;
  }

  const title = dialog.querySelector('[data-dish-dialog-title]');
  const description = dialog.querySelector('[data-dish-dialog-description]');
  const image = dialog.querySelector('[data-dish-dialog-image]');
  const emptyState = dialog.querySelector('[data-dish-dialog-empty]');

  document.querySelectorAll('[data-dish-trigger]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const name = trigger.getAttribute('data-dish-name') || 'Dish';
      const body = trigger.getAttribute('data-dish-description') || 'Details coming soon.';
      const imageUrl = trigger.getAttribute('data-dish-image') || '';

      if (title) {
        title.textContent = name;
      }
      if (description) {
        description.textContent = body;
      }

      if (image instanceof HTMLImageElement && emptyState) {
        if (imageUrl) {
          image.src = imageUrl;
          image.alt = name;
          image.hidden = false;
          emptyState.hidden = true;
        } else {
          image.hidden = true;
          emptyState.hidden = false;
        }
      }

      dialog.showModal();
    });
  });

  dialog.querySelector('[data-dish-close]')?.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => {
    const rect = dialog.getBoundingClientRect();
    const isInDialog =
      rect.top <= event.clientY &&
      event.clientY <= rect.top + rect.height &&
      rect.left <= event.clientX &&
      event.clientX <= rect.left + rect.width;

    if (!isInDialog) {
      dialog.close();
    }
  });
}

function setupOrderStreams() {
  const streamRoots = document.querySelectorAll('[data-order-stream]');
  streamRoots.forEach((root) => {
    const endpoint = root.getAttribute('data-order-stream');
    if (!endpoint || typeof EventSource === 'undefined') {
      return;
    }

    let currentVersion = null;
    const source = new EventSource(endpoint);
    source.addEventListener('orders-update', (event) => {
      try {
        const payload = JSON.parse(event.data);
        if (!payload.version) {
          return;
        }

        if (currentVersion === null) {
          currentVersion = payload.version;
          return;
        }

        if (currentVersion !== payload.version) {
          window.location.reload();
        }
      } catch (error) {
        console.error('Unable to parse order stream payload.', error);
      }
    });
  });
}
