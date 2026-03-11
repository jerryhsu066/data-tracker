export const modules = [
    {
        id: 'stocks',
        icon: '📈',
        label: 'Stocks',
        home: '/stocks/home',
        links: [
            { to: '/stocks/home',         label: 'Home' },
            { to: '/stocks/dashboard',    label: 'Portfolio' },
            { to: '/stocks/list',         label: 'Stocks' },
            { to: '/stocks/transactions', label: 'Transactions' },
            { to: '/stocks/exposure',     label: 'Exposure' },
            { to: '/stocks/settings',     label: 'Settings' },
        ],
    },
    {
        id: 'cashflow',
        icon: '💰',
        label: 'Cashflow',
        home: '/cashflow/home',
        links: [
            { to: '/cashflow/home',     label: 'Home' },
            { to: '/cashflow/enter',    label: 'Enter' },
            { to: '/cashflow/overview', label: 'Overview' },
            { to: '/cashflow/settings', label: 'Settings' },
        ],
    },
];
