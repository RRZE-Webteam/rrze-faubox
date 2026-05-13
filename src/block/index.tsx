import './editor.scss';

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';

// register block
registerBlockType(metadata.name as string, {
    ...metadata,
    edit: Edit,
    save: (): null => null,
} as any);
