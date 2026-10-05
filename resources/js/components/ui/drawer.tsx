import type { ReactNode } from 'react';
import { Dialog } from './dialog';

export function Drawer({ children, description, footer, onClose, open, title }: { children: ReactNode; description?: string; footer?: ReactNode; onClose: () => void; open: boolean; title: string }) {
    return <Dialog open={open} onClose={onClose} title={title} description={description} footer={footer} variant="drawer">{children}</Dialog>;
}
