import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { ArrowLeft, User, Target, Ruler, Scale, Calendar, LogOut } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../components/ui/select';
import { useLanguage } from '../i18n';
import { LanguageSelector } from './LanguageSelector';

const API_URL = process.env.REACT_APP_BACKEND_URL;

export const ProfilePage = ({ user, onBack, onLogout }) => {
  const { t } = useLanguage();
  const [profile, setProfile] = useState({
    age: '',
    height_cm: '',
    weight_kg: '',
    goal: ''
  });
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [saveMessage, setSaveMessage] = useState('');

  useEffect(() => {
    loadProfile();
  }, []);

  const loadProfile = async () => {
    setIsLoading(true);
    try {
      const response = await fetch(`${API_URL}/api/profile`, {
        credentials: 'include'
      });
      if (response.ok) {
        const data = await response.json();
        if (data.profile) {
          setProfile({
            age: data.profile.age || '',
            height_cm: data.profile.height_cm || '',
            weight_kg: data.profile.weight_kg || '',
            goal: data.profile.goal || ''
          });
        }
      }
    } catch (error) {
      console.error('Failed to load profile:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const saveProfile = async () => {
    setIsSaving(true);
    setSaveMessage('');
    try {
      const response = await fetch(`${API_URL}/api/profile`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          age: profile.age ? parseInt(profile.age) : null,
          height_cm: profile.height_cm ? parseInt(profile.height_cm) : null,
          weight_kg: profile.weight_kg ? parseFloat(profile.weight_kg) : null,
          goal: profile.goal || null
        })
      });

      if (response.ok) {
        setSaveMessage(t('common.success'));
        setTimeout(() => setSaveMessage(''), 3000);
      }
    } catch (error) {
      console.error('Failed to save profile:', error);
    } finally {
      setIsSaving(false);
    }
  };

  const handleLogout = async () => {
    try {
      await fetch(`${API_URL}/api/auth/logout`, {
        method: 'POST',
        credentials: 'include'
      });
      onLogout();
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  return (
    <motion.div
      initial={{ opacity: 0, x: 20 }}
      animate={{ opacity: 1, x: 0 }}
      exit={{ opacity: 0, x: -20 }}
      className="h-full flex flex-col bg-background"
      data-testid="profile-page"
    >
      {/* Header */}
      <div className="flex items-center gap-3 p-4 border-b border-border">
        <Button variant="ghost" size="icon" onClick={onBack} data-testid="back-button">
          <ArrowLeft className="w-5 h-5" />
        </Button>
        <h1 className="text-xl font-bold font-heading">{t('profile.title')}</h1>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto p-4 space-y-6">
        {isLoading ? (
          <div className="flex items-center justify-center h-32">
            <div className="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full"></div>
          </div>
        ) : (
          <>
            {/* User Info */}
            {user && (
              <div className="flex items-center gap-4 p-4 bg-card rounded-xl border border-border">
                {user.picture ? (
                  <img 
                    src={user.picture} 
                    alt={user.name} 
                    className="w-14 h-14 rounded-full"
                  />
                ) : (
                  <div className="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center">
                    <User className="w-7 h-7 text-primary" />
                  </div>
                )}
                <div>
                  <p className="font-semibold">{user.name || 'User'}</p>
                  <p className="text-sm text-muted-foreground">{user.email}</p>
                </div>
              </div>
            )}

            {/* Profile Form */}
            <div className="space-y-4">
              {/* Age */}
              <div className="space-y-2">
                <Label htmlFor="age" className="flex items-center gap-2">
                  <Calendar className="w-4 h-4" />
                  {t('profile.age')}
                </Label>
                <Input
                  id="age"
                  type="number"
                  value={profile.age}
                  onChange={(e) => setProfile({ ...profile, age: e.target.value })}
                  placeholder="25"
                  className="bg-card"
                  data-testid="age-input"
                />
              </div>

              {/* Height */}
              <div className="space-y-2">
                <Label htmlFor="height" className="flex items-center gap-2">
                  <Ruler className="w-4 h-4" />
                  {t('profile.height')}
                </Label>
                <Input
                  id="height"
                  type="number"
                  value={profile.height_cm}
                  onChange={(e) => setProfile({ ...profile, height_cm: e.target.value })}
                  placeholder="175"
                  className="bg-card"
                  data-testid="height-input"
                />
              </div>

              {/* Weight */}
              <div className="space-y-2">
                <Label htmlFor="weight" className="flex items-center gap-2">
                  <Scale className="w-4 h-4" />
                  {t('profile.weight')}
                </Label>
                <Input
                  id="weight"
                  type="number"
                  step="0.1"
                  value={profile.weight_kg}
                  onChange={(e) => setProfile({ ...profile, weight_kg: e.target.value })}
                  placeholder="70.5"
                  className="bg-card"
                  data-testid="weight-input"
                />
              </div>

              {/* Goal */}
              <div className="space-y-2">
                <Label htmlFor="goal" className="flex items-center gap-2">
                  <Target className="w-4 h-4" />
                  {t('profile.goal')}
                </Label>
                <Select
                  value={profile.goal}
                  onValueChange={(value) => setProfile({ ...profile, goal: value })}
                >
                  <SelectTrigger className="bg-card" data-testid="goal-select">
                    <SelectValue placeholder={t('profile.goal')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="lose">{t('profile.goals.lose')}</SelectItem>
                    <SelectItem value="maintain">{t('profile.goals.maintain')}</SelectItem>
                    <SelectItem value="gain">{t('profile.goals.gain')}</SelectItem>
                    <SelectItem value="healthy">{t('profile.goals.healthy')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {/* Language Selector */}
              <div className="pt-4 border-t border-border">
                <LanguageSelector variant="full" />
              </div>

              {/* Save Button */}
              <Button 
                onClick={saveProfile} 
                disabled={isSaving}
                className="w-full bg-primary hover:bg-primary/90"
                data-testid="save-profile-btn"
              >
                {isSaving ? t('common.loading') : t('common.save')}
              </Button>

              {saveMessage && (
                <p className="text-center text-sm text-success">{saveMessage}</p>
              )}
            </div>

            {/* Logout */}
            {user && (
              <div className="pt-4 border-t border-border">
                <Button 
                  variant="outline" 
                  onClick={handleLogout}
                  className="w-full text-destructive border-destructive/50 hover:bg-destructive/10"
                  data-testid="logout-btn"
                >
                  <LogOut className="w-4 h-4 mr-2" />
                  {t('auth.logout')}
                </Button>
              </div>
            )}
          </>
        )}
      </div>
    </motion.div>
  );
};

export default ProfilePage;
