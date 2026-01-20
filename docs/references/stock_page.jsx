import React, { useState } from 'react';

// Mock data
const stockData = [
    { id: 1, name: 'Молоко 3.2%', category: 'dairy', location: 'fridge', qty: 0.5, unit: 'л', expiry: '2025-01-18', note: '500г', status: 'expired' },
    { id: 2, name: 'Сыр Голландский', category: 'dairy', location: 'fridge', qty: 1, unit: 'шт', expiry: '2025-01-18', note: 'Скоро истекает!', status: 'expired' },
    { id: 3, name: 'Рис Басмати', category: 'grains', location: 'pantry', qty: 1, unit: 'кг', expiry: '2025-01-19', note: null, status: 'today' },
    { id: 4, name: 'Йогурт натуральный', category: 'dairy', location: 'fridge', qty: 2, unit: 'шт', expiry: '2025-01-20', note: null, status: 'soon' },
    { id: 5, name: 'Масло сливочное', category: 'dairy', location: 'fridge', qty: 1, unit: 'шт', expiry: '2025-02-05', note: null, status: 'ok' },
    { id: 6, name: 'Куриная грудка', category: 'meat', location: 'fridge', qty: 1, unit: 'кг', expiry: '2025-02-15', note: 'Открыт 15.01', status: 'ok' },
    { id: 7, name: 'Замороженные ягоды', category: 'frozen', location: 'fridge', qty: 1, unit: 'кг', expiry: '2025-03-01', note: null, status: 'ok' },
    { id: 8, name: 'Макароны Barilla', category: 'grains', location: 'pantry', qty: 2, unit: 'шт', expiry: '2025-07-01', note: null, status: 'ok' },
    { id: 9, name: 'Консервы тунец', category: 'canned', location: 'pantry', qty: 3, unit: 'шт', expiry: '2026-01-01', note: null, status: 'ok' },
];

const categoryIcons = {
    dairy: '🥛',
    grains: '🌾',
    meat: '🍗',
    frozen: '🧊',
    canned: '🥫',
    vegetables: '🥬',
};

const locationNames = {
    fridge: 'Холодильник',
    pantry: 'Кладовая',
    all: 'Всё',
};

function getDaysText(expiry) {
    const today = new Date('2025-01-19'); // Mock "today"
    const exp = new Date(expiry);
    const diff = Math.ceil((exp - today) / (1000 * 60 * 60 * 24));

    if (diff < 0) return `${Math.abs(diff)} дн. назад`;
    if (diff === 0) return 'сегодня';
    if (diff === 1) return 'завтра';
    return `через ${diff} дн.`;
}

function formatDate(expiry) {
    const date = new Date(expiry);
    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}

// Attention Card Component
function AttentionCard({ item, onUse, onThrow }) {
    const isExpired = item.status === 'expired';
    const isToday = item.status === 'today';

    return (
        <div className={`attention-card ${item.status}`}>
            <div className="card-header">
                <span className="category-icon">{categoryIcons[item.category] || '📦'}</span>
                <span className="item-name">{item.name}</span>
                <span className="item-location">{locationNames[item.location]}</span>
            </div>
            <div className="card-body">
                <span className="item-qty">{item.qty} {item.unit}</span>
                <div className="item-expiry-block">
          <span className="item-expiry">
            {isExpired ? '⚠️ ' : isToday ? '⏰ ' : ''}
              {getDaysText(item.expiry)}
          </span>
                    <span className="item-expiry-date">({formatDate(item.expiry)})</span>
                </div>
            </div>
            <div className="card-actions">
                <button className="btn-use" onClick={() => onUse(item.id)}>
                    ✓ Готово
                </button>
                <button className="btn-throw" onClick={() => onThrow(item.id)}>
                    🗑 Выбросить
                </button>
            </div>
        </div>
    );
}

