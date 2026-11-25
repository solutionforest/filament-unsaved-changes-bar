<?php

namespace SolutionForest\FilamentUnsavedChangesBar\Enums;

/**
 * What happens when the user clicks an SPA link (sidebar, breadcrumb…) while the form is dirty.
 * Closing the tab or a full reload always shows the browser's native dialog.
 */
enum NavigationGuard: string
{
    /** Open an "Unsaved changes" modal: Save & leave / Discard & leave / Keep editing. */
    case Modal = 'modal';

    /** Stay on the page and shake the bar. */
    case Shake = 'shake';

    /** Don't intercept SPA navigation. */
    case None = 'none';
}
