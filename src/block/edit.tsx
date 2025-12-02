import { __ } from '@wordpress/i18n';

import {
    useBlockProps,
    InspectorControls
} from "@wordpress/block-editor";

import {
    Placeholder,
    __experimentalGrid as Grid,
    __experimentalHeading as Heading,
    __experimentalSpacer as Spacer,
    __experimentalDivider as Divider,
    CheckboxControl,
    Button,
    TextControl,
    PanelBody,
    SelectControl,
} from "@wordpress/components";

import { cloud } from "@wordpress/icons";

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import "./editor.scss";

interface EditProps {
    attributes: {
        view: 'list' | 'table' | 'gallery';
        show: ('name' | 'type')[];
        sort: 'asc' | 'desc';
        index: string;
        show_title: boolean;
        isInitialSetup: boolean;
        filetype: string[];
        changeTitle: string;
    }
    setAttributes: (attributes: Partial<EditProps["attributes"]>) => void;
}

export default function Edit({ attributes, setAttributes }: EditProps) {

    const {
        view,
        show,
        sort,
        index,
        show_title,
        isInitialSetup,
        filetype,
        changeTitle,

    } = attributes;

    const blockProps = useBlockProps();

    const toggleShow = (key: 'type' ) => {
        const newShow = show.includes(key)
            ? show.filter((item) => item !== key)
            : [...show, key];

        setAttributes({ show: newShow });
    };

    // ■ Root + Subfolder state
    const [rootFolderOptions, setRootFolderOptions] = useState<{ label: string; value: string }[]>([]);
    const [folderOptions, setFolderOptions] = useState<{ label: string; value: string }[]>([]);


    // Load subfolders (only one level, rootfolder comes from plugin settings)
    useEffect(() => {
        let isActive = true;

        apiFetch({ path: '/rrze-faubox/v1/folders' })
            .then((data) => {
                if (!isActive) return;

                if (Array.isArray(data)) {
                    setFolderOptions(data);
                } else {
                    setFolderOptions([]);
                }
            })
            .catch(() => {
                if (isActive) setFolderOptions([]);
            });

        return () => {
            isActive = false;
        };
    }, []);

    // Allowed filetypes for sidebar controls
    const filetypeOptions = ['pdf', 'docx', 'txt', 'zip', 'ppt', 'jpg', 'png', 'svg', 'webp'];


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
                        <hr />
                        <Spacer paddingBottom={"1rem"} />
                        <Heading level={3}>{__('FAUbox Settings', 'rrze-faubox')}</Heading>
                        <p>{__('Configure FAUbox root folder & folder view.', 'rrze-faubox')}</p>
                        <Spacer paddingBottom={"1rem"} />

                        <Grid columns={7}>

                            {/* LEFT SIDE */}
                            <div style={{ gridColumn: "span 3" }}>


                                {/* SUBFOLDER */}
                                <SelectControl
                                    label={__('Folder Selection', 'rrze-faubox')}
                                    __next40pxDefaultSize
                                    value={index}
                                    options={[
                                        {value: "", label: __('Select Subfolder', 'rrze-faubox'), disabled: true},
                                        ...folderOptions //
                                    ]}
                                    onChange={(val: string) => setAttributes({index: val})}
                                />

                                <SelectControl
                                    label={__('View', 'rrze-faubox')}
                                    value={view}
                                    __next40pxDefaultSize
                                    options={[
                                        { disabled: true, label: __('Select an Option', 'rrze-faubox'), value: '' },
                                        { label: __('List', 'rrze-faubox'), value: 'list' },
                                        { label: __('Table', 'rrze-faubox'), value: 'table' },
                                        { label: __('Gallery', 'rrze-faubox'), value: 'gallery' },
                                    ]}
                                    onChange={(val: 'list' | 'table' | 'gallery') =>
                                        setAttributes({view: val as 'list' | 'table' | 'gallery'})
                                    }
                                />

                                <SelectControl
                                    label={__('Sorting', 'rrze-faubox')}
                                    __next40pxDefaultSize
                                    value={sort}
                                    options={[
                                        {label: __('Ascending', 'rrze-faubox'), value: 'asc'},
                                        {label: __('Descending', 'rrze-faubox'), value: 'desc'},
                                    ]}
                                    onChange={(val: 'asc' | 'desc') =>
                                        setAttributes({sort: val as 'asc' | 'desc'})
                                    }
                                />

                            </div>
                            <Spacer paddingTop=".5rem"/>

                            {/* right side */}

                            <div style={{gridColumn: "span 3"}}>
                                <Heading level={4}>{__('Displayed file information', 'rrze-faubox')}</Heading>
                                <Spacer paddingTop={"0.5rem"}/>
                                <CheckboxControl
                                    label={__('File Type', 'rrze-faubox')}
                                    checked={show.includes('type')}
                                    onChange={() => toggleShow('type')}
                                />
                                <CheckboxControl
                                    label={__('Folder Title', 'rrze-faubox')}
                                    checked={show_title}
                                    onChange={(value) => setAttributes({show_title: value})}
                                />
                                <Spacer paddingBottom={".5rem"}/>
                                <hr/>
                                <Spacer paddingBottom={".5rem"}/>
                                <Heading level={4}>{__('Data to be displayed', "rrze-faubox")}</Heading>
                                <Spacer paddingTop={"0.5rem"}/>
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
                            </div>
                        </Grid>

                        <Spacer paddingBottom={"0.5rem"}/>
                        <Button
                            variant="primary"
                            onClick={() => setAttributes({isInitialSetup: false})}
                        >
                            {__('Finish initial setup', 'rrze-faubox')}
                        </Button>
                        <Spacer paddingBottom={"0.5rem"}/>
                    </div>

                    <div>
                        <hr/>
                        <Spacer paddingTop={"1rem"}/>
                        <Heading level={3}>{__('Data Preview', 'rrze-faubox')}</Heading>
                        <Spacer paddingBottom={"0.2rem"}/>
                        <ServerSideRender
                            block="rrze/faubox"
                            attributes={attributes}
                        />
                        <Spacer/>
                    </div>
                </Placeholder>
            ) : (
                <>
                    <InspectorControls>
                        <PanelBody title={__('Data Choice', 'rrze-faubox')} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <SelectControl
                                label={__('Folder Selection', 'rrze-faubox')}
                                help={__('Select Subfolder', 'rrze-faubox')}
                                value={index}
                                options={folderOptions}
                                onChange={(val: string) => setAttributes({index: val})}
                            />
                        </PanelBody>

                        <PanelBody title={__('Display Options', 'rrze-faubox')} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <SelectControl
                                label={__('View', 'rrze-faubox')}
                                value={view}
                                options={[
                                    { disabled: true, label: __('Select an Option', 'rrze-faubox'), value: '' },
                                    { label: __('List', 'rrze-faubox'), value: 'list' },
                                    { label: __('Table', 'rrze-faubox'), value: 'table' },
                                    { label: __('Gallery', 'rrze-faubox'), value: 'gallery' },
                                ]}
                                onChange={(val: 'list' | 'table' | 'gallery') =>
                                    setAttributes({view: val as 'list' | 'table' | 'gallery'})
                                }
                            />

                            <Divider margin="3" />
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
                            <Divider margin="2" />
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
                            <Divider margin="2" />
                            <TextControl
                                label={__('Overwrite folder name', 'rrze-faubox')}
                                help={__('If left blank, the FAUbox folder name will be used automatically.', 'rrze-faubox')}
                                value={changeTitle}
                                onChange={(val) => setAttributes({ changeTitle: val })}
                            />
                        </PanelBody>

                        <PanelBody title={__('Sorting & Order', 'rrze-faubox')} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
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

                    {/* Ausgabe nach Setup */}
                    <ServerSideRender
                        block="rrze/faubox"
                        attributes={attributes}
                    />
                </>
            )}
        </div>
    );
}
