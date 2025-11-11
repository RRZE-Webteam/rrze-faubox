import {__} from '@wordpress/i18n';

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

import {cloud} from "@wordpress/icons";

import {useEffect, useState} from '@wordpress/element';

import ServerSideRender from '@wordpress/server-side-render';
import "./editor.scss";


interface EditProps {
    attributes: {
        view: 'list' | 'table' | 'gallery';
        show: ('name' | 'type' | 'size' | 'modified')[];
        sort: 'asc' | 'desc';
        index: string;
        show_title: boolean;
        isInitialSetup: boolean;
        orderby: string;
        filetype: string[];
        changeTitle: string;
    }
    setAttributes: (attributes: Partial<EditProps["attributes"]>) => void;
}

export default function Edit({attributes, setAttributes}: EditProps) {
    const {view, show, sort, index, show_title, isInitialSetup, orderby, filetype, changeTitle } = attributes;
    const blockProps = useBlockProps();

    const toggleShow = (key: 'size' | 'type' | 'modified') => {
        const newShow = show.includes(key)
            ? show.filter((item) => item !== key)
            : [...show, key];

        setAttributes({show: newShow});
    };

    const [folderOptions, setFolderOptions] = useState<{ label: string; value: string }[]>([]);
    // Load folder options via REST
    useEffect(() => {
        fetch('/wp-json/rrze-faubox/v1/folders')
            .then((res) => res.json())
            .then((data) => {
                setFolderOptions(data);
            })
            .catch(() => {
                setFolderOptions([
                    {value: '/ss25', label: 'SS25'},
                    {value: '/ss24', label: 'SS24'},
                    {value: '/ws23', label: 'WS23'},
                ]);
            });
    }, []);


    const filetypeOptions = ['pdf', 'docx', 'txt', 'zip', 'ppt', 'jpg', 'png', 'svg', 'webp' ];

    return (
        <div {...blockProps}>
            {isInitialSetup ? (
                <Placeholder
                    label={__("Faubox Block", "rrze-faubox")}
                    instructions={__("Configure your Faubox block.", "rrze-faubox")}
                    isColumnLayout={true}
                    icon={cloud}
                >
                    <div>
                        <hr/>
                        <Spacer paddingBottom={"1rem"}/>
                        <Heading level={3}>{__("Faubox Settings", "rrze-faubox")}</Heading>
                        <p>{__("Please set up your FAUbox access in advance via the WordPress Dashboard > Settings > FAUbox.", "rrze-faubox")}</p>
                        <Spacer paddingBottom={"1rem"}/>

                        <Grid columns={7}>
                            {/* lef side */}
                            <div style={{gridColumn: "span 3"}}>
                                <SelectControl
                                    label="Folder Selection"
                                    __next40pxDefaultSize
                                    value={index}
                                    options={[
                                        {value: "", label: __("Select Subfolder", "rrze-faubox"), disabled: true},
                                        ...folderOptions //
                                    ]}
                                    onChange={(val: string) => setAttributes({index: val})}
                                />

                                <SelectControl
                                    label={__("View", "rrze-faubox")}
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
                                    label={__("Sorting", "rrze-faubox")}
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
                                <SelectControl
                                    label={__("Order", "rrze-faubox")}
                                    __next40pxDefaultSize
                                    value={orderby}
                                    options={[
                                        {label: __('Name', 'rrze-faubox'), value: 'name'},
                                        {label: __('File Size', 'rrze-faubox'), value: 'size'},
                                        {label: __('File Type', 'rrze-faubox'), value: 'type'},
                                        {label: __('File Changed Date', 'rrze-faubox'), value: 'modified'}
                                    ]}
                                    onChange={(val: 'name' | 'size' | 'type' | 'modified') =>
                                        setAttributes({orderby: val as 'name' | 'size' | 'type' | 'modified'})
                                    }
                                />

                            </div>
                            <Spacer paddingTop=".5rem"/>

                            {/* right side */}

                            <div style={{gridColumn: "span 3"}}>
                                <Heading level={4}>{__("Show", "rrze-faubox")}</Heading>
                                <Spacer paddingTop={"0.5rem"}/>
                                <CheckboxControl
                                    label={__("File Size", "rrze-faubox")}
                                    checked={show.includes('size')}
                                    onChange={() => toggleShow('size')}
                                />
                                <CheckboxControl
                                    label={__("File Type", "rrze-faubox")}
                                    checked={show.includes('type')}
                                    onChange={() => toggleShow('type')}
                                />
                                <CheckboxControl
                                    label={__("File Changed Date", "rrze-faubox")}
                                    checked={show.includes('modified')}
                                    onChange={() => toggleShow('modified')}
                                />
                                <CheckboxControl
                                    label={__("Show File Title", "rrze-faubox")}
                                    checked={show_title}
                                    onChange={(value) => setAttributes({show_title: value})}
                                />
                                <Spacer paddingBottom={".5rem"}/>
                                <hr/>
                                <Spacer paddingBottom={".5rem"}/>
                                <Heading level={4}>{__("Data to be displayed", "rrze-faubox")}</Heading>
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
                            {__("Finish initial setup", "rrze-faubox")}
                        </Button>
                        <Spacer paddingBottom={"0.5rem"}/>
                    </div>

                    <div>
                        <hr/>
                        <Spacer paddingTop={"1rem"}/>
                        <Heading level={3}>{__("Data Preview", "rrze-faubox")}</Heading>
                        <Spacer paddingBottom={"0.2rem"}/>
                        <ServerSideRender
                            block="rrze/faubox"
                            attributes={{ ...attributes, is_preview: true }}
                        />
                        <Spacer/>
                    </div>
                </Placeholder>
            ) : (
                <>
                    <InspectorControls>
                        <PanelBody title={__("Data Choice", "rrze-faubox")} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <SelectControl
                                label="Folder Selection"
                                help={__("Select Subfolder", "rrze-downloads")}
                                value={index}
                                options={folderOptions}
                                onChange={(val: string) => setAttributes({index: val})}
                            />
                        </PanelBody>

                        <PanelBody title={__("Darstellungsoptionen", "rrze-faubox")} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <SelectControl
                                label={__("View", "rrze-faubox")}
                                value={view}
                                options={[
                                    {disabled: true, label: 'Select an Option', value: ''},
                                    {label: 'List', value: 'list'},
                                    {label: 'Table', value: 'table'},
                                    {label: 'Gallery', value: 'gallery'},
                                ]}
                                onChange={(val: 'list' | 'table' | 'gallery') =>
                                    setAttributes({view: val as 'list' | 'table' | 'gallery'})
                                }
                            />

                            <Divider margin="3" />
                            <Heading color="#03316a">Angezeigte Dateiinformationen</Heading>
                            <CheckboxControl
                                label={__("File Size", "rrze-faubox")}
                                checked={show.includes('size')}
                                onChange={() => toggleShow('size')}
                            />
                            <CheckboxControl
                                label={__("File Type", "rrze-faubox")}
                                checked={show.includes('type')}
                                onChange={() => toggleShow('type')}
                            />
                            <CheckboxControl
                                label={__("File Changed Date", "rrze-faubox")}
                                checked={show.includes('modified')}
                                onChange={() => toggleShow('modified')}
                            />
                            <CheckboxControl
                                label={__("Show File Title", "rrze-faubox")}
                                checked={show_title}
                                onChange={(value) => setAttributes({show_title: value})}
                            />
                            <Divider margin="2" />
                            <Heading color="#03316a">Erlaubte Dateiformate</Heading>
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
                                label={__("Ordnername überschreiben", "rrze-faubox")}
                                help={__("Wenn leer, wird automatisch der FAUbox-Ordnername verwendet.", "rrze-faubox")}
                                value={attributes.changeTitle}
                                onChange={(val) => setAttributes({ changeTitle: val })}
                            />
                        </PanelBody>

                        <PanelBody title={__("Sortierung & Reihenfolge", "rrze-faubox")} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <SelectControl
                                label="Sortierung"
                                value={sort}
                                options={[
                                    {label: 'Aufsteigend', value: 'asc'},
                                    {label: 'Absteigend', value: 'desc'},
                                ]}
                                onChange={(val: 'asc' | 'desc') =>
                                    setAttributes({sort: val as 'asc' | 'desc'})
                                }
                            />
                            <SelectControl
                                label="Reihenfolge"
                                value={orderby}
                                options={[
                                    {label: __('Name', 'rrze-faubox'), value: 'name'},
                                    {label: __('File Size', 'rrze-faubox'), value: 'size'},
                                    {label: __('File Type', 'rrze-faubox'), value: 'type'},
                                    {label: __('File Changed Date', 'rrze-faubox'), value: 'modified'}
                                ]}
                                onChange={(val: 'name' | 'size' | 'type' | 'modified') =>
                                    setAttributes({orderby: val as 'name' | 'size' | 'type' | 'modified'})
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