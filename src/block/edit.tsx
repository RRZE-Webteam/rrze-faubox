import {__} from '@wordpress/i18n';

import {
    useBlockProps,
    InspectorControls
} from "@wordpress/block-editor";

import {
    Placeholder,
    __experimentalHeading as Heading,
    __experimentalSpacer as Spacer,
    __experimentalDivider as Divider,
    CheckboxControl,
    Button,
    TextControl,
    PanelBody,
    SelectControl,
} from "@wordpress/components";

import {cloud} from "@wordpress/icons";

import {useEffect, useState} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import "./editor.scss";

interface EditProps {
    attributes: {
        view: 'list' | 'table' | 'gallery';
        show: ('name' | 'type')[];
        sort: 'asc' | 'desc';
        show_title: boolean;
        isInitialSetup: boolean;
        filetype: string[];
        changeTitle: string;
        sharelink: string;
        selectedFolders: string[];
    }
    setAttributes: (attributes: Partial<EditProps["attributes"]>) => void;
}

export default function Edit({attributes, setAttributes}: EditProps) {

    const {
        view,
        show,
        sort,
        show_title,
        isInitialSetup,
        filetype,
        changeTitle,
        sharelink,
        selectedFolders = [],
    } = attributes;

    const blockProps = useBlockProps();

    const toggleShow = (key: 'type') => {
        const newShow = show.includes(key)
            ? show.filter((item) => item !== key)
            : [...show, key];

        setAttributes({show: newShow});
    };
// Regex for public FAUbox share link
    const isValidSharelink = /^https:\/\/faubox\.rrze\.uni\-erlangen\.de\/getlink\/[A-Za-z0-9]+\/?$/.test(
        sharelink
    );

    // Folder + File options coming from REST
    const [folderOptions, setFolderOptions] = useState<{ label: string; value: string }[]>([]);

    /**
     * Load root folders + files from REST
     */
    useEffect(() => {
        if (!sharelink || !isValidSharelink) {
            setFolderOptions([]);

            return;
        }

        apiFetch({
            path: `/rrze-faubox/v1/folders?sharelink=${encodeURIComponent(sharelink)}`
        })
            .then((data: any) => {
                setFolderOptions(data.folders || []);

            })
            .catch(() => {
                setFolderOptions([]);

            });
    }, [sharelink]);


    // Allowed filetypes for sidebar controls
    const filetypeOptions = ['pdf', 'docx', 'txt', 'zip', 'ppt', 'jpg', 'jpeg', 'png', 'svg', 'webp'];


    return (
        <div {...blockProps}>

            {isInitialSetup ? (
                <Placeholder
                    label={__('FAUbox Block', 'rrze-faubox')}
                    instructions={__('Configure your FAUbox block.', 'rrze-faubox')}
                    icon={cloud}
                    isColumnLayout={true}
                >
                    <div>
                        <hr/>
                        <Spacer paddingBottom={"1rem"}/>
                        <div>
                            <TextControl
                                label={__('FAUbox Share Link', 'rrze-faubox')}
                                value={attributes.sharelink}
                                onChange={(val) => setAttributes({sharelink: val})}
                                help={__('Paste your FAUbox public link here (Root Folder)', 'rrze-faubox')}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault(); // verhindert Zeilenumbrüche
                                        setAttributes({ isInitialSetup: false });
                                    }
                                }}
                            />
                            {/* Validation message */}
                            {sharelink !== '' && !isValidSharelink && (
                                <p style={{color: "red", marginTop: "4px"}}>
                                    {__('Invalid FAUbox public link.', 'rrze-faubox')}
                                </p>
                            )}


                        </div>
                        <Spacer paddingTop=".5rem"/>


                        <Spacer paddingBottom={"0.5rem"}/>
                        <Button
                            variant="primary"
                            onClick={() => setAttributes({isInitialSetup: false})}
                        >
                            {__('Save Link', 'rrze-faubox')}
                        </Button>
                        <Spacer paddingBottom={"0.5rem"}/>
                    </div>


                </Placeholder>
            ) : (
                <>
                    <InspectorControls>
                        <PanelBody title={__('Data Choice', 'rrze-faubox')} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <TextControl
                                label={__('FAUbox Share Link', 'rrze-faubox')}
                                value={sharelink}
                                onChange={(val) => setAttributes({sharelink: val})}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault(); // verhindert Zeilenumbrüche
                                        setAttributes({ isInitialSetup: false });
                                    }
                                }}
                            />

                            {sharelink !== '' && !isValidSharelink && (
                                <p style={{color: "red", marginTop: "4px"}}>
                                    {__('Invalid FAUbox public link.', 'rrze-faubox')}
                                </p>
                            )}
                            <Spacer paddingTop={"0.5rem"}/>
                            <Heading color="#03316a">{__('Select Folders', 'rrze-faubox')}</Heading>

                            {folderOptions.length === 0 && <p>{__('No folders found.', 'rrze-faubox')}</p>}

                            {folderOptions.map((folder) => (
                                <CheckboxControl
                                    key={folder.value}
                                    label={folder.label}
                                    checked={selectedFolders.includes(folder.value)}
                                    onChange={(checked) => {
                                        const updated = checked
                                            ? [...selectedFolders, folder.value]
                                            : selectedFolders.filter((v) => v !== folder.value);
                                        setAttributes({selectedFolders: updated})
                                    }}
                                />
                            ))}
                        </PanelBody>

                        <PanelBody title={__('Display Options', 'rrze-faubox')} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <SelectControl
                                label={__('View', 'rrze-faubox')}
                                value={view}
                                options={[
                                    {disabled: true, label: __('Select an Option', 'rrze-faubox'), value: ''},
                                    {label: __('List', 'rrze-faubox'), value: 'list'},
                                    {label: __('Table', 'rrze-faubox'), value: 'table'},
                                ]}
                                onChange={(val: 'list' | 'table' | 'gallery') =>
                                    setAttributes({view: val as 'list' | 'table' | 'gallery'})
                                }
                            />

                            <Divider margin="3"/>
                            <Heading color="#03316a">{__('Displayed file information', 'rrze-faubox')}</Heading>
                            <CheckboxControl
                                label={__("File Type", 'rrze-faubox')}
                                checked={show.includes('type')}
                                onChange={() => toggleShow('type')}
                            />
                            <CheckboxControl
                                label={__('Folder Title', 'rrze-faubox')}
                                checked={show_title}
                                onChange={(value) => setAttributes({show_title: value})}
                            />
                            <Divider margin="2"/>
                            <TextControl
                                label={__('Overwrite folder name', 'rrze-faubox')}
                                help={__('If left blank, the FAUbox folder name will be used automatically.', 'rrze-faubox')}
                                value={changeTitle}
                                onChange={(val) => setAttributes({changeTitle: val})}
                            />
                            <Divider margin="2"/>
                            <Heading color="#03316a">{__('Permitted file formats', 'rrze-faubox')}</Heading>
                            {filetypeOptions.map((type) => (
                                <CheckboxControl
                                    key={type}
                                    label={type}
                                    checked={filetype.includes(type)}
                                    onChange={(isChecked) => {
                                        const updated = isChecked
                                            ? [...filetype, type]
                                            : filetype.filter((item) => item !== type);
                                        setAttributes({filetype: updated});
                                    }}
                                />
                            ))}

                            <Divider margin="2"/>
                            <SelectControl
                                label={__('Sorting', 'rrze-faubox')}
                                value={sort}
                                options={[
                                    {label: __('Ascending', 'rrze-faubox'), value: 'asc'},
                                    {label: __('Descending', 'rrze-faubox'), value: 'desc'},
                                ]}
                                onChange={(val: 'asc' | 'desc') =>
                                    setAttributes({sort: val as 'asc' | 'desc'})
                                }
                            />
                        </PanelBody>

                    </InspectorControls>
                    { /* Ausgabe nach Setup */}
                    {selectedFolders.length === 0 ? (
                        <p style={{opacity: 0.7}}>
                            {__('FAUbox link saved. Select your folder in the sidebar.', 'rrze-faubox')}
                        </p>
                    ) : (
                        <ServerSideRender
                            block="rrze/faubox"
                            attributes={attributes}
                        />
                    )}

                </>
            )}
        </div>
    );
}
