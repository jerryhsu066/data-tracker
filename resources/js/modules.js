export const modules = [
    {
        id: 'stocks',
        label: 'Stocks',
        basePath: '/stocks',
        defaultTab: 'home',
        tabs: [
            { id: 'home',         label: 'Overview',      path: '/stocks/home' },
            { id: 'dashboard',    label: 'Portfolio',      path: '/stocks/dashboard' },
            { id: 'list',         label: 'Stocks',         path: '/stocks/list' },
            { id: 'transactions', label: 'Transactions',   path: '/stocks/transactions' },
            { id: 'exposure',     label: 'Exposure',       path: '/stocks/exposure' },
            { id: 'settings',     label: 'Settings',       path: '/stocks/settings' },
        ],
    },
    {
        id: 'cashflow',
        label: 'Cashflow',
        basePath: '/cashflow',
        defaultTab: 'home',
        tabs: [
            { id: 'home',     label: 'Overview', path: '/cashflow/home' },
            { id: 'log',      label: 'Log',      path: '/cashflow/log' },
            { id: 'settings', label: 'Settings', path: '/cashflow/settings' },
        ],
    },
];

export function findModuleByPath(path) {
    return modules.find(m => path.startsWith(m.basePath));
}