// Table Row Component
function StockRow({ item, onUse }) {
    return (
        <tr className={`stock-row ${item.status}`}>
            <td className="col-name">
                <div className="name-content">
                    <span className="category-icon-small">{categoryIcons[item.category] || '📦'}</span>
                    <span className="product-name">{item.name}</span>
                    {item.note && <span className="note-inline">{item.note}</span>}
                </div>
            </td>
            <td className="col-qty">
                <span className="qty-value">{item.qty} {item.unit}</span>
            </td>
            <td className="col-expiry">
                <div className="expiry-content">
                    <div className="expiry-block">
            <span className={`expiry-relative ${item.status}`}>
              {item.status === 'expired' ? '⚠️ ' : item.status === 'today' ? '⏰ ' : ''}
                {getDaysText(item.expiry)}
            </span>
                        <span className="expiry-absolute">({formatDate(item.expiry)})</span>
                    </div>
                </div>
            </td>
            <td className="col-actions">
                <button className="action-icon" onClick={() => onUse(item.id)} title="Готово">
                    ✓
                </button>
            </td>
        </tr>
    );
}

// Main Component
export default function HestiaStockRedesign() {
    const [activeLocation, setActiveLocation] = useState('all');
    const [showAllAttention, setShowAllAttention] = useState(false);

    // Items needing attention (expired, today, soon)
    const attentionItems = stockData.filter(i =>
        i.status === 'expired' || i.status === 'today' || i.status === 'soon'
    );

    const expiredCount = stockData.filter(i => i.status === 'expired').length;
    const soonCount = stockData.filter(i => i.status === 'today' || i.status === 'soon').length;

    // Items for table (filtered by location)
    const tableItems = stockData.filter(i =>
        activeLocation === 'all' || i.location === activeLocation
    );

    const displayedAttention = showAllAttention ? attentionItems : attentionItems.slice(0, 3);

    const handleUse = (id) => console.log('Used:', id);
    const handleThrow = (id) => console.log('Thrown:', id);

    return (
        <div className="hestia-app">
            {/* Sidebar */}
            <aside className="sidebar">
                <div className="logo">
                    <span className="logo-icon">🏠</span>
                    <span className="logo-text">Hestia</span>
                    <span className="logo-sub">Домашний учёт</span>
                </div>
                <nav className="nav">
                    <a href="#" className="nav-item">Главная</a>
                    <a href="#" className="nav-item active">Запасы</a>
                    <a href="#" className="nav-item">Товары</a>
                    <a href="#" className="nav-item">Покупки</a>
                    <a href="#" className="nav-item">Рецепты</a>
                    <a href="#" className="nav-item">Задачи</a>
                </nav>
                <div className="user-block">
                    <span>Pavel</span>
                    <button className="logout-btn">Выйти</button>
                </div>
            </aside>

            {/* Main Content */}
            <main className="main-content">
                {/* Header */}
                <header className="page-header">
                    <div className="header-left">
                        <h1>Запасы</h1>
                        <p className="subtitle">Добрый вечер! {expiredCount > 0 ? `Есть ${expiredCount} просроченных` : soonCount > 0 ? `${soonCount} скоро истекают` : 'Всё в порядке'}</p>
                    </div>
                    <div className="header-actions">
                        <button className="btn-scan">📷 Сканировать</button>
                        <button className="btn-add">+ Добавить</button>
                    </div>
                </header>

                {/* Attention Section */}
                {attentionItems.length > 0 ? (
                    <section className="attention-section">
                        <div className="section-header">
                            <h2>🕐 Нужно разобраться сегодня</h2>
                            <div className="section-actions">
                                <button className="clear-all-btn">Очистить всё</button>
                                {attentionItems.length > 3 && (
                                    <button
                                        className="show-all-btn"
                                        onClick={() => setShowAllAttention(!showAllAttention)}
                                    >
                                        {showAllAttention ? 'Свернуть' : `Показать всё (${attentionItems.length})`}
                                    </button>
                                )}
                            </div>
                        </div>
                        <div className="attention-cards">
                            {displayedAttention.map(item => (
                                <AttentionCard
                                    key={item.id}
                                    item={item}
                                    onUse={handleUse}
                                    onThrow={handleThrow}
                                />
                            ))}
                        </div>
                    </section>
                ) : (
                    <section className="positive-section">
                        <div className="positive-block">
                            <span className="positive-icon">✅</span>
                            <div className="positive-text">
                                <span className="positive-title">Всё под контролем</span>
                                <span className="positive-subtitle">Следующее истекает через 17 дней</span>
                            </div>
                        </div>
                    </section>
                )}

                {/* Inventory Section */}
                <section className="inventory-section">
                    <div className="section-header">
                        <h2>📦 Все запасы</h2>
                        <div className="search-box">
                            <input type="text" placeholder="Поиск по названию..." />
                        </div>
                    </div>

                    {/* Location Tabs */}
                    <div className="location-tabs">
                        {Object.entries(locationNames).map(([key, label]) => (
                            <button
                                key={key}
                                className={`tab ${activeLocation === key ? 'active' : ''}`}
                                onClick={() => setActiveLocation(key)}
                            >
                                {key === 'fridge' && '❄️ '}
                                {key === 'pantry' && '🗄️ '}
                                {label}
                                <span className="tab-count">
                  {key === 'all'
                      ? stockData.length
                      : stockData.filter(i => i.location === key).length}
                </span>
                            </button>
                        ))}
                    </div>

                    {/* Stock Table */}
                    <table className="stock-table">
                        <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Количество</th>
                            <th>Годен до</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        {tableItems.map(item => (
                            <StockRow key={item.id} item={item} onUse={handleUse} />
                        ))}
                        </tbody>
                    </table>
                </section>
            </main>

            <style>{`
        /* Reset & Base */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        .hestia-app {
          display: flex;
          min-height: 100vh;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
          background: #fafaf8;
          color: #2d2a24;
        }

        /* Sidebar */
        .sidebar {
          width: 220px;
          background: #fff;
          border-right: 1px solid #e8e6e1;
          padding: 24px 16px;
          display: flex;
          flex-direction: column;
        }
        
        .logo {
          display: flex;
          flex-direction: column;
          margin-bottom: 32px;
        }
        .logo-icon { font-size: 28px; margin-bottom: 4px; }
        .logo-text { font-size: 20px; font-weight: 600; color: #e67e22; }
        .logo-sub { font-size: 12px; color: #8a8578; }
        
        .nav { flex: 1; }
        .nav-item {
          display: block;
          padding: 10px 12px;
          margin-bottom: 4px;
          border-radius: 8px;
          color: #5c5a52;
          text-decoration: none;
          font-size: 14px;
          transition: all 0.15s;
        }
        .nav-item:hover { background: #f5f4f0; color: #2d2a24; }
        .nav-item.active { 
          background: #fef3e2; 
          color: #e67e22; 
          font-weight: 500;
        }
        
        .user-block {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding-top: 16px;
          border-top: 1px solid #e8e6e1;
          font-size: 14px;
        }
        .logout-btn {
          background: none;
          border: none;
          color: #8a8578;
          cursor: pointer;
          font-size: 13px;
        }
        .logout-btn:hover { color: #c0392b; }

        /* Main Content */
        .main-content {
          flex: 1;
          padding: 32px 40px;
          max-width: 1200px;
        }

        /* Header */
        .page-header {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          margin-bottom: 32px;
        }
        .page-header h1 {
          font-size: 28px;
          font-weight: 600;
          margin-bottom: 4px;
        }
        .subtitle {
          color: #6b6860;
          font-size: 15px;
        }
        .header-actions {
          display: flex;
          gap: 12px;
        }
        .btn-scan, .btn-add {
          padding: 10px 20px;
          border-radius: 8px;
          font-size: 14px;
          font-weight: 500;
          cursor: pointer;
          transition: all 0.15s;
        }
        .btn-scan {
          background: #e67e22;
          color: white;
          border: none;
        }
        .btn-scan:hover { background: #d35400; }
        .btn-add {
          background: #2d2a24;
          color: white;
          border: none;
        }
        .btn-add:hover { background: #1a1814; }

        /* Attention Section */
        .attention-section {
          margin-bottom: 32px;
        }
        .section-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 16px;
        }
        .section-header h2 {
          font-size: 15px;
          font-weight: 600;
          color: #5c5a52;
        }
        .section-actions {
          display: flex;
          align-items: center;
          gap: 16px;
        }
        .clear-all-btn {
          background: #2d2a24;
          color: white;
          border: none;
          padding: 6px 14px;
          border-radius: 6px;
          font-size: 12px;
          cursor: pointer;
        }
        .clear-all-btn:hover { background: #1a1814; }
        .show-all-btn {
          background: none;
          border: none;
          color: #8a8578;
          cursor: pointer;
          font-size: 13px;
        }
        .show-all-btn:hover { color: #e67e22; }
        
        /* Positive State */
        .positive-section {
          margin-bottom: 32px;
        }
        .positive-block {
          display: flex;
          align-items: center;
          gap: 14px;
          background: #f0f9f4;
          border: 1px solid #c8e6c9;
          border-radius: 10px;
          padding: 16px 20px;
        }
        .positive-icon {
          font-size: 24px;
        }
        .positive-text {
          display: flex;
          flex-direction: column;
          gap: 2px;
        }
        .positive-title {
          font-weight: 600;
          color: #27ae60;
          font-size: 15px;
        }
        .positive-subtitle {
          color: #5c5a52;
          font-size: 13px;
        }

        /* Attention Cards */
        .attention-cards {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
          gap: 12px;
        }
        .attention-card {
          background: #fff;
          border-radius: 10px;
          padding: 12px 14px 14px;
          border-left: 3px solid #e74c3c;
          box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .attention-card.expired { 
          border-left-color: #e74c3c; 
        }
        .attention-card.today { 
          border-left-color: #e67e22; 
        }
        .attention-card.soon { 
          border-left-color: #f1c40f; 
        }
        
        .card-header {
          display: flex;
          align-items: center;
          gap: 6px;
          margin-bottom: 8px;
        }
        .category-icon { font-size: 16px; }
        .item-name { 
          font-weight: 500; 
          flex: 1;
          font-size: 14px;
        }
        .item-location {
          font-size: 11px;
          color: #8a8578;
          background: #f5f4f0;
          padding: 2px 6px;
          border-radius: 4px;
        }
        
        .card-body {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          margin-bottom: 14px;
          font-size: 13px;
        }
        .item-qty { color: #5c5a52; }
        .item-expiry-block {
          display: flex;
          flex-direction: column;
          align-items: flex-end;
        }
        .item-expiry { 
          font-weight: 500;
        }
        .item-expiry-date {
          font-size: 11px;
          color: #8a8578;
          margin-top: 2px;
        }
        .attention-card.expired .item-expiry { color: #c0392b; }
        .attention-card.today .item-expiry { color: #d35400; }
        .attention-card.soon .item-expiry { color: #b8860b; }
        
        .card-actions {
          display: flex;
          gap: 6px;
        }
        .btn-use, .btn-throw {
          flex: 1;
          padding: 5px 10px;
          border-radius: 5px;
          font-size: 12px;
          cursor: pointer;
          transition: all 0.15s;
        }
        .btn-use {
          background: #f0f9f4;
          color: #219a52;
          border: 1px solid #c8e6c9;
        }
        .btn-use:hover { background: #e0f2e5; }
        .btn-throw {
          background: #fef5f5;
          color: #c0392b;
          border: 1px solid #f5c6c6;
        }
        .btn-throw:hover { 
          background: #fdeaea; 
        }

        /* Inventory Section */
        .inventory-section .section-header h2 {
          color: #2d2a24;
        }
        .search-box input {
          padding: 8px 14px;
          border: 1px solid #e0ded8;
          border-radius: 6px;
          font-size: 14px;
          width: 240px;
          background: #fff;
        }
        .search-box input:focus {
          outline: none;
          border-color: #e67e22;
        }

        /* Location Tabs */
        .location-tabs {
          display: flex;
          gap: 8px;
          margin: 20px 0 16px;
          border-bottom: 1px solid #e8e6e1;
          padding-bottom: 0;
        }
        .tab {
          padding: 10px 16px;
          background: none;
          border: none;
          font-size: 14px;
          color: #6b6860;
          cursor: pointer;
          border-bottom: 2px solid transparent;
          margin-bottom: -1px;
          transition: all 0.15s;
        }
        .tab:hover { color: #2d2a24; }
        .tab.active {
          color: #e67e22;
          border-bottom-color: #e67e22;
          font-weight: 500;
        }
        .tab-count {
          display: inline-block;
          background: #f0eeea;
          padding: 1px 6px;
          border-radius: 10px;
          font-size: 12px;
          margin-left: 6px;
        }
        .tab.active .tab-count {
          background: #fef3e2;
          color: #e67e22;
        }

        /* Stock Table */
        .stock-table {
          width: 100%;
          border-collapse: collapse;
          background: #fff;
          border-radius: 12px;
          overflow: hidden;
          box-shadow: 0 2px 8px rgba(0,0,0,0.04);
          table-layout: fixed;
        }
        .stock-table th {
          text-align: left;
          padding: 14px 16px;
          font-size: 12px;
          font-weight: 600;
          color: #8a8578;
          text-transform: uppercase;
          letter-spacing: 0.5px;
          background: #fafaf8;
          border-bottom: 1px solid #e8e6e1;
        }
        .stock-table th:nth-child(1) { width: 45%; }
        .stock-table th:nth-child(2) { width: 18%; }
        .stock-table th:nth-child(3) { width: 27%; }
        .stock-table th:nth-child(4) { width: 10%; }
        
        .stock-table td {
          padding: 14px 16px;
          border-bottom: 1px solid #f0eeea;
          font-size: 14px;
          vertical-align: middle;
        }
        .stock-table tr:last-child td { border-bottom: none; }
        
        .stock-row.expired { background: #fef9f9; }
        .stock-row.today { background: #fefbf5; }
        
        .name-content {
          display: flex;
          align-items: center;
          gap: 10px;
        }
        .category-icon-small { font-size: 16px; flex-shrink: 0; }
        .product-name {
          font-weight: 500;
          color: #2d2a24;
        }
        .note-inline {
          font-size: 12px;
          color: #9a9890;
          margin-left: 4px;
        }
        .note-inline::before {
          content: '·';
          margin-right: 4px;
          color: #c0bdb5;
        }
        
        .qty-value { 
          color: #6b6860;
          font-weight: 400;
        }
        
        .expiry-content {
          display: flex;
          align-items: center;
        }
        .expiry-block {
          display: flex;
          flex-direction: column;
        }
        .expiry-relative {
          font-weight: 500;
          font-size: 13px;
        }
        .expiry-relative.expired { color: #c0392b; }
        .expiry-relative.today { color: #d35400; }
        .expiry-relative.soon { color: #b8860b; }
        .expiry-relative.ok { color: #27ae60; }
        
        .expiry-absolute {
          font-size: 11px;
          color: #8a8578;
          margin-top: 2px;
        }
        
        .action-icon {
          width: 28px;
          height: 28px;
          border-radius: 6px;
          border: 1px solid #e0ded8;
          background: #fff;
          color: #8a8578;
          cursor: pointer;
          font-size: 14px;
          transition: all 0.15s;
        }
        .action-icon:hover { 
          background: #f0f9f4;
          border-color: #c8e6c9;
          color: #27ae60;
        }
      `}</style>
        </div>
    );
}