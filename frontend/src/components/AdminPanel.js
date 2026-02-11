import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { 
  LayoutDashboard, Users, Settings, HelpCircle, BarChart3, 
  ArrowLeft, Ban, Check, Search, RefreshCw, MessageSquare,
  Droplets, Footprints, Dumbbell
} from 'lucide-react';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../components/ui/tabs';
import { useLanguage } from '../i18n';

const API_URL = process.env.REACT_APP_BACKEND_URL;

// Stat Card Component
const StatCard = ({ title, value, icon: Icon, color, subtitle }) => (
  <Card className="card-hover">
    <CardContent className="p-6">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-muted-foreground">{title}</p>
          <p className="text-3xl font-bold font-heading mt-1">{value}</p>
          {subtitle && <p className="text-xs text-muted-foreground mt-1">{subtitle}</p>}
        </div>
        <div className={`w-12 h-12 rounded-full flex items-center justify-center ${color}`}>
          <Icon className="w-6 h-6" />
        </div>
      </div>
    </CardContent>
  </Card>
);

// Dashboard Tab
const DashboardTab = ({ stats }) => {
  const { t } = useLanguage();
  
  return (
    <div className="space-y-6">
      {/* User Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          title="Total Users"
          value={stats?.users?.total || 0}
          icon={Users}
          color="bg-primary/10 text-primary"
          subtitle={`+${stats?.users?.new_7d || 0} this week`}
        />
        <StatCard
          title="Daily Active"
          value={stats?.users?.dau || 0}
          icon={BarChart3}
          color="bg-success/10 text-success"
        />
        <StatCard
          title="Messages Today"
          value={stats?.messages?.today || 0}
          icon={MessageSquare}
          color="bg-secondary/10 text-secondary"
        />
        <StatCard
          title="AI Calls Today"
          value={stats?.openai?.calls_count || 0}
          icon={HelpCircle}
          color="bg-warning/10 text-warning"
          subtitle={`${(stats?.openai?.input_tokens || 0) + (stats?.openai?.output_tokens || 0)} tokens`}
        />
      </div>

      {/* Cache Stats */}
      <Card>
        <CardHeader>
          <CardTitle>Cache Performance</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 gap-4">
            <div className="p-4 bg-muted rounded-lg">
              <p className="text-sm text-muted-foreground">Cached Responses</p>
              <p className="text-2xl font-bold">{stats?.cache?.total_entries || 0}</p>
            </div>
            <div className="p-4 bg-muted rounded-lg">
              <p className="text-sm text-muted-foreground">Total Hits</p>
              <p className="text-2xl font-bold">{stats?.cache?.total_hits || 0}</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

// Users Tab
const UsersTab = ({ users, onBanUser, onRefresh }) => {
  const [searchTerm, setSearchTerm] = useState('');
  
  const filteredUsers = users?.filter(user => 
    user.email?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.user_id?.toLowerCase().includes(searchTerm.toLowerCase())
  ) || [];

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <Input
            placeholder="Search users..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-10"
          />
        </div>
        <Button variant="outline" onClick={onRefresh}>
          <RefreshCw className="w-4 h-4 mr-2" />
          Refresh
        </Button>
      </div>

      <div className="border rounded-lg overflow-hidden">
        <table className="w-full">
          <thead className="bg-muted">
            <tr>
              <th className="px-4 py-3 text-left text-sm font-medium">User</th>
              <th className="px-4 py-3 text-left text-sm font-medium">Provider</th>
              <th className="px-4 py-3 text-left text-sm font-medium">Status</th>
              <th className="px-4 py-3 text-left text-sm font-medium">Last Active</th>
              <th className="px-4 py-3 text-left text-sm font-medium">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {filteredUsers.map((user) => (
              <tr key={user.user_id} className="hover:bg-muted/50">
                <td className="px-4 py-3">
                  <div className="flex items-center gap-3">
                    {user.picture ? (
                      <img src={user.picture} alt="" className="w-8 h-8 rounded-full" />
                    ) : (
                      <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                        <Users className="w-4 h-4 text-primary" />
                      </div>
                    )}
                    <div>
                      <p className="font-medium">{user.name || 'Anonymous'}</p>
                      <p className="text-xs text-muted-foreground">{user.email || user.user_id}</p>
                    </div>
                  </div>
                </td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-1 rounded-full text-xs ${
                    user.auth_provider === 'google' 
                      ? 'bg-blue-100 text-blue-700'
                      : 'bg-gray-100 text-gray-700'
                  }`}>
                    {user.auth_provider}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-1 rounded-full text-xs ${
                    user.status === 'active' 
                      ? 'bg-green-100 text-green-700'
                      : 'bg-red-100 text-red-700'
                  }`}>
                    {user.status}
                  </span>
                </td>
                <td className="px-4 py-3 text-sm text-muted-foreground">
                  {user.last_active_at ? new Date(user.last_active_at).toLocaleDateString() : '-'}
                </td>
                <td className="px-4 py-3">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onBanUser(user.user_id, user.status === 'active')}
                    className={user.status === 'active' ? 'text-destructive' : 'text-success'}
                  >
                    {user.status === 'active' ? <Ban className="w-4 h-4" /> : <Check className="w-4 h-4" />}
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

// Settings Tab
const SettingsTab = ({ settings, onSave }) => {
  const [localSettings, setLocalSettings] = useState(settings || {});
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (settings) {
      setLocalSettings(settings);
    }
  }, [settings]);

  const handleSave = async () => {
    setIsSaving(true);
    await onSave(localSettings);
    setIsSaving(false);
  };

  return (
    <div className="space-y-6">
      {/* OpenAI Settings */}
      <Card>
        <CardHeader>
          <CardTitle>OpenAI Configuration</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium">Model</label>
              <Input
                value={localSettings.openai_model || 'gpt-4o-mini'}
                onChange={(e) => setLocalSettings({ ...localSettings, openai_model: e.target.value })}
              />
            </div>
            <div>
              <label className="text-sm font-medium">Max Tokens</label>
              <Input
                type="number"
                value={localSettings.openai_max_tokens || 500}
                onChange={(e) => setLocalSettings({ ...localSettings, openai_max_tokens: parseInt(e.target.value) })}
              />
            </div>
          </div>
          <div>
            <label className="text-sm font-medium">Temperature</label>
            <Input
              type="number"
              step="0.1"
              min="0"
              max="2"
              value={localSettings.openai_temperature || 0.7}
              onChange={(e) => setLocalSettings({ ...localSettings, openai_temperature: parseFloat(e.target.value) })}
            />
          </div>
        </CardContent>
      </Card>

      {/* Limits Settings */}
      <Card>
        <CardHeader>
          <CardTitle>Rate Limits</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium">Guest Daily Limit</label>
              <Input
                type="number"
                value={localSettings.daily_message_limit_guest || 10}
                onChange={(e) => setLocalSettings({ ...localSettings, daily_message_limit_guest: parseInt(e.target.value) })}
              />
            </div>
            <div>
              <label className="text-sm font-medium">User Daily Limit</label>
              <Input
                type="number"
                value={localSettings.daily_message_limit_user || 50}
                onChange={(e) => setLocalSettings({ ...localSettings, daily_message_limit_user: parseInt(e.target.value) })}
              />
            </div>
          </div>
          <div>
            <label className="text-sm font-medium">Rate Limit (per minute)</label>
            <Input
              type="number"
              value={localSettings.rate_limit_per_minute || 10}
              onChange={(e) => setLocalSettings({ ...localSettings, rate_limit_per_minute: parseInt(e.target.value) })}
            />
          </div>
        </CardContent>
      </Card>

      {/* Health Settings */}
      <Card>
        <CardHeader>
          <CardTitle>Health Calculations</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium">Water (ml/kg)</label>
              <Input
                type="number"
                value={localSettings.water_ml_per_kg || 30}
                onChange={(e) => setLocalSettings({ ...localSettings, water_ml_per_kg: parseInt(e.target.value) })}
              />
            </div>
            <div>
              <label className="text-sm font-medium">Glass Size (ml)</label>
              <Input
                type="number"
                value={localSettings.glass_ml || 250}
                onChange={(e) => setLocalSettings({ ...localSettings, glass_ml: parseInt(e.target.value) })}
              />
            </div>
          </div>
          <div>
            <label className="text-sm font-medium">Exercise Water Bonus (ml)</label>
            <Input
              type="number"
              value={localSettings.water_exercise_bonus_ml || 500}
              onChange={(e) => setLocalSettings({ ...localSettings, water_exercise_bonus_ml: parseInt(e.target.value) })}
            />
          </div>
        </CardContent>
      </Card>

      <Button onClick={handleSave} disabled={isSaving} className="w-full">
        {isSaving ? 'Saving...' : 'Save Settings'}
      </Button>
    </div>
  );
};

// Main Admin Panel Component
export const AdminPanel = ({ onBack }) => {
  const { t } = useLanguage();
  const [activeTab, setActiveTab] = useState('dashboard');
  const [stats, setStats] = useState(null);
  const [users, setUsers] = useState([]);
  const [settings, setSettings] = useState({});
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setIsLoading(true);
    try {
      // Load dashboard stats
      const dashboardRes = await fetch(`${API_URL}/api/admin/dashboard`, {
        credentials: 'include'
      });
      if (dashboardRes.ok) {
        setStats(await dashboardRes.json());
      }

      // Load users
      const usersRes = await fetch(`${API_URL}/api/admin/users`, {
        credentials: 'include'
      });
      if (usersRes.ok) {
        const data = await usersRes.json();
        setUsers(data.users || []);
      }

      // Load settings
      const settingsRes = await fetch(`${API_URL}/api/admin/settings`, {
        credentials: 'include'
      });
      if (settingsRes.ok) {
        const data = await settingsRes.json();
        setSettings(data.settings || {});
      }
    } catch (error) {
      console.error('Failed to load admin data:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleBanUser = async (userId, ban) => {
    try {
      await fetch(`${API_URL}/api/admin/users/${userId}/ban`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ ban })
      });
      loadData();
    } catch (error) {
      console.error('Failed to ban user:', error);
    }
  };

  const handleSaveSettings = async (newSettings) => {
    try {
      await fetch(`${API_URL}/api/admin/settings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(newSettings)
      });
      setSettings(newSettings);
    } catch (error) {
      console.error('Failed to save settings:', error);
    }
  };

  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      className="min-h-screen bg-background"
      data-testid="admin-panel"
    >
      {/* Header */}
      <div className="border-b border-border bg-card">
        <div className="max-w-7xl mx-auto px-4 py-4 flex items-center gap-4">
          <Button variant="ghost" size="icon" onClick={onBack}>
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center">
              <span className="text-white font-bold">V</span>
            </div>
            <div>
              <h1 className="font-bold font-heading text-xl">Vitalia Admin</h1>
              <p className="text-xs text-muted-foreground">{t('admin.dashboard')}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-7xl mx-auto px-4 py-6">
        {isLoading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin w-10 h-10 border-4 border-primary border-t-transparent rounded-full"></div>
          </div>
        ) : (
          <Tabs value={activeTab} onValueChange={setActiveTab}>
            <TabsList className="mb-6">
              <TabsTrigger value="dashboard" className="flex items-center gap-2">
                <LayoutDashboard className="w-4 h-4" />
                Dashboard
              </TabsTrigger>
              <TabsTrigger value="users" className="flex items-center gap-2">
                <Users className="w-4 h-4" />
                Users
              </TabsTrigger>
              <TabsTrigger value="settings" className="flex items-center gap-2">
                <Settings className="w-4 h-4" />
                Settings
              </TabsTrigger>
            </TabsList>

            <TabsContent value="dashboard">
              <DashboardTab stats={stats} />
            </TabsContent>

            <TabsContent value="users">
              <UsersTab 
                users={users} 
                onBanUser={handleBanUser}
                onRefresh={loadData}
              />
            </TabsContent>

            <TabsContent value="settings">
              <SettingsTab 
                settings={settings}
                onSave={handleSaveSettings}
              />
            </TabsContent>
          </Tabs>
        )}
      </div>
    </motion.div>
  );
};

export default AdminPanel;
