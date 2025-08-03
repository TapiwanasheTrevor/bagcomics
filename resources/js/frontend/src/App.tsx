import React, { useState } from 'react';
import Header from './components/Header';
import Homepage from './pages/Homepage';
import Catalogue from './pages/Catalogue';
import ComicReader from './pages/ComicReader';
import ComicDetail from './pages/ComicDetail';
import UserLibrary from './pages/UserLibrary';
import Login from './pages/Login';
import Register from './pages/Register';

export type Page = 'home' | 'catalogue' | 'reader' | 'detail' | 'library' | 'login' | 'register';

function App() {
  const [currentPage, setCurrentPage] = useState<Page>('home');
  const [selectedComicId, setSelectedComicId] = useState<string | null>(null);
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [userData, setUserData] = useState<any>(null);

  const navigateTo = (page: Page, comicId?: string) => {
    if (comicId) setSelectedComicId(comicId);
    setCurrentPage(page);
  };

  const handleLogin = (user: any) => {
    setUserData(user);
    setIsLoggedIn(true);
  };

  const handleLogout = () => {
    setUserData(null);
    setIsLoggedIn(false);
    setCurrentPage('home');
  };

  const renderCurrentPage = () => {
    switch (currentPage) {
      case 'home':
        return <Homepage onNavigate={navigateTo} />;
      case 'catalogue':
        return <Catalogue onNavigate={navigateTo} />;
      case 'reader':
        return <ComicReader comicId={selectedComicId} onNavigate={navigateTo} />;
      case 'detail':
        return <ComicDetail comicId={selectedComicId} onNavigate={navigateTo} />;
      case 'library':
        return <UserLibrary onNavigate={navigateTo} />;
      case 'login':
        return <Login onNavigate={navigateTo} onLogin={handleLogin} />;
      case 'register':
        return <Register onNavigate={navigateTo} onLogin={handleLogin} />;
      default:
        return <Homepage onNavigate={navigateTo} />;
    }
  };

  return (
    <div className="min-h-screen bg-gray-900 text-white">
      {currentPage !== 'login' && currentPage !== 'register' && (
        <Header 
          currentPage={currentPage} 
          onNavigate={navigateTo}
          isLoggedIn={isLoggedIn}
          userData={userData}
          onLogin={() => navigateTo('login')}
          onLogout={handleLogout}
        />
      )}
      {renderCurrentPage()}
    </div>
  );
}

export default App;