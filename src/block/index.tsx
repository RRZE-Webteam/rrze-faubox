import './editor.scss';

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';
import { ReactComponent as FauboxLogo } from  '../../assets/svg/FAUbox_Logo_neg_1zu1.svg';


registerBlockType(metadata.name as string, {
    ...metadata,
    icon: <FauboxLogo />,
    edit: Edit,
    save: (): null => null,
} as any);
