import React from 'react';
import { Ship } from 'lucide-react';

const SkeletonLoader = () => {
    const styles = {
        wrapper: {
            height: '100vh',
            width: '100vw',
            display: 'flex',
            backgroundColor: '#0b0f19',
            color: '#f8fafc',
            fontFamily: 'system-ui, -apple-system, sans-serif',
            overflow: 'hidden',
        },
        sidebar: {
            width: '260px',
            backgroundColor: '#111827',
            borderRight: '1px solid rgba(255, 255, 255, 0.1)',
            display: 'flex',
            flexDirection: 'column',
            padding: '24px 16px',
            gap: '16px',
        },
        sidebarItem: {
            height: '40px',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderRadius: '8px',
            animation: 'pulse 1.5s infinite ease-in-out',
        },
        mainContent: {
            flex: 1,
            display: 'flex',
            flexDirection: 'column',
            overflowY: 'auto',
            padding: '32px',
            gap: '32px',
        },
        headerPlaceholder: {
            height: '40px',
            backgroundColor: 'rgba(30, 41, 59, 0.7)',
            borderRadius: '8px',
            width: 'min(60%, 300px)',
            animation: 'pulse 1.5s infinite ease-in-out',
            marginBottom: '24px'
        },
        statsGrid: {
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
            gap: '24px',
        },
        statCard: {
            backgroundColor: 'rgba(30, 41, 59, 0.7)',
            border: '1px solid rgba(255, 255, 255, 0.1)',
            borderRadius: '12px',
            padding: '24px',
            height: '120px',
            animation: 'pulse 1.5s infinite ease-in-out',
        },
        chartPlaceholder: {
            backgroundColor: 'rgba(30, 41, 59, 0.7)',
            border: '1px solid rgba(255, 255, 255, 0.1)',
            borderRadius: '12px',
            height: '300px',
            animation: 'pulse 1.5s infinite ease-in-out',
        },
        tablePlaceholder: {
            backgroundColor: 'rgba(30, 41, 59, 0.7)',
            border: '1px solid rgba(255, 255, 255, 0.1)',
            borderRadius: '12px',
            height: '250px',
            animation: 'pulse 1.5s infinite ease-in-out',
        },
    };

    return (
        <div style={styles.wrapper}>
            <style>{`
                @media (max-width: 1024px) {
                    .sidebar {
                        display: none !important;
                    }
                    .stats-grid {
                        grid-template-columns: 1fr !important;
                    }
                    .dashboard-charts {
                        grid-template-columns: 1fr !important;
                    }
                    .main-content {
                        padding: 20px !important;
                    }
                }
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.4; }
                    100% { opacity: 1; }
                }
            `}</style>
            <aside className="sidebar" style={styles.sidebar}>
                <div style={{ ...styles.sidebarItem, height: '60px', width: '60px', borderRadius: '12px', marginBottom: '24px' }}></div>
                <div style={styles.sidebarItem}></div>
                <div style={styles.sidebarItem}></div>
                <div style={styles.sidebarItem}></div>
                <div style={styles.sidebarItem}></div>
                <div style={styles.sidebarItem}></div>
                <div style={{ ...styles.sidebarItem, marginTop: 'auto' }}></div>
            </aside>
            <main className="main-content" style={styles.mainContent}>
                <div style={styles.headerPlaceholder}></div>
                <div className="stats-grid" style={styles.statsGrid}>
                    <div style={styles.statCard}></div>
                    <div style={styles.statCard}></div>
                    <div style={styles.statCard}></div>
                    <div style={styles.statCard}></div>
                </div>
                <div className="dashboard-charts" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
                    <div style={styles.chartPlaceholder}></div>
                    <div style={styles.chartPlaceholder}></div>
                </div>
                <div className="data-table-container" style={styles.tablePlaceholder}></div>
            </main>
        </div>
    );
};

export default SkeletonLoader;