import { __ } from "@wordpress/i18n";
import { useBlockProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';


import {
    PanelBody,
    Panel,
    ColorPalette,
    SelectControl,
    __experimentalHeading as Heading,
} from "@wordpress/components";

import "./editor.scss";

type Attributes = {
    folder: string;
};

const Edit = (props) => {
    const { attributes, setAttributes } = props;
    const blockProps = useBlockProps();

    return (
        <div {...blockProps}>
            <label htmlFor="faubox-folder">
                FAUbox Ordner-ID:
            </label>
            <input
                id="faubox-folder"
                type="text"
                value={attributes.folder}
                onChange={(event) =>
                    setAttributes({ folder: event.target.value })
                }
                placeholder="z. B. a1b2c3..."
            />
        </div>
    );
};

export default Edit;
