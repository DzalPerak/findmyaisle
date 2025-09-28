/**
 * Global notification utility using Flowbite styling
 */

export type NotificationType = 'success' | 'error' | 'warning' | 'info';

interface NotificationConfig {
    title: string;
    message?: string;
    type?: NotificationType;
    duration?: number;
}

export function showNotification({ title, message = '', type = 'info', duration = 5000 }: NotificationConfig): void {
    let bgColor, borderColor, textColor, icon;

    switch (type) {
        case 'success':
            bgColor = 'bg-green-100 dark:bg-green-800';
            borderColor = 'border-green-500 dark:border-green-700';
            textColor = 'text-green-900 dark:text-green-300';
            icon = `<svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="m15.863 4.398a1.5 1.5 0 0 0-2.226-2.002L10 6.862 6.363 2.396a1.5 1.5 0 0 0-2.226 2.002L7.774 9 4.137 13.602a1.5 1.5 0 0 0 2.226 2.002L10 11.138l3.637 4.466a1.5 1.5 0 0 0 2.226-2.002L12.226 9l3.637-4.602Z"/>
                    </svg>`;
            break;
        case 'error':
            bgColor = 'bg-red-100 dark:bg-red-800';
            borderColor = 'border-red-500 dark:border-red-700';
            textColor = 'text-red-900 dark:text-red-300';
            icon = `<svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 11.793a1 1 0 1 1-1.414 1.414L10 11.414l-2.293 2.293a1 1 0 0 1-1.414-1.414L8.586 10 6.293 7.707a1 1 0 0 1 1.414-1.414L10 8.586l2.293-2.293a1 1 0 0 1 1.414 1.414L11.414 10l2.293 2.293Z"/>
                    </svg>`;
            break;
        case 'warning':
            bgColor = 'bg-yellow-100 dark:bg-yellow-800';
            borderColor = 'border-yellow-500 dark:border-yellow-700';
            textColor = 'text-yellow-900 dark:text-yellow-300';
            icon = `<svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM10 4a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-3 0v-3A1.5 1.5 0 0 1 10 4Zm0 8a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/>
                    </svg>`;
            break;
        default: // info
            bgColor = 'bg-blue-100 dark:bg-blue-800';
            borderColor = 'border-blue-500 dark:border-blue-700';
            textColor = 'text-blue-900 dark:text-blue-300';
            icon = `<svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>`;
    }

    const notificationId = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    const notification = document.createElement("div");
    notification.id = notificationId;
    notification.className = `fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 mb-4 ${textColor} ${bgColor} rounded-lg shadow-lg border-l-4 ${borderColor} animate-slide-in`;
    
    const messageHtml = message ? `<div class="text-xs mt-1 opacity-90">${message}</div>` : '';
    
    notification.innerHTML = `
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 ${textColor} rounded-lg">
            ${icon}
            <span class="sr-only">${type} icon</span>
        </div>
        <div class="ms-3 text-sm font-normal flex-1">
            <div class="font-semibold">${title}</div>
            ${messageHtml}
        </div>
        <button type="button" class="ms-auto -mx-1.5 -my-1.5 ${textColor} hover:${textColor} rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-black hover:bg-opacity-10 inline-flex items-center justify-center h-8 w-8 transition-colors" onclick="document.getElementById('${notificationId}').remove()">
            <span class="sr-only">Close</span>
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
            </svg>
        </button>
    `;

    // Add CSS animation if not already present
    if (!document.head.querySelector('#toast-animations')) {
        const style = document.createElement('style');
        style.id = 'toast-animations';
        style.textContent = `
            @keyframes slide-in {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            .animate-slide-in {
                animation: slide-in 0.3s ease-out;
            }
        `;
        document.head.appendChild(style);
    }

    // Find or create notification container
    let container = document.getElementById('notifications-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notifications-container';
        container.className = 'fixed top-5 right-5 z-50 space-y-2';
        document.body.appendChild(container);
    }

    container.appendChild(notification);

    // Auto-remove after specified duration
    setTimeout(() => {
        if (document.getElementById(notificationId)) {
            notification.style.transition = 'all 0.3s ease-out';
            notification.style.transform = 'translateX(100%)';
            notification.style.opacity = '0';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }, duration);
}

// Convenience methods
export const notify = {
    success: (title: string, message?: string, duration?: number) => 
        showNotification({ title, message, type: 'success', duration }),
    error: (title: string, message?: string, duration?: number) => 
        showNotification({ title, message, type: 'error', duration }),
    warning: (title: string, message?: string, duration?: number) => 
        showNotification({ title, message, type: 'warning', duration }),
    info: (title: string, message?: string, duration?: number) => 
        showNotification({ title, message, type: 'info', duration }),
};