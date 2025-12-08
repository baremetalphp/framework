import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const container = document.getElementById('app');
if (container) {
    const component = container.dataset.component || 'App';
    const props = container.dataset.props ? JSON.parse(container.dataset.props) : {};
    
    const root = createRoot(container);
    root.render(<App {...props} />);
}