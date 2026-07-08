import {__} from '@wordpress/i18n';
import {BlockControls} from '@wordpress/block-editor';
import {ToolbarGroup, ToolbarButton} from '@wordpress/components';
import {pages} from '@wordpress/icons';

interface FolderToolbarProps {
    isEditing: boolean;
    onToggle: () => void;
}

export default function FolderToolbar({isEditing, onToggle}: FolderToolbarProps) {
    return (
        <BlockControls>
            <ToolbarGroup>
                        <ToolbarButton
                            icon={pages}
                            label={__('Change folder', 'rrze-faubox')}
                            onClick={onToggle}
                            isPressed={isEditing}
                        />
            </ToolbarGroup>
        </BlockControls>
    );
}


