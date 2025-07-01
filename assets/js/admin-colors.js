/**
 * Palmerita Subscriptions - Admin Color Enhancement
 * Provides color palette suggestions and improved UX for color customization
 */

(function($) {
    'use strict';
    
    // Color palettes for different button types
    const colorPalettes = {
        cv: [
            { bg: '#6366f1', text: '#ffffff', name: 'Indigo Classic' },
            { bg: '#3b82f6', text: '#ffffff', name: 'Blue Professional' },
            { bg: '#8b5cf6', text: '#ffffff', name: 'Purple Modern' },
            { bg: '#10b981', text: '#ffffff', name: 'Green Success' },
            { bg: '#f59e0b', text: '#ffffff', name: 'Amber Warm' },
            { bg: '#ef4444', text: '#ffffff', name: 'Red Bold' },
            { bg: '#1f2937', text: '#ffffff', name: 'Dark Professional' },
            { bg: '#ffffff', text: '#1f2937', name: 'Light Clean' }
        ],
        promo: [
            { bg: '#f59e0b', text: '#ffffff', name: 'Amber Attention' },
            { bg: '#ef4444', text: '#ffffff', name: 'Red Urgent' },
            { bg: '#ec4899', text: '#ffffff', name: 'Pink Vibrant' },
            { bg: '#8b5cf6', text: '#ffffff', name: 'Purple Creative' },
            { bg: '#06b6d4', text: '#ffffff', name: 'Cyan Fresh' },
            { bg: '#84cc16', text: '#ffffff', name: 'Lime Energy' },
            { bg: '#f97316', text: '#ffffff', name: 'Orange Dynamic' },
            { bg: '#dc2626', text: '#ffffff', name: 'Red Power' }
        ],
        file: [
            { bg: '#1f2937', text: '#ffffff', name: 'Dark Professional' },
            { bg: '#374151', text: '#ffffff', name: 'Gray Corporate' },
            { bg: '#6366f1', text: '#ffffff', name: 'Indigo Tech' },
            { bg: '#059669', text: '#ffffff', name: 'Green Secure' },
            { bg: '#7c3aed', text: '#ffffff', name: 'Purple Premium' },
            { bg: '#dc2626', text: '#ffffff', name: 'Red Important' },
            { bg: '#0891b2', text: '#ffffff', name: 'Teal Modern' },
            { bg: '#4f46e5', text: '#ffffff', name: 'Indigo Deep' }
        ]
    };
    
    // Initialize color enhancements
    function initColorEnhancements() {
        // Add color palette suggestions
        addColorPalettes();
        
        // Add color harmony suggestions
        addColorHarmony();
        
        // Add accessibility checker
        enhanceAccessibilityChecker();
        
        // Add copy/paste functionality
        addColorCopyPaste();
        
        // Add keyboard shortcuts
        addKeyboardShortcuts();
    }
    
    // Add color palette suggestions
    function addColorPalettes() {
        $('.palmerita-color-group').each(function() {
            const $group = $(this);
            const $bgInput = $group.find('input[type="color"]');
            const buttonType = getButtTypeFromId($bgInput.attr('id'));
            
            if (buttonType && colorPalettes[buttonType]) {
                const $paletteContainer = $('<div class="palmerita-color-palette"></div>');
                const $paletteTitle = $('<h5>Paletas sugeridas:</h5>');
                const $paletteGrid = $('<div class="palette-grid"></div>');
                
                colorPalettes[buttonType].forEach(palette => {
                    const $paletteItem = $(`
                        <div class="palette-item" 
                             data-bg="${palette.bg}" 
                             data-text="${palette.text}"
                             title="${palette.name}">
                            <div class="palette-preview" style="background-color: ${palette.bg}; color: ${palette.text};">
                                <span>Aa</span>
                            </div>
                            <span class="palette-name">${palette.name}</span>
                        </div>
                    `);
                    
                    $paletteItem.on('click', function() {
                        const bgColor = $(this).data('bg');
                        const textColor = $(this).data('text');
                        
                        // Apply colors
                        const bgInputId = $bgInput.attr('id');
                        const textInputId = bgInputId.replace('_btn_color', '_text_color');
                        
                        $bgInput.val(bgColor).trigger('change');
                        $(`#${textInputId}`).val(textColor).trigger('change');
                        
                        // Update hex inputs
                        $(`[data-color-input="${bgInputId}"]`).val(bgColor);
                        $(`[data-color-input="${textInputId}"]`).val(textColor);
                        
                        // Visual feedback
                        $(this).addClass('palette-applied');
                        setTimeout(() => $(this).removeClass('palette-applied'), 1000);
                    });
                    
                    $paletteGrid.append($paletteItem);
                });
                
                $paletteContainer.append($paletteTitle, $paletteGrid);
                $group.closest('td').append($paletteContainer);
            }
        });
    }
    
    // Add color harmony suggestions
    function addColorHarmony() {
        $('.btn-bg-color').on('change', function() {
            const bgColor = $(this).val();
            const buttonType = getButtTypeFromId($(this).attr('id'));
            const suggestions = generateHarmonySuggestions(bgColor);
            
            showHarmonySuggestions($(this), suggestions);
        });
    }
    
    // Generate color harmony suggestions
    function generateHarmonySuggestions(baseColor) {
        const hsl = hexToHsl(baseColor);
        
        return [
            {
                name: 'Complementario',
                bg: baseColor,
                text: hslToHex((hsl.h + 180) % 360, hsl.s, hsl.l > 50 ? 20 : 80)
            },
            {
                name: 'Análogo',
                bg: baseColor,
                text: hslToHex((hsl.h + 30) % 360, hsl.s, hsl.l > 50 ? 20 : 80)
            },
            {
                name: 'Triádico',
                bg: baseColor,
                text: hslToHex((hsl.h + 120) % 360, hsl.s, hsl.l > 50 ? 20 : 80)
            }
        ];
    }
    
    // Enhanced accessibility checker
    function enhanceAccessibilityChecker() {
        // Add WCAG level indicators
        $('.contrast-indicator').each(function() {
            const $indicator = $(this);
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        const text = $indicator.text();
                        const ratio = parseFloat(text.match(/(\d+\.\d+):1/)?.[1] || '0');
                        
                        let level = '';
                        if (ratio >= 7) level = 'AAA';
                        else if (ratio >= 4.5) level = 'AA';
                        else if (ratio >= 3) level = 'A';
                        else level = 'FAIL';
                        
                        $indicator.attr('data-wcag-level', level);
                    }
                });
            });
            
            observer.observe(this, {
                childList: true,
                characterData: true,
                subtree: true
            });
        });
    }
    
    // Add copy/paste functionality
    function addColorCopyPaste() {
        $('.palmerita-color-group').each(function() {
            const $group = $(this);
            const $copyBtn = $('<button type="button" class="button color-copy" title="Copiar colores">📋</button>');
            const $pasteBtn = $('<button type="button" class="button color-paste" title="Pegar colores">📄</button>');
            
            $copyBtn.on('click', function() {
                const bgColor = $group.find('input[type="color"]').val();
                const textColor = $group.closest('tbody').find('.btn-text-color').val();
                
                const colorData = JSON.stringify({ bg: bgColor, text: textColor });
                navigator.clipboard.writeText(colorData).then(() => {
                    showNotification('Colores copiados al portapapeles', 'success');
                });
            });
            
            $pasteBtn.on('click', function() {
                navigator.clipboard.readText().then(text => {
                    try {
                        const colorData = JSON.parse(text);
                        if (colorData.bg && colorData.text) {
                            const $bgInput = $group.find('input[type="color"]');
                            const $textInput = $group.closest('tbody').find('.btn-text-color');
                            
                            $bgInput.val(colorData.bg).trigger('change');
                            $textInput.val(colorData.text).trigger('change');
                            
                            showNotification('Colores aplicados desde el portapapeles', 'success');
                        }
                    } catch (e) {
                        showNotification('Formato de colores inválido', 'error');
                    }
                });
            });
            
            $group.append($copyBtn, $pasteBtn);
        });
    }
    
    // Add keyboard shortcuts
    function addKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'r':
                        e.preventDefault();
                        $('.color-reset:visible').first().click();
                        break;
                    case 'c':
                        if (e.shiftKey) {
                            e.preventDefault();
                            $('.color-copy:visible').first().click();
                        }
                        break;
                    case 'v':
                        if (e.shiftKey) {
                            e.preventDefault();
                            $('.color-paste:visible').first().click();
                        }
                        break;
                }
            }
        });
    }
    
    // Utility functions
    function getButtTypeFromId(id) {
        if (id.includes('cv_')) return 'cv';
        if (id.includes('promo_')) return 'promo';
        if (id.includes('file_')) return 'file';
        return null;
    }
    
    function hexToHsl(hex) {
        const r = parseInt(hex.substr(1, 2), 16) / 255;
        const g = parseInt(hex.substr(3, 2), 16) / 255;
        const b = parseInt(hex.substr(5, 2), 16) / 255;
        
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        let h, s, l = (max + min) / 2;
        
        if (max === min) {
            h = s = 0;
        } else {
            const d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                case g: h = (b - r) / d + 2; break;
                case b: h = (r - g) / d + 4; break;
            }
            h /= 6;
        }
        
        return { h: h * 360, s: s * 100, l: l * 100 };
    }
    
    function hslToHex(h, s, l) {
        h /= 360;
        s /= 100;
        l /= 100;
        
        const c = (1 - Math.abs(2 * l - 1)) * s;
        const x = c * (1 - Math.abs((h * 6) % 2 - 1));
        const m = l - c / 2;
        let r = 0, g = 0, b = 0;
        
        if (0 <= h && h < 1/6) {
            r = c; g = x; b = 0;
        } else if (1/6 <= h && h < 2/6) {
            r = x; g = c; b = 0;
        } else if (2/6 <= h && h < 3/6) {
            r = 0; g = c; b = x;
        } else if (3/6 <= h && h < 4/6) {
            r = 0; g = x; b = c;
        } else if (4/6 <= h && h < 5/6) {
            r = x; g = 0; b = c;
        } else if (5/6 <= h && h < 1) {
            r = c; g = 0; b = x;
        }
        
        r = Math.round((r + m) * 255);
        g = Math.round((g + m) * 255);
        b = Math.round((b + m) * 255);
        
        return '#' + [r, g, b].map(x => x.toString(16).padStart(2, '0')).join('');
    }
    
    function showHarmonySuggestions($input, suggestions) {
        // Remove existing suggestions
        $input.siblings('.harmony-suggestions').remove();
        
        const $suggestions = $('<div class="harmony-suggestions"></div>');
        suggestions.forEach(suggestion => {
            const $item = $(`
                <div class="harmony-item" 
                     data-bg="${suggestion.bg}" 
                     data-text="${suggestion.text}"
                     title="${suggestion.name}">
                    <div class="harmony-preview" style="background-color: ${suggestion.bg}; color: ${suggestion.text};">
                        ${suggestion.name}
                    </div>
                </div>
            `);
            
            $item.on('click', function() {
                const textColor = $(this).data('text');
                const textInputId = $input.attr('id').replace('_btn_color', '_text_color');
                $(`#${textInputId}`).val(textColor).trigger('change');
            });
            
            $suggestions.append($item);
        });
        
        $input.after($suggestions);
        
        // Auto-hide after 5 seconds
        setTimeout(() => $suggestions.fadeOut(), 5000);
    }
    
    function showNotification(message, type) {
        const $notification = $(`
            <div class="palmerita-notification ${type}">
                ${message}
            </div>
        `);
        
        $('body').append($notification);
        
        setTimeout(() => {
            $notification.addClass('show');
        }, 100);
        
        setTimeout(() => {
            $notification.removeClass('show');
            setTimeout(() => $notification.remove(), 300);
        }, 3000);
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.palmerita-color-group').length > 0) {
            initColorEnhancements();
        }
    });
    
})(jQuery); 