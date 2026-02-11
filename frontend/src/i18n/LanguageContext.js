import React, { createContext, useContext, useState, useEffect } from 'react';
import { translations, getTranslation, supportedLanguages } from './translations';

const LanguageContext = createContext();

export const LanguageProvider = ({ children }) => {
  const [language, setLanguage] = useState(() => {
    // Get from localStorage or browser preference
    const stored = localStorage.getItem('vitalia_language');
    if (stored && translations[stored]) return stored;
    
    const browserLang = navigator.language.split('-')[0];
    if (translations[browserLang]) return browserLang;
    
    return 'tr'; // Default to Turkish
  });

  useEffect(() => {
    localStorage.setItem('vitalia_language', language);
    // Set document direction for RTL languages
    document.documentElement.dir = translations[language]?.dir || 'ltr';
    document.documentElement.lang = language;
  }, [language]);

  const t = (path) => getTranslation(language, path);

  const value = {
    language,
    setLanguage,
    t,
    supportedLanguages,
    isRTL: translations[language]?.dir === 'rtl'
  };

  return (
    <LanguageContext.Provider value={value}>
      {children}
    </LanguageContext.Provider>
  );
};

export const useLanguage = () => {
  const context = useContext(LanguageContext);
  if (!context) {
    throw new Error('useLanguage must be used within a LanguageProvider');
  }
  return context;
};

export { supportedLanguages };
