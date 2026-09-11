import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { useT } from '@/i18n';

export default function CancelProcessingModal({ open, onOpenChange, onConfirm, busy = false }) {
    const t = useT();

    return (
        <Dialog open={open} onOpenChange={busy ? undefined : onOpenChange}>
            <DialogContent className="sm:max-w-md" showCloseButton={!busy}>
                <DialogHeader>
                    <DialogTitle>{t('progress.stopTitle')}</DialogTitle>
                    <DialogDescription>{t('progress.stopBody')}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="border-0 bg-transparent p-0">
                    <Button type="button" variant="outline" disabled={busy} onClick={() => onOpenChange(false)}>
                        {t('progress.keepGoing')}
                    </Button>
                    <Button type="button" variant="destructive" disabled={busy} onClick={onConfirm}>
                        {busy ? t('progress.stopping') : t('progress.confirmStop')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
