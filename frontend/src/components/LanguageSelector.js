import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Globe, Check, ChevronDown } from 'lucide-react';
import { Button } from '../components/ui/button';
import { useLanguage, supportedLanguages } from '../i18n';

export const LanguageSelector = ({ variant = 'dropdown' }) => {
  const { language, setLanguage, t } = useLanguage();
  const [isOpen, setIsOpen] = useState(false);

  const currentLang = supportedLanguages.find(l => l.code === language);

  if (variant === 'full') {
    // Full list for settings page
    return (
      <div className="space-y-2" data-testid="language-selector-full">
        <label className="text-sm font-medium text-muted-foreground">{t('profile.language')}</label>
        <div className="grid grid-cols-2 gap-2">
          {supportedLanguages.map((lang) => (
            <button
              key={lang.code}
              onClick={() => setLanguage(lang.code)}
              className={`flex items-center gap-2 p-3 rounded-lg border transition-all ${
                language === lang.code
                  ? 'border-primary bg-primary/5 text-primary'
                  : 'border-border hover:border-primary/50 hover:bg-muted'
              }`}
              data-testid={`lang-option-${lang.code}`}
            >
              <span className="text-lg">{lang.flag}</span>
              <span className="text-sm font-medium">{lang.name}</span>
              {language === lang.code && <Check className="w-4 h-4 ml-auto" />}
            </button>
          ))}
        </div>
      </div>
    );
  }

  // Dropdown variant for header
  return (
    <div className="relative" data-testid="language-selector">
      <Button
        variant="ghost"
        size="sm"
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-3"
        data-testid="language-dropdown-trigger"
      >
        <span className="text-base">{currentLang?.flag}</span>
        <span className="hidden sm:inline text-sm">{currentLang?.name}</span>
        <ChevronDown className={`w-4 h-4 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </Button>

      <AnimatePresence>
        {isOpen && (
          <>
            {/* Backdrop */}
            <div 
              className="fixed inset-0 z-40" 
              onClick={() => setIsOpen(false)}
            />
            
            {/* Dropdown */}
            <motion.div
              initial={{ opacity: 0, y: -10, scale: 0.95 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: -10, scale: 0.95 }}
              transition={{ duration: 0.15 }}
              className="absolute right-0 top-full mt-2 z-50 w-48 bg-card border border-border rounded-xl shadow-lg overflow-hidden"
              data-testid="language-dropdown-menu"
            >
              <div className="py-1 max-h-64 overflow-y-auto">
                {supportedLanguages.map((lang) => (
                  <button
                    key={lang.code}
                    onClick={() => {
                      setLanguage(lang.code);
                      setIsOpen(false);
                    }}
                    className={`w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors ${
                      language === lang.code
                        ? 'bg-primary/10 text-primary'
                        : 'hover:bg-muted'
                    }`}
                    data-testid={`lang-option-${lang.code}`}
                  >
                    <span className="text-lg">{lang.flag}</span>
                    <span className="text-sm font-medium flex-1">{lang.name}</span>
                    {language === lang.code && <Check className="w-4 h-4" />}
                  </button>
                ))}
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </div>
  );
};

export default LanguageSelector;
